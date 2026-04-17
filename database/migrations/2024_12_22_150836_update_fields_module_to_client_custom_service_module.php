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
        $module = \App\Models\Module::where('name', 'ClientCustomService')->first();
        $fieldInits = [
            'custom_id',
            'description',
            'amount',
            'unity',
            'price',
            'pay_period',
            'start_date',
            'discount',
            'estado',
            'payment_type',
        ];

        $fieldsOther = $module->fields()->whereNotIn('name', $fieldInits)->get();
        foreach ($fieldsOther as $field) {
            if ($field->partition == 'init') {
                $field->partition = 'other';
                $field->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = \App\Models\Module::where('name', 'ClientCustomService')->first();
        $fieldInits = [
            'custom_id',
            'description',
            'amount',
            'unity',
            'price',
            'pay_period',
            'start_date',
            'discount',
            'estado',
            'payment_type',
        ];

        $fieldsOther = $module->fields()->whereNotIn('name', $fieldInits)->get();
        foreach ($fieldsOther as $field) {
            if ($field->partition == 'other') {
                $field->partition = 'init';
                $field->save();
            }
        }
    }
};
