<?php

$settings = [
    'offices' => [
        'Facilities Management Office' => 'Facilities Management Office',
        'Learning Resource Center' => 'Learning Resource Center',
        'Health Services Offices' => 'Health Services Offices',
        "Registrar's Office" => "Registrar's Office",
        'Student Discipline Office' => 'Student Discipline Office',
        'Alumni Affairs Office' => 'Alumni Affairs Office',
        'Student Development and Activities Office' => 'Student Development and Activities Office',
        'Asset Management Office' => 'Asset Management Office',
    ],

    /*
    |--------------------------------------------------------------------------
    | University Service contact directory
    |--------------------------------------------------------------------------
    |
    | These contacts are assignment snapshots, not PRISM user accounts. The
    | stable keys are submitted by the form; names and email addresses are
    | always resolved from this server-side directory.
    |
    */
    'contacts' => [
        'Facilities Management Office' => [
            'facilities-contact' => [
                'name' => 'Sir Facilities Contact',
                'email' => null,
            ],
        ],
        'Learning Resource Center' => [
            'library-contact' => [
                'name' => "Ma'am Library Contact",
                'email' => null,
            ],
        ],
        'Health Services Offices' => [
            'health-contact' => [
                'name' => 'Nurse Health Services Contact',
                'email' => null,
            ],
        ],
        "Registrar's Office" => [
            'registrar-contact' => [
                'name' => 'Mr. Registrar Contact',
                'email' => null,
            ],
        ],
        'Student Discipline Office' => [
            'discipline-contact' => [
                'name' => "Designated Discipline Office Staff",
                'email' => null,
            ],
        ],
        'Alumni Affairs Office' => [
            'alumni-contact' => [
                'name' => "Ma'am Alumni Contact",
                'email' => null,
            ],
        ],
        'Student Development and Activities Office' => [
            'student-development-contact' => [
                'name' => "Ma'am Student Development Contact",
                'email' => null,
            ],
        ],
        'Asset Management Office' => [
            'asset-management-contact' => [
                'name' => 'Sir Asset Management Contact',
                'email' => null,
            ],
        ],
    ],
];

// Keep the operational directory (including existing form keys) private.
// An empty override disables the private file, as used by the test environment.
$privateContactsFile = env('PRISM_US_CONTACTS_FILE', storage_path('app/private/university-service-contacts.php'));

if (is_string($privateContactsFile) && $privateContactsFile !== '' && is_file($privateContactsFile)) {
    $privateDirectory = require $privateContactsFile;

    if (! is_array($privateDirectory)) {
        throw new \UnexpectedValueException('The private University Service directory must return an array.');
    }

    $settings['contacts'] = array_replace($settings['contacts'], $privateDirectory['contacts'] ?? $privateDirectory);
}

return $settings;
