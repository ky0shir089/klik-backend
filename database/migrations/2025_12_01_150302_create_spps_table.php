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
        Schema::create('spps', function (Blueprint $table) {
            $table->id();
            $table->foreignId("customer_id")->constrained("customers", "klik_bidder_id")->cascadeOnDelete();
            $table->string("branch_name");
            $table->unsignedInteger("total_unit");
            $table->unsignedInteger("total_amount");
            $table->string("status")->default("NEW");
            $table->foreignId("created_by")->constrained("users")->cascadeOnDelete();
            $table->foreignId("updated_by")->nullable()->constrained("users")->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('spp_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId("spp_id")->constrained("spps")->cascadeOnDelete();
            $table->foreignId("unit_id")->constrained("units", "klik_unit_id")->cascadeOnDelete();
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
        Schema::dropIfExists('spp_details');
        Schema::dropIfExists('spps');
    }
};
