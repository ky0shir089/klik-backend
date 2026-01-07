<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceDetail extends Model
{
    protected $table = 'invoice_details';

    protected $fillable = [
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
}
