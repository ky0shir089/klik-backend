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
        Schema::create('payment_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string("pv_no")->nullable();
            $table->string("description")->nullable();
            $table->string("payment_method")->default("BANK");
            $table->foreignId("bank_account_id")->nullable()->constrained("bank_accounts")->cascadeOnDelete();
            $table->foreignId("supplier_id")->constrained("suppliers")->cascadeOnDelete();
            $table->foreignId("supplier_account_id")->constrained("supplier_accounts")->cascadeOnDelete();
            $table->morphs("processable");
            $table->unsignedInteger("pv_amount");
            $table->unsignedInteger("rv_amount");
            $table->unsignedInteger("rv_balance")->default(0);
            $table->string("status")->default("NEW");
            $table->date("paid_date")->nullable();
            $table->foreignId("trx_dtl_id")->constrained("type_trxes")->cascadeOnDelete();
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
        Schema::dropIfExists('payment_vouchers');
    }
};
