<?php

use App\Models\Client;
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

        $clients = Client::all();
        foreach ($clients as $client) {
            $activities = $client->activities()->orderBy('id', 'desc')->get();
            foreach ($activities as $activity) {
                $data = json_decode($activity->properties, true);
                if (isset($data['attributes']['fecha_corte'])) {
                    $client->fecha_suspension = $data['attributes']['fecha_corte'];
                } elseif (isset($data['old']['fecha_corte'])) {
                    $client->fecha_suspension = $data['old']['fecha_corte'];
                }
            }
            $client->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $clients = Client::all();
        foreach ($clients as $client) {
            $client->fecha_suspension = null;
        }
    }
};
