<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AcademicProgram;
use App\Models\AcademicUnit;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Builds and publishes dashboard announcements, including the ones derived
 * automatically from audit events. Also groups announcements for display
 * per recipient.
 */
class AnnouncementService
{
    private const NON_ADMIN_ROLES = Announcement::RECIPIENT_ROLES;

    public function publishFromAudit(AuditLog $audit): void
    {
        $actor = $audit->user()->first();

        if (! $actor || $actor->role !== 'super_admin') {
            return;
        }

        if ($audit->module === 'auth') {
            return;
        }

        if ($audit->module === 'users') {
            if ($audit->action === 'update' && ! $this->hasMeaningfulChanges($audit)) {
                return;
            }

            $this->publishAccountChange($audit, $actor);

            return;
        }

        if (! in_array($audit->module, [
            'academic_periods',
            'academic_units',
            'academic_programs',
            'risk_factor_weights',
        ], true)) {
            return;
        }

        if ($audit->action === 'update' && ! $this->hasMeaningfulChanges($audit)) {
            return;
        }

        [$title, $message, $roles] = match ($audit->module) {
            'academic_periods' => [
                'Academic term updated',
                $this->academicPeriodMessage($audit),
                self::NON_ADMIN_ROLES,
            ],
            'academic_units', 'academic_programs' => [
                $this->academicCatalogTitle($audit),
                $this->academicCatalogMessage($audit),
                self::NON_ADMIN_ROLES,
            ],
            'risk_factor_weights' => [
                'Risk-scoring settings updated',
                $this->changeSentence(
                    $audit,
                    'Risk-scoring settings were updated.'
                ),
                ['discipline_coordinator', 'discipline_officer'],
            ],
            default => throw new \LogicException('Unsupported announcement audit module.'),
        };

        $academicUnitIds = in_array($audit->module, ['academic_units', 'academic_programs'], true)
            ? $this->affectedAcademicUnitIds($audit)
            : [];

        $this->publishToRoles(
            $roles,
            $actor,
            $title,
            $message,
            (string) $audit->module,
            $academicUnitIds
        );
    }

    /**
     * Groups announcements by date, drops no-op "changed from X to X" entries,
     * and flags `personal` messages that are private to the viewing user.
     */
    public function displayGroups(Collection $announcements): Collection
    {
        return $announcements
            ->groupBy(fn (Announcement $announcement) => $announcement->created_at->toDateString())
            ->map(function (Collection $announcements) {
                $items = $announcements
                    ->map(function (Announcement $announcement) {
                        $message = trim($announcement->message);

                        if (preg_match('/^The System Administrator changed (.+?) from (.+)$/', $message, $parts)) {
                            $message = ucfirst($parts[1]).' changed from '.$parts[2];
                        } elseif (str_starts_with($message, 'The System Administrator changed ')) {
                            $legacyChange = substr($message, strlen('The System Administrator changed '));

                            $message = $legacyChange === 'an academic department or program.'
                                ? 'Academic department or program information was updated.'
                                : ucfirst($legacyChange);
                        }

                        $message = str_replace(
                            ['The term name changed', 'The department name changed', 'The program name changed'],
                            ['The academic term changed', 'The academic department name changed', 'The academic program name changed'],
                            $message
                        );

                        return [
                            'message' => $message,
                            'personal' => $announcement->target_user_id !== null,
                            'time' => $announcement->created_at->format('g:i A'),
                        ];
                    })
                    ->filter(function (array $item) {
                        $message = $item['message'];

                        if (in_array($message, [
                            'Academic department or program information was updated.',
                            'Academic department or program information was changed.',
                        ], true) || str_starts_with($message, 'The academic department changed from ')) {
                            return false;
                        }

                        if (preg_match_all('/changed from "([^"]*)" to "([^"]*)"/', $message, $matches, PREG_SET_ORDER)) {
                            $hasActualChange = collect($matches)->contains(function ($match) {
                                $before = preg_replace('/\s+/', ' ', trim($match[1]));
                                $after = preg_replace('/\s+/', ' ', trim($match[2]));

                                return $before !== $after;
                            });

                            if (! $hasActualChange) {
                                return false;
                            }
                        }

                        return true;
                    })
                    ->unique(fn (array $item) => mb_strtolower(preg_replace('/\s+/', ' ', trim($item['message']))))
                    ->values();

                return [
                    'date' => $announcements->first()->created_at,
                    'messages' => $items,
                ];
            })
            ->filter(fn (array $group) => $group['messages']->isNotEmpty());
    }

