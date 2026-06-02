<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WorkflowApproval extends Model
{
    protected $table = 'workflow_approvals';

    protected $fillable = [
        'processable_type',
        'processable_id',
        'approve_count',
        'updated_at'
    ];

    public function processable(): MorphTo
    {
        return $this->morphTo();
    }
}
