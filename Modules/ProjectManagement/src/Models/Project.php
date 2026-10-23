<?php

namespace Modules\ProjectManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\ProjectManagement\Models\Attachment;
use Modules\ProjectManagement\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        // Add your fillable attributes here
        'name',
        'description',
        'start_date',
        'end_date',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        // Add your hidden attributes here
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        // Add your casts here
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        
    }
    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest();
    }

    /** Projects are visible to members only when they have an assigned task in them. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->can('tasks.assign')
            ? $query
            : $query->whereHas('tasks', fn (Builder $tasks) => $tasks->where('assigned_to', $user->id));
    }
}
