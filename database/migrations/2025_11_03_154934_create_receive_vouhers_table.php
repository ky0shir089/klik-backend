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
        Schema::create('receive_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string("rv_no");
            $table->dateTime("date");
            $table->foreignId("type_trx_id")->constrained("type_trxes")->cascadeOnDelete();
            $table->string("description");
            $table->string("pay_method")->default("BANK");
            $table->string("bank_account_id");
            $table->foreignId("coa_id")->constrained("chart_of_accounts")->cascadeOnDelete();
            $table->unsignedInteger("starting_balance")->default(0);
            $table->unsignedInteger("used_balance")->default(0);
            $table->unsignedInteger("ending_balance")->default(0);
            $table->string("journal_number")->nullable();
            $table->string("status")->default("NEW");
            $table->foreignId("customer_id")->nullable()->constrained("customers", "klik_bidder_id")->cascadeOnDelete();
            $table->foreignId("created_by")->constrained("users")->cascadeOnDelete();
            $table->foreignId("updated_by")->nullable()->constrained("users")->cascadeOnDelete();
            $table->timestamps();

            $table->foreign("bank_account_id")->references("account_number")->on("bank_accounts")->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receive_vouchers');
    }
};
