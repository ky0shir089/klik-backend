<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Xendit extends Model
{
    protected $table = 'xendits';

    protected $fillable = [
        'date',
        'reference_id',
        'withdrawal_amount',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $hidden = [
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
    ];
}
