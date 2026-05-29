<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_closures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ancestor_id');
            $table->unsignedBigInteger('descendant_id');
            $table->unsignedTinyInteger('depth')->comment('0=self, 1=directo, hasta 5');
            $table->timestamps();

            $table->unique(['ancestor_id', 'descendant_id'], 'uniq_ancestor_descendant');
            $table->index(['ancestor_id', 'depth'],  'idx_ancestor_depth');
            $table->index(['descendant_id', 'depth'], 'idx_descendant_depth');

            // No FK constraint sobre clients para no bloquear inserts masivos
            // La consistencia la garantiza RebuildClosuresCommand / ClientObserver
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_closures');
    }
};
