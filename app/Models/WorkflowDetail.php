<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowDetail extends Model
{
    protected $table = 'workflow_details';

    protected $fillable = [
        'wf_id',
        'sequence',
        'user_id',
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

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(WorkflowHeader::class, 'wf_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
