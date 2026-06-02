<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Settlement extends Model
{
    protected $table = 'settlements';

    protected $fillable = [
        'prepayment_pv_id',
        'lpj_invoice_id',
        'prepayment_amount',
        'lpj_amount',
        'balance',
        'status',
        'created_by',
        'updated_by',
        'updated_at'
    ];

    public function pv(): BelongsTo
    {
        return $this->belongsTo(PaymentVoucher::class, 'prepayment_pv_id', "id");
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'lpj_invoice_id', "id");
    }

    public function byhmd(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'byhmd_invoice_id', "id");
    }
}
