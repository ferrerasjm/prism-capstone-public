<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Consecutive counseling non-compliance hold threshold
    |--------------------------------------------------------------------------
    |
    | An internal safeguard, not sourced from an official institutional
    | policy: when a student's counseling referrals on the same case come
    | back "incomplete" this many times in a row (no completed referral in
    | between), a registration hold is raised automatically. Adjust or
    | remove this if an official policy value is ever defined.
    |
    */
    'counseling_hold_threshold' => 2,

];
