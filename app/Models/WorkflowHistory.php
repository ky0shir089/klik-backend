<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class WorkflowHistory extends Model
{
    protected $table = 'workflow_histories';

    protected $fillable = [
        'wf_id',
        'sequence',
        'processable_type',
        'processable_id',
        'status',
        'user_id',
        'signature',
        'remark',
        'updated_at'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
