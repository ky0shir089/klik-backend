<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceExternalUnit extends Model
{
    protected $table = 'invoice_external_units';

    protected $fillable = [
        'invoice_external_id',
        'unit_id',
        'created_by',
        'updated_by',
        'updated_at',
    ];

    protected $hidden = [
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
    ];

    public function invoiceExternal()
    {
        return $this->belongsTo(InvoiceExternal::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}
