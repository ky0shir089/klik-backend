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
            $table->string("date");
            $table->foreignId("type_trx_id")->constrained("type_trxes")->cascadeOnDelete();
            $table->string("description");
            $table->string("pay_method")->default("BANK");
            $table->foreignId("bank_account_id")->constrained("bank_accounts")->cascadeOnDelete();
            $table->foreignId("coa_id")->constrained("chart_of_accounts")->cascadeOnDelete();
            $table->unsignedInteger("starting_balance")->default(0);
            $table->unsignedInteger("used_balance")->default(0);
            $table->unsignedInteger("ending_balance")->default(0);
            $table->string("status")->default("NEW");
            $table->foreignId("customer_id")->nullable()->constrained("customers")->cascadeOnDelete();
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
        Schema::dropIfExists('receive_vouchers');
    }
};
