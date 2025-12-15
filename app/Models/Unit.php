<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Unit extends Model
{
    protected $table = 'units';

    protected $fillable = [
        'customer_auction_id',
        'klik_unit_id',
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
        'spp_status',
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
        return $this->BelongsTo(Auction::class, 'auction_id', 'klik_auction_id');
    }

    // public function auction(): HasOneThrough
    // {
    //     return $this->hasOneThrough(Auction::class, AuctionCustomer::class, 'id', 'klik_auction_id', 'customer_auction_id', 'auction_id');
    // }

    public function spp(): HasOne
    {
        return $this->hasOne(Spp::class, 'unit_id', 'klik_unit_id');
    }
}
