<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class File extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'project_id',
        'bucket',
        'path',
        'original_name',
        'mime_type',
        'size',
        'extension',
        'url',
        'metadata',
        'disk',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'size' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'image/');
    }

    public function isPublic(): bool
    {
        $publicBuckets = config('supabase.storage.public_buckets', []);

        return in_array($this->bucket, $publicBuckets, true);
    }

    public function getFullPathAttribute(): string
    {
        return "{$this->bucket}/{$this->path}";
    }

    public function getFormattedSizeAttribute(): string
    {
        $size = $this->size ?? 0;

        if ($size < 1024) {
            return "{$size} B";
        }

        if ($size < 1024 * 1024) {
            return round($size / 1024, 1).' KB';
        }

        return round($size / (1024 * 1024), 1).' MB';
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if ($this->isImage()) {
            return $this->url;
        }

        return null;
    }

    public function scopeByBucket(Builder $query, string $bucket): Builder
    {
        return $query->where('bucket', $bucket);
    }

    public function scopeByUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByType(Builder $query, string $mimeType): Builder
    {
        return $query->where('mime_type', 'like', "{$mimeType}%");
    }

    public function scopeImages(Builder $query): Builder
    {
        return $query->where('mime_type', 'like', 'image/%');
    }

    public function scopeDocuments(Builder $query): Builder
    {
        return $query->whereIn('mime_type', [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain',
            'text/csv',
            'application/json',
        ]);
    }

    public function scopeRecent(Builder $query, int $limit = 20): Builder
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }
}
