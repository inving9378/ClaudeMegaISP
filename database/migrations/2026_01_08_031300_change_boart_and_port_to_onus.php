<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    protected $fields = ['board', 'port'];
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->fields as $f) {
            DB::statement('ALTER TABLE olt_onus MODIFY ' . $f . ' varchar(3) NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
