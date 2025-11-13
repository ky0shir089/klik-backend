<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FileUpload extends Model
{
    protected $table = "file_uploads";

    protected $fillable = [
        'path',
        'filename',
        'extension',
        'created_by',
        'updated_at',
    ];

    protected $hidden = [
        "created_by",
        "updated_by",
        "created_at",
        "updated_at"
    ];
}
