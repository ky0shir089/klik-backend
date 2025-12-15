<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Spp extends Model
{
    protected $table = 'spps';

    protected $fillable = [
        'customer_id',
        'branch_name',
        'total_unit',
        'total_amount',
        'status',
        'created_by',
        'updated_by',
        'updated_at',
    ];

    protected $hidden = [
        'created_by',
        'updated_by',
        'updated_at',
    ];

    public function pv(): MorphOne
    {
        return $this->morphOne(PaymentVoucher::class, 'processable', 'processable_type', 'processable_id', 'spp_no');
    }

    // public function customer(): HasOneThrough
    // {
    //     return $this->hasOneThrough(Customer::class, Payment::class, 'id', 'klik_bidder_id', 'payment_id', 'customer_id');
    // }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id', 'id');
    }

    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class, 'klik_bidder_id', 'customer_id');
    }

    public function unit(): HasOne
    {
        return $this->hasOne(Unit::class, 'klik_unit_id', 'unit_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(SppDetail::class, 'spp_id', 'id');
    }
}
