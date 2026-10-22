<?php

return [
    // Permit an interview walkthrough to advance a fictional UPCC conference.
    // Both checks keep this override confined to the isolated local demo copy.
    'allow_early_upcc_attendance' => env('PRISM_DEMO_ALLOW_EARLY_UPCC_ATTENDANCE', false)
        && env('APP_ENV') === 'local'
        && env('DB_DATABASE') === 'prism_demo_sanitized',
];
