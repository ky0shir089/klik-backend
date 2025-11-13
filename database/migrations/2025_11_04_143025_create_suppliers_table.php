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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string("name");
            $table->boolean("is_active");
            $table->foreignId("created_by")->constrained("users")->cascadeOnDelete();
            $table->foreignId("updated_by")->nullable()->constrained("users")->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('supplier_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId("supplier_id")->constrained("suppliers")->cascadeOnDelete();
            $table->foreignId("bank_id")->constrained("banks")->cascadeOnDelete();
            $table->string("account_number");
            $table->string("account_name");
            $table->foreignId("created_by")->constrained("users")->cascadeOnDelete();
            $table->foreignId("updated_by")->nullable()->constrained("users")->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_accounts');
        Schema::dropIfExists('suppliers');
    }
};
