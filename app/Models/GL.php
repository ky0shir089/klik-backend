<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GL extends Model
{
    protected $table = 'general_ledgers';

    protected $fillable = [
        'gl_no',
        'date',
        'type',
        'description',
        'coa_id',
        'debit',
        'credit',
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
}
