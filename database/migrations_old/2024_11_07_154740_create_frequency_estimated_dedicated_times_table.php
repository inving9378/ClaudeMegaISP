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
        Schema::create('frequency_estimated_dedicated_times', function (Blueprint $table) {
            $table->id();
            $table->string('value');
            $table->timestamps();
        });

        $frequencies = [
            [
                'value' => '00:00'
            ],
            [
                'value' => '00:30'
            ],
            [
                'value' => '01:00'
            ],
            [
                'value' => '01:30'
            ],
            [

                'value' => '02:00'
            ],
            [

                'value' => '02:30'
            ],
            [

                'value' => '03:00'
            ],
            [

                'value' => '03:30'
            ],
            [

                'value' => '04:00'
            ],
            [
                'value' => '04:30'
            ],
            [

                'value' => '05:00'
            ],
            [

                'value' => '05:30'
            ],
            [

                'value' => '06:00'
            ],
            [

                'value' => '06:30'
            ],
            [

                'value' => '07:00'
            ],
            [
                'value' => '07:30'
            ],
            [
                'value' => '08:00'
            ],
        ];
        foreach ($frequencies as $frequency) {
            DB::table('frequency_estimated_dedicated_times')->insert($frequency);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('frequency_estimated_dedicated_times');
    }
};
