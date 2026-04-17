<?php

use App\Models\DurationContract;
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
        Schema::create('duration_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('duration');
            $table->timestamps();
        });

        DurationContract::create([
            'name' => '6 Meses',
            'duration' => 6
        ]);

        DurationContract::create([
            'name' => '12 Meses',
            'duration' => 12
        ]);

        DurationContract::create([
            'name' => '18 Meses',
            'duration' => 18
        ]);

        DurationContract::create([
            'name' => '24 Meses',
            'duration' => 24
        ]);

        DurationContract::create([
            'name' => '36 Meses',
            'duration' => 36
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('duration_contracts');
    }
};
