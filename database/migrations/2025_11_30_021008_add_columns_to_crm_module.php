<?php

use App\Models\Module;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    protected $columns = [
        [
            'name' => 'ift',
            'label' => 'IFT',
            'order' => 2
        ],
        [
            'name' => 'phone2',
            'label' => 'Teléfono2',
            'order' => 4
        ],
        [
            'name' => 'email',
            'label' => 'Correo',
            'order' => 7
        ],
        [
            'name' => 'state_str',
            'label' => 'Estado',
            'order' => 8
        ],
        [
            'name' => 'municipality_str',
            'label' => 'Municipio',
            'order' => 9
        ],
        [
            'name' => 'colony_str',
            'label' => 'Colonia',
            'order' => 10
        ],
        [
            'name' => 'zip',
            'label' => 'Código Postal',
            'order' => 11
        ],
        [
            'name' => 'street',
            'label' => 'Calle',
            'order' => 12
        ],
        [
            'name' => 'external_number',
            'label' => 'Número Exterior',
            'order' => 13
        ],
        [
            'name' => 'internal_number',
            'label' => 'Número Interior',
            'order' => 14
        ],
        [
            'name' => 'score',
            'label' => 'Score',
            'order' => 15
        ],
        [
            'name' => 'high_date',
            'label' => 'Fecha de alta',
            'order' => 17
        ],
        [
            'name' => 'last_contacted',
            'label' => 'Ultimo contacto',
            'order' => 16
        ],
        [
            'name' => 'owner_str',
            'label' => 'Propietario',
            'order' => 17
        ],
    ];

    public function up(): void
    {
        $module = Module::where('name', 'Crm')->first();
        $module->columnsDatatable()->createMany($this->columns);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'Crm')->first();
        foreach ($this->columns as $c) {
            $module->columnsDatatable()->where('name', $c['name'])->delete();
        }
    }
};
