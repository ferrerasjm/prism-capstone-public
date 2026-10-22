<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CsvImportLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'import_batch_id',
        'imported_by',
        'file_name',
        'total_rows',
        'created_count',
        'skipped_count',
        'error_count',
        'results_json',
        'created_at',
    ];

    protected $casts = [
        'results_json' => 'array',
        'created_at' => 'datetime',
    ];

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
