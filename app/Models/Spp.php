<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Spp extends Model
{
    protected $table = 'spps';

    protected $fillable = [
        'spp_no',
        'payment_id',
        'created_by',
        'updated_by',
        'updated_at',
    ];

    protected $hidden = [
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
    ];

    public function pv(): MorphMany 
    {
        return $this->MorphMany(PaymentVoucher::class, 'processable');
    }
}
