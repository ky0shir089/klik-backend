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
            $table->date("date");
            $table->string("invoice_no");
            $table->foreignId("trx_id")->constrained("type_trxes")->cascadeOnDelete();
            $table->foreignId("supplier_id")->constrained("suppliers")->cascadeOnDelete();
            $table->string("payment_method")->default("BANK");
            $table->foreignId("supplier_account_id")->nullable()->constrained("supplier_accounts")->cascadeOnDelete();
            $table->string("description");
            $table->unsignedInteger("total_amount");
            $table->foreignId("file_upload_id")->nullable()->constrained("file_uploads")->cascadeOnDelete();
            $table->string("status")->default("REQUEST");
            $table->text("signature")->nullable();
            $table->foreignId("pv_id")->nullable()->constrained("payment_vouchers")->cascadeOnDelete();
            $table->foreignId("created_by")->constrained("users")->cascadeOnDelete();
            $table->foreignId("updated_by")->nullable()->constrained("users")->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('invoice_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId("invoice_id")->constrained("invoices")->cascadeOnDelete();
            $table->foreignId("inv_coa_id")->constrained("chart_of_accounts")->cascadeOnDelete();
            $table->string("description");
            $table->unsignedBigInteger("item_amount");
            $table->foreignId("pph_id")->nullable()->constrained("pphs")->cascadeOnDelete();
            $table->unsignedBigInteger("pph_amount");
            $table->unsignedInteger("ppn_rate");
            $table->unsignedInteger("ppn_amount");
            $table->foreignId("rv_id")->nullable()->constrained("receive_vouchers")->cascadeOnDelete();
            $table->unsignedInteger("total_amount");
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
        Schema::dropIfExists('invoice_details');
        Schema::dropIfExists('invoices');
    }
};
