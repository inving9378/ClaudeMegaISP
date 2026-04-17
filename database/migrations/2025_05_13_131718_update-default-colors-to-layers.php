<?php

use App\Models\MapLayer;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $layers = MapLayer::all();
        foreach ($layers as $l) {
            $l->color = $l->type == 'marker' ? '#5bc0de' : '#6666ff';
            $l->icon_color = $l->type == 'marker' ? '#ffffff' : null;
            $l->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
