<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentHandbook extends Model
{
    protected $fillable = [
        'title',
        'edition',
        'academic_year',
        'effective_date',
        'valid_until',
        'notes',
        'file_path',
        'original_filename',
        'file_size',
        'checksum_sha256',
        'status',
        'uploaded_by',
        'published_by',
        'published_at',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'valid_until' => 'date',
        'file_size' => 'integer',
        'published_at' => 'datetime',
    ];

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopePublishedHistory(Builder $query): Builder
    {
        return $query->whereIn('status', ['published', 'archived']);
    }

    public static function current(): ?self
    {
        return static::query()
            ->with('uploadedBy')
            ->published()
            ->latest('published_at')
            ->first();
    }

    public static function applicableOn(CarbonInterface|string|null $date): ?self
    {
        $query = static::query()->publishedHistory();

        if ($date) {
            $query->where(function (Builder $query) use ($date): void {
                $query->whereNull('effective_date')
                    ->orWhereDate('effective_date', '<=', $date);
            });
        }

        return $query
            ->orderByRaw('CASE WHEN effective_date IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('effective_date')
            ->orderByDesc('published_at')
            ->first();
    }

    public function downloadFilename(): string
    {
        $base = preg_replace('/[^\pL\pN _.-]+/u', '', "{$this->title} {$this->edition}");
        $base = trim((string) preg_replace('/\s+/', ' ', $base), ' .');

        return ($base !== '' ? $base : 'Student Handbook').'.pdf';
    }

    public function editionYear(): ?string
    {
        return preg_match('/^(\d{4}) Edition$/', $this->edition, $matches) === 1
            ? $matches[1]
            : null;
    }

    public function formattedFileSize(): string
    {
        if ($this->file_size >= 1_048_576) {
            return number_format($this->file_size / 1_048_576, 1).' MB';
        }

        return number_format($this->file_size / 1024).' KB';
    }
}
