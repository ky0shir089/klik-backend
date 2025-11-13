<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Module extends Model
{
    protected $table = 'modules';

    protected $fillable = [
        'name',
        'icon',
        'position',
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

    public function menus(): HasManyThrough
    {
        return $this->hasManyThrough(MenuRole::class, Menu::class, "module_id", "menu_id", "id", "id");
    }
}
