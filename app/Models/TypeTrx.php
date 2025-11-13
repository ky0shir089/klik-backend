<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TypeTrx extends Model
{
    protected $table = 'type_trxes';

    protected $fillable = [
        'code',
        'name',
        'in_out',
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

    public function trx_dtl(): HasMany
    {
        return $this->HasMany(TrxDtl::class, "trx_id", "id");
    }
}
