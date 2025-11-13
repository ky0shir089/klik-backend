<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string("name");
            $table->foreignId("menu_id")->constrained("menus")->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId("permission_id")->constrained("permissions")->cascadeOnDelete();
            $table->foreignId("role_id")->constrained("roles")->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
    }
};
