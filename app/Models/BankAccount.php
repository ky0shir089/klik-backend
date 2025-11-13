<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BankAccount extends Model
{
    protected $table = 'bank_accounts';

    protected $fillable = [
        'account_number',
        'account_name',
        'bank_id',
        'coa_id',
        'is_active',
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

    public function coa(): BelongsTo
    {
        return $this->BelongsTo(ChartOfAccount::class, 'coa_id', 'id');
    }
}
