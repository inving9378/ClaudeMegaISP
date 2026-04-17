<?php

use App\Models\MapDevice;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{

    protected function changePorts($n)
    {
        $devices = MapDevice::where('type', 'olt')->get();
        foreach ($devices as $d) {
            $ports = $d->ports;
            foreach ($ports as $p) {
                $name = intval($p->name) + $n;
                if ($name <= 9) {
                    $name = '0' . $name;
                }
                $p->name = $name;
                $p->save();
            }
        }
    }
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->changePorts(-1);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->changePorts(1);
    }
};
