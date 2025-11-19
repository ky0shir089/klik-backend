<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PaymentVoucher extends Model
{
    protected $table = 'payment_vouchers';

    protected $fillable = [
        'pv_no',
        'description',
        'bank_account_id',
        'supplier_id',
        'supplier_account_id',
        'processable_type',
        'processable_id',
        'pv_amount',
        'rv_amount',
        'rv_balance',
        'status',
        'paid_date',
        'trx_dtl_id',
        'created_by',
        'updated_by',
        'updated_at',
        'pvs'
    ];

    protected $hidden = [
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
    ];

    public function processable(): MorphTo
    {
        return $this->morphTo();
    }

    public function supplier(): BelongsTo
    {
        return $this->BelongsTo(Supplier::class, 'supplier_id', 'id');
    }

    public function supplier_account(): BelongsTo
    {
        return $this->BelongsTo(SupplierAccount::class, 'supplier_account_id', 'id');
    }

    public function trx_dtl(): BelongsTo
    {
        return $this->BelongsTo(TrxDtl::class, 'trx_dtl_id', 'id');
    }

    public function bank_account(): BelongsTo
    {
        return $this->BelongsTo(BankAccount::class, 'bank_account_id', 'id');
    }
}
