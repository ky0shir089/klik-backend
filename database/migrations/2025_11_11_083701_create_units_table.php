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
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId("auction_id")->constrained("auctions")->cascadeOnDelete();
            $table->unsignedInteger("lot_number");
            $table->string("police_number");
            $table->string("chassis_number");
            $table->string("engine_number");
            $table->string("contract_number")->nullable();
            $table->string("package_number")->nullable();
            $table->unsignedBigInteger("price");
            $table->unsignedBigInteger("admin_fee");
            $table->unsignedBigInteger("final_price");
            $table->string("payment_status")->default("UNPAID");
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
        Schema::dropIfExists('units');
    }
};
