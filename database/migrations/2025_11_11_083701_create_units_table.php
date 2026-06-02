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
            $table->foreignId("auction_id")->constrained("auctions", "klik_auction_id")->cascadeOnDelete();
            $table->unsignedInteger("klik_unit_id")->unique();
            $table->unsignedInteger("lot_number");
            $table->string("police_number");
            $table->string("chassis_number");
            $table->string("engine_number");
            $table->string("package_number");
            $table->string("contract_number");
            $table->unsignedBigInteger("price")->default(0);
            $table->unsignedBigInteger("ticket_price")->default(0);
            $table->unsignedBigInteger("admin_fee")->default(0);
            $table->unsignedBigInteger("final_price")->default(0);
            $table->unsignedBigInteger("distributed_price")->default(0);
            $table->unsignedBigInteger("diff_price")->default(0);
            $table->unsignedBigInteger("byad_amount")->default(0);
            $table->unsignedBigInteger("admin_amount")->default(0);
            $table->unsignedBigInteger("fee_amount")->default(0);
            $table->unsignedBigInteger("ppn_amount")->default(0);
            $table->unsignedBigInteger("pph_amount")->default(0);
            $table->unsignedBigInteger("net_amount")->default(0);
            $table->string("payment_status")->default("UNPAID");
            $table->string("spp_status")->nullable();
            $table->string("byad_status")->nullable();
            $table->foreignId("created_by")->constrained("users")->cascadeOnDelete();
            $table->foreignId("updated_by")->nullable()->constrained("users")->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('unit_spps', function (Blueprint $table) {
            $table->id();
            $table->foreignId("unit_id")->constrained("units", "klik_unit_id")->cascadeOnDelete();
            $table->string("contract_number");
            $table->string("package_number");
            $table->unsignedBigInteger("distributed_price");
            $table->unsignedBigInteger("diff_price");
            $table->foreignId("created_by")->constrained("users")->cascadeOnDelete();
            $table->foreignId("updated_by")->nullable()->constrained("users")->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('unit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId("unit_id")->constrained("units", "klik_unit_id")->cascadeOnDelete();
            $table->string("date");
            $table->string("receipt_link");
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
        Schema::dropIfExists('unit_transactions');
        Schema::dropIfExists('unit_spps');
        Schema::dropIfExists('units');
    }
};
