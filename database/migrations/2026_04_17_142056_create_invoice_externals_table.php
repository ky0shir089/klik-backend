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
        Schema::create('invoice_externals', function (Blueprint $table) {
            $table->id();
            $table->date("date");
            $table->date("due_date");
            $table->string("invoice_external_no");
            $table->foreignId("supplier_id")->constrained("suppliers")->cascadeOnDelete();
            $table->string("description");
            $table->unsignedInteger("total_unit")->default(0);
            $table->unsignedInteger("total_amount_real")->default(0);
            $table->unsignedInteger("total_amount_manual")->default(0);
            $table->unsignedInteger("ppn")->default(0);
            $table->unsignedInteger("pph23")->default(0);
            $table->unsignedInteger("grand_total")->default(0);
            $table->string("signatory");
            $table->foreignId("file_upload_id")->nullable()->constrained("file_uploads")->cascadeOnDelete();
            $table->string("status")->default("OPEN");
            $table->foreignId("created_by")->constrained("users")->cascadeOnDelete();
            $table->foreignId("updated_by")->nullable()->constrained("users")->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('invoice_external_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId("invoice_external_id")->constrained("invoice_externals")->cascadeOnDelete();
            $table->foreignId("unit_id")->constrained("units")->cascadeOnDelete();
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
        Schema::dropIfExists('invoice_external_units');
        Schema::dropIfExists('invoice_externals');
    }
};
