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
            $table->unsignedBigInteger("klik_auction_id")->unique();
            $table->string("auction_name");
            $table->date("auction_date");
            $table->string("branch_id");
            $table->string("branch_name");
            $table->foreignId("created_by")->constrained("users")->cascadeOnDelete();
            $table->foreignId("updated_by")->nullable()->constrained("users")->cascadeOnDelete();
            $table->timestamps();

            $table->unique(["customer_id", "klik_auction_id"]);
        });

        // Schema::create('auction_customer', function (Blueprint $table) {
        //     $table->id();
        //     $table->foreignId("customer_id")->constrained("customers", "klik_bidder_id")->cascadeOnDelete();
        //     $table->foreignId("auction_id")->constrained("auctions", "klik_auction_id")->cascadeOnDelete();
        //     $table->unsignedBigInteger("total_unit")->default(0);
        //     $table->unsignedBigInteger("total_base_price")->default(0);
        //     $table->unsignedBigInteger("total_admin_fee")->default(0);
        //     $table->unsignedBigInteger("total_final_price")->default(0);
        //     $table->string("status")->default("NEW");
        //     $table->foreignId("created_by")->constrained("users")->cascadeOnDelete();
        //     $table->foreignId("updated_by")->nullable()->constrained("users")->cascadeOnDelete();
        //     $table->timestamps();

        //     $table->unique(["customer_id", "auction_id"]);
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Schema::dropIfExists('auction_customer');
        Schema::dropIfExists('auctions');
    }
};