    public function publishToUser(
        User $target,
        User $actor,
        string $title,
        string $message,
        string $kind = 'account_update'
    ): ?Announcement {
        if ($target->role === 'super_admin') {
            return null;
        }

        return Announcement::create([
            'created_by' => $actor->id,
            'target_user_id' => $target->id,
            'kind' => $kind,
            'title' => $title,
            'message' => $message,
        ]);
    }

    private function publishAccountChange(AuditLog $audit, User $actor): void
    {
        if (! $audit->record_id) {
            return;
        }

        $target = User::find($audit->record_id);

        if (! $target) {
            return;
        }

        $created = $audit->module === 'users' && $audit->action === 'create';
        $title = $created ? 'Your account was created' : 'Your account information was updated';
        $message = $created
            ? 'Your PRISM account was created.'
            : $this->changeSentence(
                $audit,
                'Your account information was updated.',
                'your'
            );

        $this->publishToUser($target, $actor, $title, $message);
    }

    private function publishToRoles(
        array $roles,
        User $actor,
        string $title,
        string $message,
        string $kind,
        array $academicUnitIds = []
    ): void {
        foreach (array_unique($roles) as $role) {
            $targets = $role === 'discipline_officer' && $academicUnitIds !== []
                ? array_values(array_unique($academicUnitIds))
                : [null];

            foreach ($targets as $academicUnitId) {
                Announcement::create([
                    'created_by' => $actor->id,
                    'target_role' => $role,
                    'target_academic_unit_id' => $academicUnitId,
                    'kind' => $kind,
                    'title' => $title,
                    'message' => $message,
                ]);
            }
        }
    }

    private function affectedAcademicUnitIds(AuditLog $audit): array
    {
        if ($audit->module === 'academic_units') {
            return $audit->record_id ? [(int) $audit->record_id] : [];
        }

        $oldValues = $audit->old_values ?? [];
        $newValues = $audit->new_values ?? [];
        $ids = [
            $oldValues['academic_unit_id'] ?? null,
            $newValues['academic_unit_id'] ?? null,
        ];

        if (array_filter($ids) === [] && $audit->record_id) {
            $ids[] = AcademicProgram::find($audit->record_id)?->academic_unit_id;
        }

        return array_values(array_unique(array_map(
            static fn (mixed $id): int => (int) $id,
            array_filter($ids)
        )));
    }

    private function changeSentence(
        AuditLog $audit,
        string $fallback,
        string $determiner = 'the',
        ?string $subject = null
    ): string {
        $oldValues = $audit->old_values ?? [];
        $newValues = $audit->new_values ?? [];

        if ($audit->module === 'risk_factor_weights' && isset($newValues['weights'])) {
            $newValues = $newValues['weights'];
        }

        $oldValues = Arr::dot($oldValues);
        $newValues = Arr::dot($newValues);
        $changes = [];

        foreach (array_intersect(array_keys($oldValues), array_keys($newValues)) as $key) {
            if ($this->valuesEqual($oldValues[$key], $newValues[$key])) {
                continue;
            }

            $changes[] = sprintf(
                '%s %s changed from %s to %s',
                $determiner,
                $this->fieldLabel($key, $audit),
                $this->formatValue($key, $oldValues[$key]),
                $this->formatValue($key, $newValues[$key]),
            );
        }

        if ($changes === []) {
            return $subject && ! str_contains($fallback, $subject)
                ? "For {$subject}, ".lcfirst($fallback)
                : $fallback;
        }

        $sentence = ucfirst($this->joinChanges($changes)).'.';

        return $subject && ! str_contains($sentence, $subject)
            ? "For {$subject}, ".lcfirst($sentence)
            : $sentence;
    }

