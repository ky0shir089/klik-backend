<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Unit extends Model
{
    protected $table = 'units';

    protected $fillable = [
        'lot_number',
        'police_number',
        'chassis_number',
        'engine_number',
        'contract_number',
        'packge_number',
        'price',
        'admin_fee',
        'final_price',
        'payment_status',
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

    public function auction(): BelongsTo
    {
        return $this->BelongsTo(Auction::class, 'auction_id', 'id');
    }
}
