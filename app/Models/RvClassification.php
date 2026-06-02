<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RvClassification extends Model
{
    protected $table = 'rv_classifications';

    protected $fillable = [
        'rv_id',
        'unit_id',
        'rv_amount',
        'unit_final_price',
        'rv_balance',
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

    public function rv(): BelongsTo
    {
        return $this->belongsTo(RV::class, 'rv_id', 'id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id', 'id');
    }
}
