<?php

use App\Models\MapLayer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function precessFibers($layer)
    {
        $sequence = 1;
        foreach ($layer->fibers as $fiber) {
            $buffer = ceil($sequence / 12);
            $parent = ceil($sequence / 96);

            $buffer = ($buffer - 1) % 8 + 1;
            $fiber->update([
                'parent_buffer' => $parent,
                'buffer' => $buffer
            ]);
            $sequence++;
        }
    }
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('map_fibers', function (Blueprint $table) {
            $table->smallInteger('parent_buffer')->default(1)->after('id');
        });

        MapLayer::with('fibers')->where('dialog', 'route')->chunk(100, function ($layer) {
            foreach ($layer as $f) {
                $this->precessFibers($f);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('map_fibers', function (Blueprint $table) {
            $table->dropColumn('parent_buffer');
        });
    }
};
