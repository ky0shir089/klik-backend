<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Payment extends Model
{
    protected $table = 'payments';

    protected $fillable = [
        'payment_date',
        'branch_id',
        'branch_name',
        'customer_id',
        'total_unit',
        'total_amount',
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

    public function pv(): MorphOne
    {
        return $this->morphOne(PaymentVoucher::class, 'processable');
    }

    public function units(): HasMany
    {
        return $this->HasMany(PaymentDetail::class, 'payment_id', 'id');
    }

    public function rvs(): HasMany
    {
        return $this->HasMany(PaymentRv::class, 'payment_id', 'id');
    }

    public function customer(): BelongsTo
    {
        return $this->BelongsTo(Customer::class, 'customer_id', 'klik_bidder_id');
    }

    public function rv(): HasOneThrough
    {
        return $this->HasOneThrough(ReceiveVoucher::class, PaymentRv::class, 'payment_id', 'id', 'id', 'rv_id');
    }
}
