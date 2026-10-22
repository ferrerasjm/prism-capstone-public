<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\StudentHandbook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class StudentHandbookController extends Controller
{
    private const MAX_FILE_KILOBYTES = 20_480;

    public function index(): View
    {
        return view('handbooks.index', [
            'currentHandbook' => StudentHandbook::query()
                ->with(['uploadedBy', 'publishedBy'])
                ->published()
                ->latest('published_at')
                ->first(),
            'handbooks' => StudentHandbook::query()
                ->with(['uploadedBy', 'publishedBy'])
                ->orderByRaw("CASE status WHEN 'published' THEN 1 WHEN 'draft' THEN 2 ELSE 3 END")
                ->orderByDesc('effective_date')
                ->orderByDesc('created_at')
                ->get(),
            'routePrefix' => 'coordinator',
            'coordActiveNav' => 'handbooks',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'edition' => $this->editionLabel($request->input('edition_year')),
        ]);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'edition_year' => ['required', 'regex:/^\d{4}$/'],
            'edition' => ['required', Rule::unique('student_handbooks', 'edition')],
            'academic_year' => ['nullable', 'string', 'max:20'],
            'effective_date' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after:effective_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'handbook_file' => [
                'required',
                'file',
                'mimes:pdf',
                'mimetypes:application/pdf',
                'max:'.self::MAX_FILE_KILOBYTES,
            ],
        ], [
            'edition_year.required' => 'Please enter the handbook edition year.',
            'edition_year.regex' => 'The edition year must contain exactly four digits.',
            'edition.unique' => 'A handbook with this edition already exists.',
            'handbook_file.mimes' => 'The handbook must be a PDF file.',
            'handbook_file.mimetypes' => 'The handbook must be a valid PDF file.',
            'handbook_file.max' => 'The handbook PDF must not exceed 20 MB.',
        ]);

        $file = $validated['handbook_file'];
        $stored = $this->storePdf($file);

        try {
            $handbook = DB::transaction(function () use ($request, $validated, $file, $stored): StudentHandbook {
                $handbook = StudentHandbook::create([
                    'title' => $this->clean($validated['title']),
                    'edition' => $this->clean($validated['edition']),
                    'academic_year' => $this->nullableClean($validated['academic_year'] ?? null),
                    'effective_date' => $validated['effective_date'],
                    'valid_until' => $validated['valid_until'] ?? null,
                    'notes' => $this->nullableClean($validated['notes'] ?? null),
                    'file_path' => $stored['path'],
                    'original_filename' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                    'checksum_sha256' => $stored['checksum'],
                    'status' => 'draft',
                    'uploaded_by' => auth()->id(),
                ]);

                $this->audit($request, $handbook, 'create', null, $this->auditValues($handbook));

                return $handbook;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($stored['path']);
            throw $exception;
        }

        return back()->with(
            'success',
            "{$handbook->edition} was uploaded as a draft. Review it before publishing."
        );
    }

    /**
     * Edits a handbook revision.
     *
     * Only draft handbooks are editable; published and archived editions
     * are immutable, because cases already decided under them must stay
     * reproducible.
     */
    public function update(Request $request, StudentHandbook $studentHandbook): RedirectResponse
    {
        if ($studentHandbook->status !== 'draft') {
            return back()->with('error', 'Published and archived handbooks are immutable.');
        }

        $request->merge([
            'edit_edition' => $this->editionLabel($request->input('edit_edition_year')),
        ]);

        $validated = $request->validate([
            'edit_target_id' => ['required', 'integer', Rule::in([$studentHandbook->id])],
            'edit_title' => ['required', 'string', 'max:150'],
            'edit_edition_year' => ['required', 'regex:/^\d{4}$/'],
            'edit_edition' => [
                'required',
                Rule::unique('student_handbooks', 'edition')->ignore($studentHandbook),
            ],
            'edit_academic_year' => ['nullable', 'string', 'max:20'],
            'edit_effective_date' => ['required', 'date'],
            'edit_valid_until' => ['nullable', 'date', 'after:edit_effective_date'],
            'edit_notes' => ['nullable', 'string', 'max:1000'],
            'edit_handbook_file' => [
                'nullable',
                'file',
                'mimes:pdf',
                'mimetypes:application/pdf',
                'max:'.self::MAX_FILE_KILOBYTES,
            ],
        ], [
            'edit_edition_year.required' => 'Please enter the handbook edition year.',
            'edit_edition_year.regex' => 'The edition year must contain exactly four digits.',
            'edit_edition.unique' => 'A handbook with this edition already exists.',
            'edit_handbook_file.mimes' => 'The replacement handbook must be a PDF file.',
            'edit_handbook_file.mimetypes' => 'The replacement handbook must be a valid PDF file.',
            'edit_handbook_file.max' => 'The replacement PDF must not exceed 20 MB.',
        ]);

        $replacement = isset($validated['edit_handbook_file'])
            ? $this->storePdf($validated['edit_handbook_file'])
            : null;
        $oldPath = $studentHandbook->file_path;
        $oldValues = $this->auditValues($studentHandbook);

        $updates = [
            'title' => $this->clean($validated['edit_title']),
            'edition' => $this->clean($validated['edit_edition']),
            'academic_year' => $this->nullableClean($validated['edit_academic_year'] ?? null),
            'effective_date' => $validated['edit_effective_date'],
            'valid_until' => $validated['edit_valid_until'] ?? null,
            'notes' => $this->nullableClean($validated['edit_notes'] ?? null),
        ];

        if ($replacement) {
            /** @var UploadedFile $file */
            $file = $validated['edit_handbook_file'];
            $updates += [
                'file_path' => $replacement['path'],
                'original_filename' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'checksum_sha256' => $replacement['checksum'],
            ];
        }

        try {
            DB::transaction(function () use (
                $request,
                $studentHandbook,
                $updates,
                $oldValues,
                $replacement
            ): void {
                $studentHandbook->update($updates);
                $newValues = $this->auditValues($studentHandbook);
                $newValues['file_replaced'] = (bool) $replacement;
                $this->audit($request, $studentHandbook, 'update', $oldValues, $newValues);
            });
        } catch (Throwable $exception) {
            if ($replacement) {
                Storage::disk('local')->delete($replacement['path']);
            }
            throw $exception;
        }

        if ($replacement) {
            Storage::disk('local')->delete($oldPath);
        }

        return back()->with('success', "{$studentHandbook->edition} draft updated.");
    }

    public function publish(Request $request, StudentHandbook $studentHandbook): RedirectResponse
    {
        if ($studentHandbook->status !== 'draft') {
            return back()->with('error', 'Only draft handbooks can be published.');
        }

        if ($studentHandbook->effective_date?->isFuture()) {
            return back()->with(
                'error',
                'This handbook cannot be published before its effective date.'
            );
        }

        DB::transaction(function () use ($request, $studentHandbook): void {
            $currentHandbooks = StudentHandbook::query()
                ->published()
                ->lockForUpdate()
                ->get();

            foreach ($currentHandbooks as $current) {
                $oldValues = $this->auditValues($current);
                $current->update(['status' => 'archived']);
                $this->audit(
                    $request,
                    $current,
                    'update',
                    $oldValues,
                    $this->auditValues($current),
                    "Replaced by {$studentHandbook->edition}."
                );
            }

            $oldValues = $this->auditValues($studentHandbook);
            $studentHandbook->update([
                'status' => 'published',
                'published_by' => auth()->id(),
                'published_at' => now(),
            ]);
            $this->audit(
                $request,
                $studentHandbook,
                'update',
                $oldValues,
                $this->auditValues($studentHandbook)
            );
        });

        return back()->with(
            'success',
            "{$studentHandbook->edition} is now visible on the student dashboard."
        );
    }

    public function destroyDraft(Request $request, StudentHandbook $studentHandbook): RedirectResponse
    {
        if ($studentHandbook->status !== 'draft') {
            return back()->with('error', 'Published handbook editions cannot be deleted.');
        }

        $validated = $request->validate([
            'delete_target_id' => ['required', 'integer', Rule::in([$studentHandbook->id])],
            'delete_reason' => ['required', 'string', 'min:20', 'max:500'],
        ], [
            'delete_reason.required' => 'Please provide a reason for deleting this draft.',
        ]);

        $path = $studentHandbook->file_path;
        $edition = $studentHandbook->edition;

        DB::transaction(function () use ($request, $studentHandbook, $validated): void {
            $this->audit(
                $request,
                $studentHandbook,
                'void',
                $this->auditValues($studentHandbook),
                null,
                trim($validated['delete_reason'])
            );
            $studentHandbook->delete();
        });

        Storage::disk('local')->delete($path);

        return redirect()
            ->route('coordinator.handbooks.index')
            ->with('success', "{$edition} draft deleted.");
    }

    public function managerDownload(StudentHandbook $studentHandbook): StreamedResponse
    {
        return $this->fileResponse($studentHandbook, true);
    }

    public function studentView(): StreamedResponse
    {
        return $this->fileResponse($this->currentOrFail(), false);
    }

    public function studentDownload(): StreamedResponse
    {
        return $this->fileResponse($this->currentOrFail(), true);
    }

    /**
     * @return array{path: string, checksum: string}
     */
    private function storePdf(UploadedFile $file): array
    {
        $path = 'student-handbooks/'.Str::uuid().'.pdf';
        $checksum = hash_file('sha256', $file->getRealPath());

        if (! $checksum || ! Storage::disk('local')->putFileAs(
            'student-handbooks',
            $file,
            basename($path)
        )) {
            throw ValidationException::withMessages([
                'handbook_file' => 'The PDF could not be stored. Please try again.',
            ]);
        }

        return ['path' => $path, 'checksum' => $checksum];
    }

    private function fileResponse(StudentHandbook $handbook, bool $download): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($handbook->file_path), 404);

        if ($download) {
            return Storage::disk('local')->download(
                $handbook->file_path,
                $handbook->downloadFilename(),
                ['Content-Type' => 'application/pdf']
            );
        }

        return Storage::disk('local')->response(
            $handbook->file_path,
            $handbook->downloadFilename(),
            ['Content-Type' => 'application/pdf'],
            'inline'
        );
    }

    private function currentOrFail(): StudentHandbook
    {
        $handbook = StudentHandbook::current();
        abort_unless($handbook, 404);

        return $handbook;
    }

    private function clean(mixed $value): string
    {
        return preg_replace('/\s+/', ' ', trim((string) $value));
    }

    private function nullableClean(mixed $value): ?string
    {
        $value = $this->clean($value);

        return $value === '' ? null : $value;
    }

    private function editionLabel(mixed $year): string
    {
        return trim((string) $year).' Edition';
    }

    private function auditValues(StudentHandbook $handbook): array
    {
        return [
            'title' => $handbook->title,
            'edition' => $handbook->edition,
            'academic_year' => $handbook->academic_year,
            'effective_date' => $handbook->effective_date?->toDateString(),
            'valid_until' => $handbook->valid_until?->toDateString(),
            'status' => $handbook->status,
            'original_filename' => $handbook->original_filename,
            'file_size' => $handbook->file_size,
            'published_by' => $handbook->published_by,
            'published_at' => $handbook->published_at?->toIso8601String(),
        ];
    }

    private function audit(
        Request $request,
        StudentHandbook $handbook,
        string $action,
        ?array $oldValues,
        ?array $newValues,
        ?string $reason = null
    ): void {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'module' => 'student_handbooks',
            'record_id' => $handbook->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'reason' => $reason,
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);
    }
}
