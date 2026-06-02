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
        Schema::create('byad_headers', function (Blueprint $table) {
            $table->id();
            $table->date("date");
            $table->string("branch");
            $table->string("description");
            $table->foreignId("file_upload_id")->nullable()->constrained("file_uploads")->cascadeOnDelete();
            $table->unsignedInteger("total_unit")->default(0);
            $table->unsignedInteger("total_amount")->default(0);
            $table->unsignedInteger("byad_amount")->default(0);
            $table->string("status")->default("NEW");
            $table->foreignId("created_by")->constrained("users")->cascadeOnDelete();
            $table->foreignId("updated_by")->nullable()->constrained("users")->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('byad_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId("byad_id")->constrained("byad_headers")->cascadeOnDelete();
            $table->foreignId("unit_id")->constrained("units")->cascadeOnDelete();
            $table->foreignId("created_by")->constrained("users")->cascadeOnDelete();
            $table->foreignId("updated_by")->nullable()->constrained("users")->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('byad_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId("invoice_id")->constrained("invoices")->cascadeOnDelete();
            $table->date("date");
            $table->unsignedInteger("total_unit")->default(0);
            $table->unsignedInteger("total_amount")->default(0);
            $table->unsignedInteger("byad_amount")->default(0);
            $table->string("status")->default("NEW");
            $table->foreignId("created_by")->constrained("users")->cascadeOnDelete();
            $table->foreignId("updated_by")->nullable()->constrained("users")->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('byad_payment_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId("byad_payment_id")->constrained("byad_payments")->cascadeOnDelete();
            $table->foreignId("byad_id")->constrained("byad_headers")->cascadeOnDelete();
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
        Schema::dropIfExists('byad_payment_details');
        Schema::dropIfExists('byad_payments');
        Schema::dropIfExists('byad_details');
        Schema::dropIfExists('byad_headers');
    }
};
