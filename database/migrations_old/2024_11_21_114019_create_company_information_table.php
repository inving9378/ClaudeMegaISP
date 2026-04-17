<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('company_information', function (Blueprint $table) {
            $table->id();
            $table->string('company_name')->nullable();
            $table->string('company_postal_code')->nullable();
            $table->string('country')->nullable();
            $table->string('colony_id')->nullable();
            $table->string('state_id')->nullable();
            $table->string('municipality_id')->nullable();
            $table->string('email')->nullable();
            $table->string('atention_client_phone')->nullable();
            $table->string('rfc')->nullable();
            $table->string('iva')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account')->nullable();
            $table->string('cominion_partner')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        DB::table('company_information')->insert([
            'company_name' => 'Meganet',
            'company_postal_code' => '00000',
            'country' => 'México',
            'colony_id' => null,
            'state_id' => null,
            'municipality_id' => null,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_information');
    }
};
