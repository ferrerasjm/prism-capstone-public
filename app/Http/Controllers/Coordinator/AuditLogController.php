<?php

declare(strict_types=1);

namespace App\Http\Controllers\Coordinator;

use App\Support\PerPage;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'module' => ['nullable', 'string', 'max:50'],
            'action' => ['nullable', 'string', 'max:30'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $logs = AuditLog::query()
            ->with('user:id,first_name,middle_name,last_name,email,role')
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('module', 'like', "%{$search}%")
                        ->orWhere('record_id', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($user) => $user
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->when($filters['module'] ?? null, fn ($query, $module) => $query->where('module', $module))
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->latest('created_at')
            ->paginate(PerPage::resolve())
            ->withQueryString();

        $logs->getCollection()->transform(function (AuditLog $log): AuditLog {
            $log->setAttribute('safe_old_values', $this->redact($log->old_values));
            $log->setAttribute('safe_new_values', $this->redact($log->new_values));

            return $log;
        });

        return view('coordinator.audit-logs.index', [
            'logs' => $logs,
            'filters' => $filters,
            'modules' => AuditLog::query()->whereNotNull('module')->distinct()->orderBy('module')->pluck('module'),
            'actions' => AuditLog::query()->whereNotNull('action')->distinct()->orderBy('action')->pluck('action'),
            'coordActiveNav' => 'audit',
        ]);
    }

    private function redact(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        foreach ($values as $key => $value) {
            if (preg_match('/password|token|secret|otp|credential/i', (string) $key)) {
                $values[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $values[$key] = $this->redact($value);
            }
        }

        return $values;
    }
}
