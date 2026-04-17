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
          $checkIndexService = new CheckIndexService();
         $taskTablewithIndex = [
            "tasks" => [
                "assigned_to",
                "client_main_information_id",
                "project_id",
                "partner_id",
                "periodo",
                "priority",
                "status",
                "archived",
                "location_id",
                "archived_at",
                "finish_at"
            ]
        ];

        $checkIndexService->addMissingIndexes($taskTablewithIndex);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
      
    }
};
