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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string("invoice_no");
            $table->foreignId("rv_id")->nullable()->constrained("receive_vouchers")->cascadeOnDelete();
            $table->foreignId("customer_id")->constrained("customers")->cascadeOnDelete();
            $table->foreignId("bank_account_id")->constrained("bank_accounts")->cascadeOnDelete();
            $table->string("status")->default("REQUEST");
            $table->string("payment_method")->default("BANK");
            $table->string("description");
            $table->unsignedBigInteger("amount");
            $table->foreignId("inv_coa_id")->constrained("chart_of_accounts")->cascadeOnDelete();
            $table->foreignId("trx_id")->constrained("type_trxes")->cascadeOnDelete();
            $table->foreignId("pv_id")->nullable()->constrained("payment_vouchers")->cascadeOnDelete();
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
        Schema::dropIfExists('invoices');
    }
};
