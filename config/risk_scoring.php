<?php

declare(strict_types=1);

return [
    'methodology_version' => '1.0',

    'bands' => [
        'critical' => 76,
        'high' => 51,
        'moderate' => 26,
        'low' => 0,
    ],

    'factors' => [
        'total_offense_count' => [
            'name' => 'Total Confirmed Offense Count',
            'type' => 'count',
            'cap' => 5,
            'weight' => 0.2500,
            'description' => 'Resolved cases with at least one confirmed offense. Capped at five cases.',
        ],
        'recent_violation' => [
            'name' => 'Recent Confirmed Violation',
            'type' => 'boolean',
            'cap' => null,
            'weight' => 0.1500,
            'description' => 'Triggered when a confirmed case was resolved within the previous six months.',
        ],
        'major_offense_count' => [
            'name' => 'Major Offense Severity',
            'type' => 'count',
            'cap' => 3,
            'weight' => 0.2000,
            'description' => 'Resolved major-offense cases. Capped at three cases to represent severity.',
        ],
        'escalation_flag' => [
            'name' => 'Escalation Flag',
            'type' => 'boolean',
            'cap' => null,
            'weight' => 0.1500,
            'description' => 'Triggered by a non-dismissed, non-voided case formally flagged for escalation.',
        ],
        'unresolved_cases' => [
            'name' => 'Unresolved Cases',
            'type' => 'count',
            'cap' => 3,
            'weight' => 0.1000,
            'description' => 'Open cases from initial review through pending resolution. Capped at three cases.',
        ],
        'non_compliant_sanctions' => [
            'name' => 'Sanction Non-Compliance',
            'type' => 'count',
            'cap' => 3,
            'weight' => 0.1500,
            'description' => 'Non-compliant sanctions or evaluations needing further intervention. Capped at three sanctions.',
        ],
    ],

    // Supported measurements that may be added by a Super Admin but are not
    // enabled in the default six-factor methodology.
    'available_factors' => [
        'repeat_offender_flag' => [
            'name' => 'Repeat Offender Flag',
            'type' => 'boolean',
            'cap' => null,
            'weight' => 0,
            'description' => 'Triggered when the student has at least two resolved cases with a confirmed offense.',
        ],
    ],
];
