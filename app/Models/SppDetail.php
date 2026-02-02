<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class SppDetail extends Model
{
    protected $table = 'spp_details';

    protected $fillable = [
        'spp_id',
        'unit_id',
        'created_by',
        'updated_by',
        'updated_at',
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

    public function rvs(): HasOneThrough
    {
        return $this->hasOneThrough(RV::class, RvClassification::class, 'unit_id', 'id', 'unit_id', 'rv_id');
    }

    public function pv(): HasOneThrough
    {
        return $this->HasOneThrough(PaymentVoucher::class, PaymentDetail::class, 'spp_id', 'id', 'spp_id', 'payment_id');
    }
}