    private function academicPeriodMessage(AuditLog $audit): string
    {
        $oldValues = $audit->old_values ?? [];
        $newValues = $audit->new_values ?? [];
        $term = $newValues['term_name'] ?? $oldValues['term_name'] ?? null;
        $year = $newValues['academic_year'] ?? $oldValues['academic_year'] ?? null;
        $period = $term && $year ? "{$term} for Academic Year {$year}" : null;

        if ($audit->action === 'create') {
            return $period
                ? "The {$period} was added."
                : 'A new academic term was added.';
        }

        if (array_key_exists('is_active', $oldValues)
            && array_key_exists('is_active', $newValues)
            && ! $this->valuesEqual($oldValues['is_active'], $newValues['is_active'])) {
            $action = $newValues['is_active'] ? 'restored' : 'archived';

            return $period
                ? "The {$period} was {$action}."
                : "An academic term was {$action}.";
        }

        return $this->changeSentence($audit, 'Academic term information was updated.', 'the', $period);
    }

    private function academicCatalogMessage(AuditLog $audit): string
    {
        $oldValues = $audit->old_values ?? [];
        $newValues = $audit->new_values ?? [];
        $isDepartment = $audit->module === 'academic_units';
        $recordName = $newValues['name'] ?? $oldValues['name'] ?? null;
        $recordLabel = $isDepartment ? 'academic department' : 'academic program';

        if ($audit->action === 'create') {
            if (! $isDepartment && isset($newValues['academic_unit_id'])) {
                $department = AcademicUnit::find($newValues['academic_unit_id'])?->name;

                if ($recordName && $department) {
                    return "The academic program \"{$recordName}\" was added under \"{$department}\".";
                }
            }

            return $recordName
                ? "The {$recordLabel} \"{$recordName}\" was added."
                : ucfirst("a new {$recordLabel} was added.");
        }

        if (array_key_exists('is_active', $oldValues)
            && array_key_exists('is_active', $newValues)
            && ! $this->valuesEqual($oldValues['is_active'], $newValues['is_active'])) {
            $action = $newValues['is_active'] ? 'restored' : 'archived';

            return $recordName
                ? "The {$recordLabel} \"{$recordName}\" was {$action}."
                : ucfirst("the {$recordLabel} was {$action}.");
        }

        $nameChanged = array_key_exists('name', $oldValues)
            && array_key_exists('name', $newValues)
            && ! $this->valuesEqual($oldValues['name'], $newValues['name']);
        $departmentChanged = array_key_exists('academic_unit_id', $oldValues)
            && array_key_exists('academic_unit_id', $newValues)
            && ! $this->valuesEqual($oldValues['academic_unit_id'], $newValues['academic_unit_id']);

        if ($nameChanged && ! $departmentChanged) {
            return sprintf(
                'The %s was renamed from %s to %s.',
                $recordLabel,
                $this->formatValue('name', $oldValues['name']),
                $this->formatValue('name', $newValues['name']),
            );
        }

        return $this->changeSentence(
            $audit,
            ucfirst("{$recordLabel} information was updated."),
            'the',
            $recordName
        );
    }

    private function hasMeaningfulChanges(AuditLog $audit): bool
    {
        $oldValues = $audit->old_values ?? [];
        $newValues = $audit->new_values ?? [];

        if ($audit->module === 'risk_factor_weights' && isset($newValues['weights'])) {
            $newValues = $newValues['weights'];
        }

        $oldValues = Arr::dot($oldValues);
        $newValues = Arr::dot($newValues);

        foreach (array_intersect(array_keys($oldValues), array_keys($newValues)) as $key) {
            if (! $this->valuesEqual($oldValues[$key], $newValues[$key])) {
                return true;
            }
        }

        return false;
    }

