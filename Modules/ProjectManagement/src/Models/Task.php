<?php

namespace Modules\ProjectManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\ProjectManagement\Models\Project;
use Illuminate\Database\Eloquent\Relations\BelongsTo; 

class Task extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        // Add your fillable attributes here
        'project_id',
        'assigned_to',
        'title',
        'description',
        'due_date',
        'priority', 
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        // Add your hidden attributes here
        'created_at',
        'updated_at',
        'deleted_at',
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
        'due_date' => 'date',
    ];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        
    }
    public function project() : BelongsTo  //hasManyy
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee(): BelongsTo
    {
         return $this->belongsTo(\App\Models\User::class, 'assigned_to');
}
}