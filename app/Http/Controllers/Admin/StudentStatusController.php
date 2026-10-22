<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\AuditLog;
use App\Models\StudentProfile;
use App\Models\StudentStatusBatch;
use App\Models\User;
use App\Services\StudentStatusUpdate;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StudentStatusController extends Controller
{
    public function index(Request $request)
    {
        $student = $request->filled('student')
            ? User::where('role', 'student')->with('studentProfile')->findOrFail($request->integer('student')) : null;

        return view('admin.student-statuses.index', [
            'student' => $student,
            'periods' => AcademicPeriod::where('is_active', true)->orderByDesc('starts_on')->get(),
            'batches' => StudentStatusBatch::with('period')->latest('id')->paginate(10),
        ]);
    }

    public function store(Request $request, StudentStatusUpdate $service)
    {
        $data = $request->validate([
            'academic_period_id' => ['required', 'exists:academic_periods,id'],
            'source_reference' => ['required', 'string', 'max:255'],
            'effective_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'csv_file' => ['required_without:student_number', 'file', 'mimes:csv,txt', 'max:5120'],
            'student_number' => ['required_without:csv_file', 'string', 'regex:/^\d{4}-\d+$/'],
            'student_status' => ['required_with:student_number', Rule::in(array_keys(User::STUDENT_STATUSES))],
        ]);
        $entries = [];
        if ($request->hasFile('csv_file')) {
            $handle = fopen($request->file('csv_file')->getRealPath(), 'r');
            try {
                $headers = fgetcsv($handle, null, ',', '"', '');
                $headers = array_map(fn ($v) => strtolower(trim(ltrim((string) $v, "\xEF\xBB\xBF"))), $headers ?: []);
                if (count($headers) !== count(array_unique($headers)) || array_diff(['student_number', 'student_status'], $headers)) {
                    throw ValidationException::withMessages(['csv_file' => 'Include unique student_number and student_status column headers.']);
                }
                $numberColumn = array_search('student_number', $headers);
                $statusColumn = array_search('student_status', $headers);
                $line = 1;
                while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
                    $line++;
                    if (! array_filter($row, fn ($v) => trim((string) $v) !== '')) { continue; }
                    $number = trim((string) ($row[$numberColumn] ?? ''));
                    $status = strtolower(trim((string) ($row[$statusColumn] ?? '')));
                    if (count($row) !== count($headers) || ! preg_match('/^\d{4}-\d+$/', $number)
                        || isset($entries[$number]) || ! isset(User::STUDENT_STATUSES[$status])) {
                        throw ValidationException::withMessages(['csv_file' => "Row {$line}: check the student number, duplicate entries, status, and column count."]);
                    }
                    $entries[$number] = $status;
                    if (count($entries) > 20000) {
                        throw ValidationException::withMessages(['csv_file' => 'Limit each upload to 20,000 students.']);
                    }
                }
            } finally { fclose($handle); }
        } else {
            $entries[$data['student_number']] = $data['student_status'];
        }
        if (! $entries) {
            throw ValidationException::withMessages(['csv_file' => 'Include at least one student row.']);
        }

        $rows = [];
        // Chunk lookups to stay within database parameter limits.
        foreach (array_chunk($entries, 500, true) as $chunk) {
            $profiles = StudentProfile::with('user')->whereIn('student_number', array_keys($chunk))->get()->keyBy('student_number');
            foreach ($chunk as $number => $status) {
                $user = $profiles->get($number)?->user;
                $error = ! $user || $user->role !== 'student' ? 'No matching student account; create the account first.' : null;
                if (! $error && $user->student_status_on && $data['effective_on'] < $user->student_status_on) {
                    $error = 'Older than the last confirmed student status; verify the Registrar date.';
                }
                $before = $error ? null : $service->snapshot($user);
                $changes = $error ? [] : $service->changes($user, $status, $data['effective_on']);
                $different = $error ? [] : array_filter($changes, fn ($value, $key) => $value !== ($before[$key] ?? null), ARRAY_FILTER_USE_BOTH);
                $rows[] = ['number' => (string) $number, 'user_id' => $user?->id, 'name' => $user?->list_name ?? 'Unknown Student',
                    'student_status' => $status, 'before' => $before, 'changes' => $changes, 'error' => $error,
                    'result' => $error ? 'Needs Checking' : ($different ? 'Will Update' : 'Unchanged')];
            }
        }
        $batch = StudentStatusBatch::create([
            'created_by' => $request->user()->id, 'academic_period_id' => $data['academic_period_id'],
            'source_reference' => $data['source_reference'], 'effective_on' => $data['effective_on'], 'rows' => $rows,
        ]);

        return redirect()->route('admin.student-statuses.show', $batch);
    }

    public function show(Request $request, StudentStatusBatch $batch)
    {
        $all = collect($batch->rows);
        $page = max(1, $request->integer('page', 1));
        $rows = new LengthAwarePaginator($all->forPage($page, 10)->values(), $all->count(), 10, $page, ['path' => $request->url()]);
        return view('admin.student-statuses.show', ['batch' => $batch->load('period'), 'rows' => $rows, 'counts' => $all->countBy('result')]);
    }

    public function apply(Request $request, StudentStatusBatch $batch, StudentStatusUpdate $service)
    {
        $request->validate(['confirmed' => ['accepted']]);

        try {
            DB::transaction(function () use ($request, $batch, $service) {
                $batch = StudentStatusBatch::lockForUpdate()->findOrFail($batch->id);
                if ($batch->applied_at) { return; }
                $valid = collect($batch->rows)->whereNull('error')->sortBy('user_id');
                if ($valid->isEmpty()) {
                    throw ValidationException::withMessages(['confirmed' => 'No valid student accounts to update. Correct the file and upload again.']);
                }
                foreach ($valid as $row) {
                    $user = User::lockForUpdate()->find($row['user_id']);
                    $profile = StudentProfile::where('student_number', $row['number'])->lockForUpdate()->first();
                    if (! $user || (int) $profile?->user_id !== $user->id || $service->snapshot($user) != $row['before']
                        || $service->changes($user, $row['student_status'], $batch->effective_on->toDateString()) != $row['changes']) {
                        throw ValidationException::withMessages(['confirmed' => 'An account changed after this preview. Nothing was applied. Upload a fresh file and review it again.']);
                    }
                    $user->fill($row['changes']);
                    if ($user->isDirty()) {
                        $user->save();
                        AuditLog::create(['user_id' => $request->user()->id, 'module' => 'users', 'record_id' => $user->id,
                            'action' => 'update', 'old_values' => $row['before'], 'new_values' => $row['changes'],
                            'reason' => "Registrar status batch #{$batch->id}: {$batch->source_reference}",
                            'ip_address' => $request->ip(), 'created_at' => now()]);
                    }
                }
                $batch->update(['applied_at' => now(), 'applied_by' => $request->user()->id]);
                AuditLog::create(['user_id' => $request->user()->id, 'module' => 'student_status_batches', 'record_id' => $batch->id,
                    'action' => 'update', 'new_values' => ['applied' => true, 'academic_period_id' => $batch->academic_period_id,
                        'effective_on' => $batch->effective_on->toDateString(), 'source_reference' => $batch->source_reference],
                    'ip_address' => $request->ip(), 'created_at' => now()]);
            });
        } catch (ValidationException $e) {
            // Surfaced as a toast rather than the $errors bag, since this is a one-shot action
            // result (the checkbox itself is already enforced client-side), not a field error.
            return back()->with('error', $e->validator->errors()->first());
        }

        return back()->with('success', 'Student status updates applied. Entries marked Needs Checking were skipped.');
    }
}
