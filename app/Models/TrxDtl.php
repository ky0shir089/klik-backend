<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrxDtl extends Model
{
    protected $table = 'trx_dtls';

    protected $fillable = [
        'trx_id',
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

    public function trx(): BelongsTo
    {
        return $this->BelongsTo(TypeTrx::class, 'trx_id', 'id');
    }

    public function coa(): BelongsTo
    {
        return $this->BelongsTo(ChartOfAccount::class, 'coa_id', 'id');
    }
}
