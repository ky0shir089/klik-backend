<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WorkflowHeader extends Model
{
    protected $table = 'workflow_headers';

    protected $fillable = [
        'name',
        'type_trx',
        'min_amount',
        'max_amount',
        'is_active',
        'created_by',
        'updated_by',
        'updated_at'
    ];

    protected $hidden = [
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'type_trx' => 'array',
    ];

    public function processable(): MorphTo
    {
        return $this->morphTo();
    }
    
    public function details(): HasMany
    {
        return $this->hasMany(WorkflowDetail::class, 'wf_id', 'id');
    }
}
