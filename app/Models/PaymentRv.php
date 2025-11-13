<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRv extends Model
{
    protected $table = 'payment_rvs';

    protected $fillable = [
        'payment_id',
        'rv_id',
        'rv_amount',
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

    public function rv(): BelongsTo
    {
        return $this->BelongsTo(RV::class, 'rv_id', 'id');
    }
}
