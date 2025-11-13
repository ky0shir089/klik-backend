<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Menu extends Model
{
    protected $table = "menus";

    protected $fillable = [
        'name',
        'url',
        'position',
        'is_active',
        'slug',
        'module_id',
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

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function permissions(): HasMany
    {
        return $this->HasMany(Permission::class);
    }
}
