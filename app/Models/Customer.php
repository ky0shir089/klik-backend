<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Customer extends Model
{
    protected $table = 'customers';

    protected $fillable = [
        'klik_bidder_id',
        'ktp',
        'name',
        'branch_id',
        'branch_name',
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

    public function rvs(): HasMany
    {
        return $this->hasMany(RV::class, 'customer_id', 'klik_bidder_id');
    }

    public function units(): HasManyThrough
    {
        return $this->HasManyThrough(Unit::class, Auction::class, 'customer_id', 'auction_id', 'klik_bidder_id', 'id')->orderBy("id", "asc");
    }
}
