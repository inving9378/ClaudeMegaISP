<?php

use App\Services\CheckIndexService;
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
        $tablesAndColumns = [
            'client_main_information' => [
              'activation_date',
              'created_at'
            ],
        ];
        $service = new CheckIndexService();
        $missingIndexes = $service->checkMissingIndexes($tablesAndColumns);
        $missingIndexes = $service->addMissingIndexes($missingIndexes);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
