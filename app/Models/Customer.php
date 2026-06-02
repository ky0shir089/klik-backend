<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Customer extends Model
{
    protected $table = 'customers';

    protected $fillable = [
        'klik_bidder_id',
        'ktp',
        'name',
        'va_number',
        'phone',
        'address',
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

    public function getRouteKeyName(): string
    {
        return 'klik_bidder_id';
    }

    public function auctions(): HasMany
    {
        return $this->hasMany(Auction::class, 'customer_id', 'klik_bidder_id');
    }

    // public function auctions(): BelongsToMany
    // {
    //     return $this->belongsToMany(Auction::class, "auction_customer", "customer_id", "auction_id", 'klik_bidder_id', 'klik_auction_id');
    // }

    public function rvs(): HasMany
    {
        return $this->hasMany(RV::class, 'customer_id', 'klik_bidder_id');
    }

    // public function units(): HasManyThrough
    // {
    //     return $this->HasManyThrough(Unit::class, AuctionCustomer::class, 'customer_id', 'customer_auction_id', 'klik_bidder_id', 'id')->orderBy("id", "asc");
    // }

    public function units(): HasManyThrough
    {
        return $this->hasManyThrough(Unit::class, Auction::class, 'customer_id', 'auction_id', 'klik_bidder_id', 'klik_auction_id');
    }

    public function spps(): HasManyThrough
    {
        return $this->hasManyThrough(Unit::class, Spp::class, 'customer_id', 'klik_unit_id', 'klik_bidder_id', 'unit_id');
    }
}
