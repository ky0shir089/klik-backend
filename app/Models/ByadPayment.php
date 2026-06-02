<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class ByadPayment extends Model
{
    protected $table = 'byad_payments';

    protected $fillable = [
        'invoice_id',
        'date',
        'description',
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
        return $this->hasMany(ByadPaymenDetail::class, 'byad_payment_id', 'id');
    }

    public function pv(): MorphOne
    {
        return $this->morphOne(PaymentVoucher::class, 'processable');
    }

    public function customer(): HasOneThrough
    {
        return $this->hasOneThrough(Supplier::class, SupplierAccount::class, 'supplier_id', 'id', 'supplier_account_id', 'id');
    }
}
