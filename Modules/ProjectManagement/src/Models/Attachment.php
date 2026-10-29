<?php

namespace Modules\ProjectManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'attachable_type',
        'attachable_id',
        'uploaded_by',
        'file_name',
        'file_path',
        'file_size',
        'mime_type',
        'disk',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getHumanSizeAttribute(): string
    {
        $bytes = (int) $this->file_size;

        return match (true) {
            $bytes >= 1073741824 => number_format($bytes / 1073741824, 1) . ' GB',
            $bytes >= 1048576    => number_format($bytes / 1048576, 1) . ' MB',
            $bytes >= 1024       => number_format($bytes / 1024, 1) . ' KB',
            default              => $bytes . ' B',
        };
    }

    public function getExtensionAttribute(): string
    {
        return strtolower(pathinfo($this->file_name, PATHINFO_EXTENSION));
    }

    public function getIsImageAttribute(): bool
    {
        if ($this->mime_type && str_starts_with($this->mime_type, 'image/')) {
            return true;
        }

        return in_array($this->extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'avif'], true);
    }

    public function getIsPdfAttribute(): bool
    {
        return $this->mime_type === 'application/pdf' || $this->extension === 'pdf';
    }

    public function getIconAttribute(): string
    {
        if ($this->is_image) {
            return 'image';
        }
        if ($this->is_pdf) {
            return 'picture_as_pdf';
        }

        return match (true) {
            in_array($this->extension, ['doc', 'docx', 'rtf', 'txt', 'md', 'odt'], true) => 'description',
            in_array($this->extension, ['xls', 'xlsx', 'csv', 'ods'], true) => 'table_chart',
            in_array($this->extension, ['ppt', 'pptx', 'odp'], true) => 'slideshow',
            in_array($this->extension, ['zip', 'rar', '7z', 'tar', 'gz', 'bz2'], true) => 'folder_zip',
            in_array($this->extension, ['mp4', 'mkv', 'avi', 'mov', 'webm'], true) => 'movie',
            in_array($this->extension, ['mp3', 'wav', 'ogg', 'm4a', 'flac'], true) => 'audio_file',
            in_array($this->extension, ['json', 'xml', 'yaml', 'yml', 'js', 'ts', 'php', 'html', 'css', 'py', 'sql', 'sh'], true) => 'code',
            default => 'attach_file',
        };
    }

    public function getColorClassesAttribute(): array
    {
        if ($this->is_image) {
            return [
                'bg'     => 'bg-emerald-500/10 dark:bg-emerald-500/20',
                'text'   => 'text-emerald-600 dark:text-emerald-400',
                'border' => 'border-emerald-200 dark:border-emerald-800/40',
            ];
        }

        if ($this->is_pdf) {
            return [
                'bg'     => 'bg-rose-500/10 dark:bg-rose-500/20',
                'text'   => 'text-rose-600 dark:text-rose-400',
                'border' => 'border-rose-200 dark:border-rose-800/40',
            ];
        }

        return match (true) {
            in_array($this->extension, ['xls', 'xlsx', 'csv', 'ods'], true) => [
                'bg'     => 'bg-teal-500/10 dark:bg-teal-500/20',
                'text'   => 'text-teal-600 dark:text-teal-400',
                'border' => 'border-teal-200 dark:border-teal-800/40',
            ],
            in_array($this->extension, ['doc', 'docx', 'rtf', 'txt', 'md', 'odt'], true) => [
                'bg'     => 'bg-blue-500/10 dark:bg-blue-500/20',
                'text'   => 'text-blue-600 dark:text-blue-400',
                'border' => 'border-blue-200 dark:border-blue-800/40',
            ],
            in_array($this->extension, ['zip', 'rar', '7z', 'tar', 'gz', 'bz2'], true) => [
                'bg'     => 'bg-purple-500/10 dark:bg-purple-500/20',
                'text'   => 'text-purple-600 dark:text-purple-400',
                'border' => 'border-purple-200 dark:border-purple-800/40',
            ],
            in_array($this->extension, ['json', 'xml', 'yaml', 'yml', 'js', 'ts', 'php', 'html', 'css', 'py', 'sql', 'sh'], true) => [
                'bg'     => 'bg-indigo-500/10 dark:bg-indigo-500/20',
                'text'   => 'text-indigo-600 dark:text-indigo-400',
                'border' => 'border-indigo-200 dark:border-indigo-800/40',
            ],
            default => [
                'bg'     => 'bg-surface-container-high dark:bg-neutral-800',
                'text'   => 'text-on-surface-variant dark:text-neutral-300',
                'border' => 'border-outline-variant/30 dark:border-neutral-700',
            ],
        };
    }

    public function deletePhysicalFile(): bool
    {
        $diskName = $this->disk ?: 'public';
        if ($this->file_path && Storage::disk($diskName)->exists($this->file_path)) {
            return Storage::disk($diskName)->delete($this->file_path);
        }

        return false;
    }

    protected static function booted()
    {
        static::deleting(fn (Attachment $attachment) => $attachment->deletePhysicalFile());
    }
}
