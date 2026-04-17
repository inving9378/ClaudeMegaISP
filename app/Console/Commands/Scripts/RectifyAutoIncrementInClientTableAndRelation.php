<?php

namespace App\Console\Commands\Scripts;

use App\Models\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RectifyAutoIncrementInClientTableAndRelation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:rectify-auto-increment-in-client-table-and-relation';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $lastClientId = Client::orderBy('id', 'desc')->first()->id;
        DB::statement('ALTER TABLE clients AUTO_INCREMENT =' . $lastClientId + 1);
    }
}
