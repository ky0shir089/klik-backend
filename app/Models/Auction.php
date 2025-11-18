<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Auction extends Model
{
    protected $table = 'auctions';

    protected $fillable = [
        'customer_id',
        'klik_auction_id',
        'auction_name',
        'auction_date',
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

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class, 'auction_id', 'id');
    }

    public function customer(): BelongsTo
    {
        return $this->BelongsTo(Customer::class, 'customer_id', 'klik_bidder_id');
    }
}
