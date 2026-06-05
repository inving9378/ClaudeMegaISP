<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_sidebar_config', function (Blueprint $table) {
            $table->id();
            $table->string('module_key', 100)->unique();
            $table->boolean('show_in_sidebar')->default(true);
            $table->enum('sidebar_location', ['direct', 'sub_item'])->default('direct');
            $table->string('sidebar_parent', 100)->nullable();
            $table->enum('sidebar_section', ['menu', 'modulos'])->default('modulos');
            $table->integer('sidebar_position')->default(99);
            $table->string('sidebar_icon', 80)->nullable();
            $table->string('sidebar_label', 100);
            $table->boolean('config_moved')->default(false);
            $table->string('admin_section', 80)->nullable();
            $table->string('configuracion_subsection', 100)->nullable();
            $table->boolean('is_core')->default(false);
            $table->timestamps();

            $table->index(['show_in_sidebar', 'sidebar_section', 'sidebar_position'], 'msc_visible_section_pos');
            $table->index('sidebar_parent', 'msc_parent');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_sidebar_config');
    }
};
