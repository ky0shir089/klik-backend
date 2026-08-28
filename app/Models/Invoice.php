<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
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
        'updated_by',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'trx_id' => 'integer',
    ];

    // public function resolveRouteBinding($value, $field = null)
    // {
    //     return $this->where($field ?? $this->getRouteKeyName(), $value)
    //         ->where(function ($query) {
    //             $query->where('trx_id', '!=', 3)
    //                 ->orWhere('status', '!=', 'REQUEST');
    //         })
    //         ->firstOrFail();
    // }

    public function supplier_account(): BelongsTo
    {
        return $this->belongsTo(SupplierAccount::class, 'supplier_account_id', 'id');
    }

    public function type_trx(): BelongsTo
    {
        return $this->belongsTo(TypeTrx::class, 'trx_id', 'id');
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'id');
    }

    public function wf_histories(): MorphMany
    {
        return $this->morphMany(WorkflowHistory::class, 'processable')->oldest("sequence");
    }

    public function wf_approval(): MorphOne
    {
        return $this->morphOne(WorkflowApproval::class, 'processable');
    }

    public function settlement(): HasOne
    {
        return $this->hasOne(Settlement::class, 'lpj_invoice_id', 'id');
    }

    public function paid_attachment(): HasMany
    {
        return $this->hasMany(PaidAttachment::class, 'invoice_id', 'id');
    }
}
