<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ByadDetail extends Model
{
    protected $table = 'byad_details';

    protected $fillable = [
        'byad_id',
        'unit_id',
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

    public function unit(): HasOne
    {
        return $this->hasOne(Unit::class, 'id', 'unit_id');
    }
}
