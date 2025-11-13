<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierAccount extends Model
{
    protected $table = 'supplier_accounts';

    protected $fillable = [
        'supplier_id',
        'bank_id',
        'account_number',
        'account_name',
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

    public function bank(): BelongsTo
    {
        return $this->BelongsTo(Bank::class, 'bank_id', 'id');
    }

    public function supplier(): BelongsTo
    {
        return $this->BelongsTo(Supplier::class, 'supplier_id', 'id');
    }
}
