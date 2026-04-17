<?php

use App\Http\Repository\FieldTypeRepository;
use App\Models\Module;
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
        $fieldTypeRepository = new FieldTypeRepository();
        $module = Module::where('name', 'InventoryItemCustom')->first();
        $fields = [
            [
                'name' => 'name',
                'label' => 'Nombre',
                'placeholder' => '',
                'hint' => 'Este nombre es el que generaliza los articulos',
                'type' => 1,
                'additional_field' => false,
                'position' => 1,
            ],
            [
                'name' => 'serial_number',
                'label' => 'Identificador ',
                'placeholder' => '',
                'type' => $fieldTypeRepository->getIdByName('input-scanner') ?? 1,
                'additional_field' => false,
                'position' => 2,
            ],
            [
                'name' => 'description',
                'label' => 'Descripcion',
                'placeholder' => 'Descripcion',
                'type' => 5,
                'position' => 5,
                'additional_field' => false,
            ],

            [
                'name' => 'status_item',
                'label' => 'Estado del Articulo',
                'placeholder' => 'Seleccione',
                'type' => 22,
                'position' => 6,
                'additional_field' => false,
                'options' => json_encode([
                    "new" => "Nuevo",
                    "used" => "Usado",
                    "repair" => "En Reparacion",
                    "warranty" => "Garantia",
                    "broken" => "Roto"
                ])
            ],
            [
                'name' => 'image',
                'label' => 'Imagen',
                'placeholder' => 'Agregar Imagen',
                'type' => 44,
                'position' => 7,
                'additional_field' => false
            ],

            [
                'name' => 'serial_number_enable',
                'label' => 'Activar Numero de serie',
                'placeholder' => 'Descripcion',
                'type' => 3,
                'position' => 100,
                'additional_field' => false,
                'default_value' => 1
            ],
            [
                'name' => 'status_item_enable',
                'label' => 'Activar estado del articulo',
                'placeholder' => 'Descripcion',
                'type' => 3,
                'position' => 100,
                'additional_field' => false,
                'default_value' => 1
            ],
            [
                'name' => 'inventory_store_id',
                'label' => 'Almacen',
                'placeholder' => 'Almacen',
                'type' => 3,
                'position' => 100,
                'additional_field' => false
            ],

            [
                'name' => 'store_zone_id',
                'label' => 'ZONA',
                'placeholder' => 'ZONA',
                'type' => 3,
                'position' => 100,
                'additional_field' => false
            ],

            [
                'name' => 'store_zone_id',
                'label' => 'ZONA',
                'placeholder' => 'ZONA',
                'type' => 3,
                'position' => 100,
                'additional_field' => false
            ],
            [
                'name' => 'inventory_item_custom_model_id',
                'label' => 'Modelo de Articulo',
                'placeholder' => 'Modelo de Articulo',
                'type' => 3,
                'position' => 100,
                'additional_field' => false
            ]

        ];
        $module->fields()->createMany($fields);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'InventoryItemCustom')->first();
        $module->fields()->delete();
    }
};
