<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceExternal extends Model
{
    protected $table = 'invoice_externals';

    protected $fillable = [
        'date',
        'due_date',
        'invoice_external_no',
        'supplier_id',
        'description',
        'total_unit',
        'total_amount_real',
        'total_amount_manual',
        'ppn',
        'pph23',
        'grand_total',
        'signatory',
        'file_upload_id',
        'status',
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

    protected $casts = [
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function attachment()
    {
        return $this->belongsTo(FileUpload::class, 'file_upload_id', 'id');
    }

    public function units()
    {
        return $this->hasMany(InvoiceExternalUnit::class);
    }
}
