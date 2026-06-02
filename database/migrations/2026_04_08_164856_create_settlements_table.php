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
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId("prepayment_pv_id")->constrained("payment_vouchers")->cascadeOnDelete();
            $table->foreignId("lpj_invoice_id")->nullable()->constrained("invoices")->cascadeOnDelete();
            $table->foreignId("byhmd_invoice_id")->nullable()->constrained("invoices")->cascadeOnDelete();
            $table->unsignedInteger("prepayment_amount")->default(0);
            $table->unsignedInteger("lpj_amount")->default(0);
            $table->unsignedInteger("byhmd_amount")->default(0);
            $table->integer("balance")->default(0);
            $table->string("status")->default("NEW");
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
        Schema::dropIfExists('settlements');
    }
};
