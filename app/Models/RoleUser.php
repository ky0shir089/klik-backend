<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoleUser extends Model
{
    protected $table = "role_user";

    protected $fillable = [
        'user_id',
        'role_id',
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

    public function role_menu(): HasMany
    {
        return $this->HasMany(RoleMenu::class, "role_id", "role_id");
    }
}
