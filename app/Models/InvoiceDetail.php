<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class InvoiceDetail extends Model
{
    protected $table = 'invoice_details';

    protected $fillable = [
        'id',
        'invoice_id',
        'inv_coa_id',
        'description',
        'item_amount',
        'pph_id',
        'pph_amount',
        'ppn_rate',
        'ppn_amount',
        'rv_id',
        'total_amount',
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

    public function coa(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'inv_coa_id', 'id');
    }

    public function pph(): BelongsTo
    {
        return $this->belongsTo(Pph::class, 'pph_id', 'id');
    }

    public function rv(): BelongsTo
    {
        return $this->belongsTo(RV::class, 'rv_id', 'id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id', 'id');
    }

    public function mit(): MorphOne
    {
        return $this->morphOne(RV::class, 'invoiceable');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }
}
