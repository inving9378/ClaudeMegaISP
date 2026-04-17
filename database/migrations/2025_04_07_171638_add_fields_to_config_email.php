<?php

use App\Models\Module;
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
        Schema::table('email_settings', function (Blueprint $table) {
            $table->integer('limit_per_hour')->default(0)->after('mail_from_name');
            $table->integer('limit_per_minute')->default(0)->after('limit_per_hour');
        });

        $module = Module::where('name', 'EmailSetting')->first();
        $fields = [
            [
                'name' => 'limit_per_hour',
                'label' => 'Limite por Hora',
                'placeholder' => '',
                'type' => 15,
                'position' => 9,
                'hint' => 'Configure en 0 para envíos ilimitados.',
                'additional_field' => false,
            ],
            [
                'name' => 'limit_per_minute',
                'label' => 'Limite por Minuto',
                'placeholder' => '',
                'type' => 15,
                'position' => 10,
                'hint'=> 'Configure en 0 para envíos ilimitados.',
                'additional_field' => false,
            ],

        ];
        $module->fields()->createMany($fields);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_settings', function (Blueprint $table) {
            $table->dropColumn('limit_per_hour');
            $table->dropColumn('limit_per_minute');
        });

        $module = Module::where('name', 'EmailSetting')->first();
        $module->fields()->where('name', 'limit_per_hour')->delete();
        $module->fields()->where('name', 'limit_per_minute')->delete();
    }
};
