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
        Schema::create('rv_classifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId("unit_id")->constrained("units")->cascadeOnDelete();
            $table->foreignId("rv_id")->constrained("receive_vouchers")->cascadeOnDelete();
            $table->unsignedBigInteger("rv_amount")->default(0);
            $table->unsignedBigInteger("unit_final_price")->default(0);
            $table->unsignedBigInteger("rv_balance")->default(0);
            $table->foreignId("created_by")->constrained("users")->cascadeOnDelete();
            $table->foreignId("updated_by")->nullable()->constrained("users")->cascadeOnDelete();
            $table->timestamps();

            // $table->unique(["unit_id", "rv_id"]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rv_classifications');
    }
};
