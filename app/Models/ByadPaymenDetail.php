<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ByadPaymenDetail extends Model
{
    protected $table = 'byad_payment_details';

    protected $fillable = [
        'byad_id',
        'unit_id',
        'amount',
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

    public function byad(): HasOne
    {
        return $this->hasOne(ByadHeader::class, 'id', 'byad_id');
    }
}
