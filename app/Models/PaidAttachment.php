<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaidAttachment extends Model
{
    protected $table = "paid_attachments";

    protected $fillable = [
        "invoice_id",
        "file_upload_id",
        "created_by",
        "updated_by",
    ];

    protected $hidden = [
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
    ];

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(FileUpload::class, 'file_upload_id', 'id');
    }
}