    private function valuesEqual(mixed $oldValue, mixed $newValue): bool
    {
        if (is_string($oldValue) && is_string($newValue)) {
            $oldValue = preg_replace('/\s+/', ' ', trim($oldValue));
            $newValue = preg_replace('/\s+/', ' ', trim($newValue));
        }

        return $oldValue == $newValue;
    }

    private function academicCatalogTitle(AuditLog $audit): string
    {
        $oldValues = $audit->old_values ?? [];
        $newValues = $audit->new_values ?? [];
        $recordType = $audit->module === 'academic_units' ? 'Department' : 'Program';

        if ($audit->action === 'create') {
            return $recordType.' added';
        }

        if (array_key_exists('is_active', $oldValues)
            && array_key_exists('is_active', $newValues)
            && $oldValues['is_active'] != $newValues['is_active']) {
            return $recordType.($newValues['is_active'] ? ' restored' : ' archived');
        }

        if ($audit->module === 'academic_programs') {
            if (($oldValues['academic_unit_id'] ?? null) != ($newValues['academic_unit_id'] ?? null)) {
                return 'Program department changed';
            }

            if (($oldValues['name'] ?? null) != ($newValues['name'] ?? null)) {
                return 'Program renamed';
            }
        }

        if ($audit->module === 'academic_units'
            && ($oldValues['name'] ?? null) != ($newValues['name'] ?? null)) {
            return 'Department renamed';
        }

        return 'Academic catalog updated';
    }

    private function fieldLabel(string $key, AuditLog $audit): string
    {
        $field = Str::afterLast($key, '.');

        if ($field === 'academic_unit_id' && $audit->module === 'academic_programs') {
            $programName = $audit->new_values['name']
                ?? $audit->old_values['name']
                ?? AcademicProgram::find($audit->record_id)?->name;

            return $programName
                ? 'academic department for '.$programName
                : 'academic department for the affected program';
        }

        return match ($field) {
            'academic_unit_id' => 'academic department',
            'academic_year' => 'academic year',
            'count_cap' => Str::lower(Str::headline(Str::beforeLast($key, '.'))).' cap',
            'ends_on' => 'end date',
            'first_name' => 'first name',
            'institutional_id' => 'University ID',
            'is_active' => 'status',
            'last_name' => 'last name',
            'middle_name' => 'middle name',
            'office_name' => 'office or affiliation',
            'starts_on' => 'start date',
            'term_name' => 'academic term',
            'weight' => Str::lower(Str::headline(Str::beforeLast($key, '.'))).' weight',
            'name' => $audit->module === 'academic_units' ? 'academic department name' : 'academic program name',
            default => Str::lower(Str::headline($field)),
        };
    }

    private function formatValue(string $key, mixed $value): string
    {
        $field = Str::afterLast($key, '.');

        if ($value === null || $value === '') {
            return '"not set"';
        }

        if ($field === 'academic_unit_id') {
            $value = AcademicUnit::find($value)?->name ?? "department #{$value}";
        } elseif ($field === 'is_active') {
            $value = (bool) $value ? 'active' : 'inactive';
        } elseif (in_array($field, ['role', 'status'], true)) {
            $value = Str::headline((string) $value);
        } elseif (is_bool($value)) {
            $value = $value ? 'yes' : 'no';
        }

        return '"'.(string) $value.'"';
    }

    private function joinChanges(array $changes): string
    {
        if (count($changes) === 1) {
            return $changes[0];
        }

        if (count($changes) === 2) {
            return implode(' and ', $changes);
        }

        $last = array_pop($changes);

        return implode(', ', $changes).', and '.$last;
    }
}
