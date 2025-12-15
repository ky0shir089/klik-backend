<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuctionCustomer extends Model
{
    protected $table = 'auction_customer';

    protected $fillable = [
        'customer_id',
        'auction_id',
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

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class, "auction_customer", "id");
    }

    // public function customers(): BelongsToMany
    // {
    //     return $this->belongsToMany(Customer::class, "auction_customer", "auction_id", "customer_id");
    // }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, "customer_id", "klik_bidder_id");
    }

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class, "auction_id", "klik_auction_id");
    }

    public function classifications(): HasMany
    {
        return $this->hasMany(RvClassification::class, "auction_id", "auction_id");
    }

    public function rvs(): HasMany
    {
        return $this->hasMany(RV::class, "customer_id", "customer_id");
    }
}
