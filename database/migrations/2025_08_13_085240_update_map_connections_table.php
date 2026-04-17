<?php

use App\Models\MapConnection;
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
        $connections = MapConnection::all();
        Schema::table('map_connections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('from_id');
            $table->dropConstrainedForeignId('to_id');
            //$table->dropColumn(['from_id', 'to_id']);
        });
        Schema::table('map_connections', function (Blueprint $table) {
            $table->morphs('from');
            $table->morphs('to');
        });
        foreach ($connections as $c) {
            MapConnection::find($c->id)->update([
                'from_type' => 'App\Models\MapPort',
                'from_id' => $c->from_id,
                'to_type' => 'App\Models\MapPort',
                'to_id' => $c->to_id
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connections = MapConnection::all();
        Schema::table('map_connections', function (Blueprint $table) {
            $table->dropColumn(['from_type', 'from_id', 'to_type', 'to_id']);
        });
        Schema::table('map_connections', function (Blueprint $table) {
            $table->unsignedBigInteger('from_id')->nullable()->after('animate');
            $table->foreign('from_id')->references('id')->on('map_ports')->onDelete('cascade');
            $table->unsignedBigInteger('to_id')->nullable()->after('from_id');
            $table->foreign('to_id')->references('id')->on('map_ports')->onDelete('cascade');
        });
        foreach ($connections as $c) {
            MapConnection::find($c->id)->update([
                'from_id' => $c->from_id,
                'to_id' => $c->to_id
            ]);
        }
    }
};
