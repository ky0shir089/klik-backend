<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuRole extends Model
{
    protected $table = "menu_role";

    protected $fillable = [
        'role_id',
        'menu_id',
        'status',
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

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function menus(): HasMany
    {
        return $this->hasMany(Menu::class, "id", "menu_id");
    }
}
