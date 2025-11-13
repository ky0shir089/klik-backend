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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->date("payment_date");
            $table->unsignedBigInteger("branch_id");
            $table->string("branch_name");
            $table->foreignId("customer_id")->constrained("customers", "klik_bidder_id")->cascadeOnDelete();
            $table->unsignedInteger("total_unit");
            $table->unsignedInteger("total_amount");
            $table->string("status")->default('NEW');
            $table->foreignId("created_by")->constrained("users")->cascadeOnDelete();
            $table->foreignId("updated_by")->nullable()->constrained("users")->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('payment_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId("payment_id")->constrained("payments")->cascadeOnDelete();
            $table->foreignId("unit_id")->constrained("units")->cascadeOnDelete();
            $table->foreignId("created_by")->constrained("users")->cascadeOnDelete();
            $table->foreignId("updated_by")->nullable()->constrained("users")->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('payment_rvs', function (Blueprint $table) {
            $table->id();
            $table->foreignId("payment_id")->constrained("payments")->cascadeOnDelete();
            $table->foreignId("rv_id")->constrained("receive_vouchers")->cascadeOnDelete();
            $table->unsignedInteger("rv_amount");
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
        Schema::dropIfExists('payment_rvs');
        Schema::dropIfExists('payment_details');
        Schema::dropIfExists('payments');
    }
};
