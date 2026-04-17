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
        \App\Models\FieldModule::where('module_id', 8)->where('name', 'owner_id')->update(['search' => '{"model":"App\\\Models\\\User","id":"id","text":"name", "scope": "sellerRole"}']);
        \App\Models\FieldModule::where('module_id', 10)->where('name', 'owner_id')->update(['search' => '{"model":"App\\\Models\\\User","id":"id","text":"name", "scope": "sellerRole"}']);
        \App\Models\FieldModule::where('module_id', 10)->where('name', 'crm_techical_user_id')->update(['search' => '{"model":"App\\\Models\\\User","id":"id","text":"name", "scope": "technicalRole"}']);
        \App\Models\FieldModule::where('module_id', 13)->where('name', 'seller_id')->update(['search' => '{"model":"App\\\Models\\\User","id":"id","text":"name", "scope": "sellerRole"}']);
        \App\Models\FieldModule::where('module_id', 44)->where('name', 'assigned_to')->update(['search' => '{"model":"App\\\Models\\\User","id":"id","text":"name", "scope": "notClientRole"}']);
        \App\Models\FieldModule::where('module_id', 45)->where('name', 'assigned_to')->update(['search' => '{"model":"App\\\Models\\\User","id":"id","text":"name", "scope": "notClientRole"}']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
