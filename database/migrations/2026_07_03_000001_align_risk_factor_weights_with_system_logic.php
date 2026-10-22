<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $factors = [
            'total_offense_count' => ['Total Confirmed Offense Count', 'count', 5, 0.2500, 'Resolved cases with at least one confirmed offense. Capped at five cases.'],
            'recent_violation' => ['Recent Confirmed Violation', 'boolean', null, 0.1500, 'Triggered when a confirmed case was resolved within the previous six months.'],
            'major_offense_count' => ['Major Offense Severity', 'count', 3, 0.2000, 'Resolved major-offense cases. Capped at three cases to represent severity.'],
            'escalation_flag' => ['Escalation Flag', 'boolean', null, 0.1500, 'Triggered by a non-dismissed, non-voided case formally flagged for escalation.'],
            'unresolved_cases' => ['Unresolved Cases', 'count', 3, 0.1000, 'Open cases from initial review through pending resolution. Capped at three cases.'],
            'non_compliant_sanctions' => ['Sanction Non-Compliance', 'count', 3, 0.1500, 'Current sanctions formally marked non-compliant. Capped at three sanctions.'],
        ];

        DB::transaction(function () use ($factors, $now): void {
            DB::table('risk_factor_weights')
                ->whereNotIn('factor_code', array_keys($factors))
                ->update(['is_active' => false, 'updated_at' => $now]);

            foreach ($factors as $code => [$name, $type, $cap, $weight, $description]) {
                DB::table('risk_factor_weights')->updateOrInsert(
                    ['factor_code' => $code],
                    [
                        'factor_name' => $name,
                        'factor_type' => $type,
                        'count_cap' => $cap,
                        'weight' => $weight,
                        'description' => $description,
                        'is_active' => true,
                        'updated_at' => $now,
                    ]
                );
            }
        });
    }

    public function down(): void
    {
        $now = now();
        $legacyFactors = [
            'major_offense_count' => ['Major Offense Count', 'count', 3, 0.3000, 'Number of resolved major offense cases. Capped at 3.'],
            'non_compliant_sanctions' => ['Non-Compliant Sanctions', 'count', 3, 0.2000, 'Number of sanctions currently marked non_compliant. Capped at 3.'],
            'active_major_cases' => ['Active Major Cases', 'count', 2, 0.1500, 'Major cases currently in progress. Capped at 2.'],
            'repeat_same_offense' => ['Repeat of Same Offense', 'count', 3, 0.1000, 'Highest prior offense count across confirmed cases. Capped at 3.'],
            'minor_offense_count' => ['Minor Offense Count', 'count', 4, 0.0700, 'Number of resolved minor offense cases. Capped at 4.'],
            'severe_sanction_received' => ['Severe Sanction Received', 'boolean', null, 0.0800, 'Student has received a Category 3, 4, or 5 sanction.'],
            'habitual_disregard_flag' => ['Habitual Disregard (MAJOR-19)', 'boolean', null, 0.0500, 'Student has been classified under MAJOR-19.'],
            'recent_violation' => ['Recent Violation (Within 6 Months)', 'boolean', null, 0.0500, 'Student has a case opened within the last 6 months.'],
            'violation_velocity' => ['Violation Velocity', 'count', 4, 0.0800, 'Cases opened in the last 12 months. Capped at 4.'],
            'escalation_pattern' => ['Escalation Pattern', 'boolean', null, 0.0500, 'Student has a confirmed minor-to-major escalation.'],
        ];

        DB::transaction(function () use ($legacyFactors, $now): void {
            DB::table('risk_factor_weights')->update(['is_active' => false, 'updated_at' => $now]);

            foreach ($legacyFactors as $code => [$name, $type, $cap, $weight, $description]) {
                DB::table('risk_factor_weights')->updateOrInsert(
                    ['factor_code' => $code],
                    [
                        'factor_name' => $name,
                        'factor_type' => $type,
                        'count_cap' => $cap,
                        'weight' => $weight,
                        'description' => $description,
                        'is_active' => true,
                        'updated_at' => $now,
                    ]
                );
            }
        });
    }
};
