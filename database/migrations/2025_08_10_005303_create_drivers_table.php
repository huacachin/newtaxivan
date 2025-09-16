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
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('document_number', 50)->unique();
            $table->date('document_expiration_date')->nullable();
            $table->date('birthdate')->nullable();
            $table->string('email', 150)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('license', 50)->nullable();
            $table->string('class', 50)->nullable();
            $table->string('category', 50)->nullable();
            $table->date('license_issue_date')->nullable();
            $table->date('license_revalidation_date')->nullable();
            $table->date('contract_start')->nullable();
            $table->date('contract_end')->nullable();
            $table->string('condition', 100)->nullable();
            $table->string('status', 50)->default('active');
            $table->date('road_education')->nullable();
            $table->date('road_education_expiration_date')->nullable();
            $table->string('road_education_municipality', 150)->nullable();
            $table->date('credential')->nullable();
            $table->date('credential_expiration_date')->nullable();
            $table->string('credential_municipality', 150)->nullable();
            $table->integer('score')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
