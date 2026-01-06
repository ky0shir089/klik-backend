<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Invoice extends Model
{
    protected $table = 'invoices';

    protected $fillable = [
        'invoice_no',
        'rv_id',
        'supplier_account_id',
        'status',
        'signature',
        'description',
        'amount',
        'inv_coa_id',
        'pv_id',
        'created_by',
        'updated_by',
        'updated_at'
    ];

    protected $hidden = [
        'created_by',
        'updated_by',
        'updated_at',
    ];

    public function supplier_account(): BelongsTo
    {
        return $this->belongsTo(SupplierAccount::class, 'supplier_account_id', 'id');
    }

    public function trx_dtl(): BelongsTo
    {
        return $this->belongsTo(TrxDtl::class, 'inv_coa_id', 'coa_id');
    }

    public function coa(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'inv_coa_id', 'id');
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
