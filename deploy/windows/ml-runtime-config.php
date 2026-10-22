<?php

// Read the same effective (possibly cached) configuration as Laravel.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo json_encode(config('offense_classification'), JSON_THROW_ON_ERROR);
