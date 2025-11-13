<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RV extends Model
{
    protected $table = 'receive_vouchers';

    protected $fillable = [
        'rv_no',
        'date',
        'type_trx_id',
        'description',
        'bank_account_id',
        'coa_id',
        'starting_balance',
        'used_balance',
        'ending_balance',
        'status',
        'customer_id',
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

    public function type_trx()
    {
        return $this->belongsTo(TypeTrx::class, 'type_trx_id', 'id');
    }

    public function account()
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id', 'id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }
}
