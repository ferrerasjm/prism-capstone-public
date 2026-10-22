<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Support\PerPage;
use App\Http\Controllers\Controller;
use App\Models\CsvImportLog;
use App\Services\StudentImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class CsvImportController extends Controller
{
    public function __construct(private readonly StudentImportService $importService) {}

    public function index(): View
    {
        $history = CsvImportLog::with('importedBy')
            ->orderByDesc('created_at')
            ->paginate(PerPage::resolve())
            ->withQueryString();

        return view('admin.import.index', [
            'history' => $history,
            'activeNav' => 'import',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'csv_file' => [
                'required',
                'file',
                'mimes:csv,txt',
                'max:5120',
            ],
        ], [
            'csv_file.mimes' => 'The file must be a valid CSV file.',
            'csv_file.max' => 'The file may not be larger than 5MB.',
        ]);

        $result = $this->importService->import(
            $request->file('csv_file'),
            auth()->user()
        );

        /*
         * No rows means no batch was recorded, so there is no results page to send them to.
         * Named route rather than back(): back() falls through to "/" when the browser sends
         * no referer, which would drop the admin on the dashboard mid-task.
         */
        if ($result['empty'] ?? false) {
            return redirect()->route('admin.import.index')->with(
                'error',
                'No student rows were found in that file. Check that it has the header row '
                    .'with at least one student underneath, then upload it again.',
            );
        }

        return redirect()
            ->route('admin.import.show', $result['batch_id'])
            ->with('import_summary', [
                'total' => $result['total'],
                'created' => $result['created'],
                'skipped' => $result['skipped'],
                'errors' => $result['errors'],
                'activation_emails_queued' => $result['activation_emails_queued'],
                'activation_email_failures' => $result['activation_email_failures'],
            ]);
    }

    public function template(): Response
    {
        // Headers only. The worked example lives on the import page instead, where it cannot
        // be mistaken for data or accidentally imported.
        $csvContent = implode(',', StudentImportService::CSV_HEADERS)."\r\n";

        return response($csvContent, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="student-bulk-import-template.csv"',
        ]);
    }

    public function show(string $batchId): View
    {
        $log = CsvImportLog::where('import_batch_id', $batchId)
            ->with('importedBy')
            ->firstOrFail();

        return view('admin.import.show', [
            'log' => $log,
            'rows' => $log->results_json,
            'activeNav' => 'import',
        ]);
    }

    public function download(string $batchId): Response
    {
        $log = CsvImportLog::where('import_batch_id', $batchId)->firstOrFail();

        $rows = $log->results_json;
        $headers = ['Row', 'Student Number', 'Name', 'Official Email', 'Outcome', 'Activation Email', 'Reason'];

        $csvContent = implode(',', $headers)."\n";

        foreach ($rows as $row) {
            $csvContent .= implode(',', [
                $row['row'],
                '"'.($row['student_number'] ?? '').'"',
                '"'.($row['name'] ?? '').'"',
                '"'.($row['email'] ?? '').'"',
                $row['outcome'],
                $row['activation_email'] ?? 'not_sent',
                '"'.str_replace('"', '""', $row['reason'] ?? '').'"',
            ])."\n";
        }

        $filename = 'import-results-'.now()->format('Ymd-His').'.csv';

        return response($csvContent, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
