<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Auction extends Model
{
    protected $table = 'auctions';

    protected $fillable = [
        'klik_auction_id',
        'auction_name',
        'auction_date',
        'branch_id',
        'branch_name',
        'total_unit',
        'total_base_price',
        'total_admin_fee',
        'total_final_price',
        'status',
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

    protected $casts = [
        'auction_date' => 'date:Y-m-d',
    ];

    public function unit(): HasOne
    {
        return $this->hasOne(Unit::class, 'auction_id', 'klik_auction_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'klik_bidder_id');
    }

    public function rv_classifications(): HasMany
    {
        return $this->hasMany(RvClassification::class, 'auction_id', 'id');
    }

    // public function customers(): HasMany
    // {
    //     return $this->hasMany(Customer::class, 'customer_id', 'klik_bidder_id');
    // }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, "auction_customer", "auction_id", "customer_id", "klik_auction_id", "klik_bidder_id");
    }

    public function detail(): BelongsTo
    {
        return $this->belongsTo(AuctionCustomer::class, 'klik_auction_id', 'auction_id');
    }
}
