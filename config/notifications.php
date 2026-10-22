<?php

declare(strict_types=1);

return [

    'nte_reminder' => [
        // Kill switch for emailing the NTE deadline reminder, independent of
        // MAIL_MAILER and any cached config. The reminder still shows up in
        // the student's PRISM notification bell either way.
        'mail_enabled' => (bool) env('NTE_REMINDER_MAIL_ENABLED', true),
    ],

];
