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
        Schema::create('auctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId("customer_id")->constrained("customers", "klik_bidder_id")->cascadeOnDelete();
            $table->unsignedBigInteger("klik_auction_id");
            $table->string("auction_name");
            $table->date("auction_date");
            $table->string("branch_id");
            $table->string("branch_name");
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
        Schema::dropIfExists('auctions');
    }
};
