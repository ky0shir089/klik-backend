<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $table = "roles";

    protected $fillable = [
        'name',
        'description',
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

    protected $casts = [
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->BelongsToMany(Permission::class, "permission_role", "role_id", "permission_id");
    }

    public function menus(): BelongsToMany
    {
        return $this->BelongsToMany(Menu::class, "menu_role", "role_id", "menu_id");
    }
}
