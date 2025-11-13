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
        Schema::create('type_trxes', function (Blueprint $table) {
            $table->id();
            $table->string("code");
            $table->string("name");
            $table->string("in_out");
            $table->boolean("is_active");
            $table->foreignId("created_by")->constrained("users")->cascadeOnDelete();
            $table->foreignId("updated_by")->nullable()->constrained("users")->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('trx_dtls', function (Blueprint $table) {
            $table->id();
            $table->foreignId("trx_id")->constrained("type_trxes")->cascadeOnDelete();
            $table->foreignId("coa_id")->constrained("chart_of_accounts")->cascadeOnDelete();
            $table->boolean("is_active");
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
        Schema::dropIfExists('trx_dtls');
        Schema::dropIfExists('type_trxes');
    }
};
