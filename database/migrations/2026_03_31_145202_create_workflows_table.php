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
        Schema::create('workflow_headers', function (Blueprint $table) {
            $table->id();
            $table->string("name");
            $table->json("type_trx");
            $table->unsignedInteger("min_amount")->default(0);
            $table->unsignedInteger("max_amount")->nullable();
            $table->boolean("is_active")->default(false);
            $table->foreignId("created_by")->constrained("users")->cascadeOnDelete();
            $table->foreignId("updated_by")->nullable()->constrained("users")->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('workflow_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId("wf_id")->constrained("workflow_headers")->cascadeOnDelete();
            $table->unsignedTinyInteger("sequence")->default(1);
            $table->foreignId("user_id")->constrained("users")->cascadeOnDelete();
            $table->foreignId("created_by")->constrained("users")->cascadeOnDelete();
            $table->foreignId("updated_by")->nullable()->constrained("users")->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('workflow_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId("wf_id")->constrained("workflow_headers")->cascadeOnDelete();
            $table->unsignedTinyInteger("sequence")->default(1);
            $table->morphs("processable");
            $table->string("status")->default("REQUEST");
            $table->foreignId("user_id")->constrained("users")->cascadeOnDelete();
            $table->text("signature")->nullable();
            $table->string("remark")->nullable();
            $table->timestamps();
        });

        Schema::create('workflow_approvals', function (Blueprint $table) {
            $table->id();
            $table->morphs("processable");
            $table->unsignedTinyInteger("approve_count")->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_approvals');
        Schema::dropIfExists('workflow_histories');
        Schema::dropIfExists('workflow_details');
        Schema::dropIfExists('workflow_headers');
    }
};
