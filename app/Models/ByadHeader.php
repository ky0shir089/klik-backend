<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ByadHeader extends Model
{
    protected $table = 'byad_headers';

    protected $fillable = [
        'date',
        'branch',
        'description',
        'file_upload_id',
        'total_unit',
        'total_amount',
        'byad_amount',
        'status',
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

    public function details(): HasMany
    {
        return $this->hasMany(ByadDetail::class, 'byad_id', 'id');
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(FileUpload::class, 'file_upload_id', 'id');
    }
}
