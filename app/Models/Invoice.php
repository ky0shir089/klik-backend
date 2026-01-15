<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Invoice extends Model
{
    protected $table = 'invoices';

    protected $fillable = [
        'date',
        'invoice_no',
        'trx_id',
        'supplier_id',
        'payment_method',
        'supplier_account_id',
        'description',
        'total_amount',
        'file_upload_id',
        'status',
        'signature',
        'pv_id',
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

    public function supplier_account(): BelongsTo
    {
        return $this->belongsTo(SupplierAccount::class, 'supplier_account_id', 'id');
    }

    public function trx_dtl(): BelongsTo
    {
        return $this->belongsTo(TrxDtl::class, 'trx_id', 'id');
    }

    public function pv(): MorphOne
    {
        return $this->morphOne(PaymentVoucher::class, 'processable');
    }

    public function customer(): HasOneThrough
    {
        return $this->hasOneThrough(Supplier::class, SupplierAccount::class, 'supplier_id', 'id', 'supplier_account_id', 'id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(InvoiceDetail::class, 'invoice_id', 'id');
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(FileUpload::class, 'file_upload_id', 'id');
    }
}
