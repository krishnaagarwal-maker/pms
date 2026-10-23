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

    /**
     * Get the owning attachable model (Project or Task).
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The user who uploaded the attachment.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Human-readable file size (e.g., 2.4 MB, 320 KB).
     */
    public function getHumanSizeAttribute(): string
    {
        $bytes = (int) $this->file_size;

        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 1) . ' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }

    /**
     * Normalized file extension.
     */
    public function getExtensionAttribute(): string
    {
        return strtolower(pathinfo($this->file_name, PATHINFO_EXTENSION));
    }

    /**
     * Determine if the file is an image.
     */
    public function getIsImageAttribute(): bool
    {
        if ($this->mime_type && str_starts_with($this->mime_type, 'image/')) {
            return true;
        }

        return in_array($this->extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'avif']);
    }

    /**
     * Determine if the file is a PDF.
     */
    public function getIsPdfAttribute(): bool
    {
        return $this->mime_type === 'application/pdf' || $this->extension === 'pdf';
    }

    /**
     * Material symbol icon name for the file type.
     */
    public function getIconAttribute(): string
    {
        if ($this->is_image) {
            return 'image';
        }
        if ($this->is_pdf) {
            return 'picture_as_pdf';
        }
        if (in_array($this->extension, ['doc', 'docx', 'rtf', 'txt', 'md', 'odt'])) {
            return 'description';
        }
        if (in_array($this->extension, ['xls', 'xlsx', 'csv', 'ods'])) {
            return 'table_chart';
        }
        if (in_array($this->extension, ['ppt', 'pptx', 'odp'])) {
            return 'slideshow';
        }
        if (in_array($this->extension, ['zip', 'rar', '7z', 'tar', 'gz', 'bz2'])) {
            return 'folder_zip';
        }
        if (in_array($this->extension, ['mp4', 'mkv', 'avi', 'mov', 'webm'])) {
            return 'movie';
        }
        if (in_array($this->extension, ['mp3', 'wav', 'ogg', 'm4a', 'flac'])) {
            return 'audio_file';
        }
        if (in_array($this->extension, ['json', 'xml', 'yaml', 'yml', 'js', 'ts', 'php', 'html', 'css', 'py', 'sql', 'sh'])) {
            return 'code';
        }

        return 'attach_file';
    }

    /**
     * Color scheme for the file badge and icon based on file category.
     */
    public function getColorClassesAttribute(): array
    {
        if ($this->is_image) {
            return [
                'bg' => 'bg-emerald-500/10 dark:bg-emerald-500/20',
                'text' => 'text-emerald-600 dark:text-emerald-400',
                'border' => 'border-emerald-200 dark:border-emerald-800/40',
            ];
        }
        if ($this->is_pdf) {
            return [
                'bg' => 'bg-rose-500/10 dark:bg-rose-500/20',
                'text' => 'text-rose-600 dark:text-rose-400',
                'border' => 'border-rose-200 dark:border-rose-800/40',
            ];
        }
        if (in_array($this->extension, ['xls', 'xlsx', 'csv', 'ods'])) {
            return [
                'bg' => 'bg-teal-500/10 dark:bg-teal-500/20',
                'text' => 'text-teal-600 dark:text-teal-400',
                'border' => 'border-teal-200 dark:border-teal-800/40',
            ];
        }
        if (in_array($this->extension, ['doc', 'docx', 'rtf', 'txt', 'md', 'odt'])) {
            return [
                'bg' => 'bg-blue-500/10 dark:bg-blue-500/20',
                'text' => 'text-blue-600 dark:text-blue-400',
                'border' => 'border-blue-200 dark:border-blue-800/40',
            ];
        }
        if (in_array($this->extension, ['zip', 'rar', '7z', 'tar', 'gz', 'bz2'])) {
            return [
                'bg' => 'bg-purple-500/10 dark:bg-purple-500/20',
                'text' => 'text-purple-600 dark:text-purple-400',
                'border' => 'border-purple-200 dark:border-purple-800/40',
            ];
        }
        if (in_array($this->extension, ['json', 'xml', 'yaml', 'yml', 'js', 'ts', 'php', 'html', 'css', 'py', 'sql', 'sh'])) {
            return [
                'bg' => 'bg-indigo-500/10 dark:bg-indigo-500/20',
                'text' => 'text-indigo-600 dark:text-indigo-400',
                'border' => 'border-indigo-200 dark:border-indigo-800/40',
            ];
        }

        return [
            'bg' => 'bg-surface-container-high dark:bg-neutral-800',
            'text' => 'text-on-surface-variant dark:text-neutral-300',
            'border' => 'border-outline-variant/30 dark:border-neutral-700',
        ];
    }

    /**
     * Delete underlying physical file from disk.
     */
    public function deletePhysicalFile(): bool
    {
        $diskName = $this->disk ?: 'public';
        if ($this->file_path && Storage::disk($diskName)->exists($this->file_path)) {
            return Storage::disk($diskName)->delete($this->file_path);
        }

        return false;
    }

    /**
     * Boot model events.
     */
    protected static function booted()
    {
        static::deleting(function (Attachment $attachment) {
            $attachment->deletePhysicalFile();
        });
    }
}
