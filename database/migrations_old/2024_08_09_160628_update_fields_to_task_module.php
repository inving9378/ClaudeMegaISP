<?php

use App\Http\Repository\FieldTypeRepository;
use App\Models\FieldType;
use App\Models\Module;
use App\Models\TemplateVerification;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use SebastianBergmann\Template\Template;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {

        Schema::create('template_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('list')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        $valuesTemplate = [
            [
                'name' => 'Instalación',
                'list' => json_encode([
                    'Preparar materiales / herramientas',
                    'Fibra / antena /utp',
                    'Modem',
                    'KIT/pinzas/cortadora/conectores'
                ]),
            ],
            [
                'name' => 'Desconectar Cliente',
                'list' => json_encode([
                    'Remocion de equipo / Desconectar de la red',
                    'Revisar que no quede el cable colgando'
                ]),
            ],
            [
                'name' => 'Reparación de Cliente',
                'list' => json_encode([
                    'Preparar material/herramienta',
                    'Revision del problema',
                    'Realizar testeo de funcionamiento',
                    'Validar si es con costo',
                ]),
            ],
            [
                'name' => 'Instalación Wifi',
                'list' => json_encode([
                    'Preparar material/herramienta',
                    'Cable utp, Mastil',
                    'Modem',
                    'Realizar testeo',
                ]),
            ],
            [
                'name' => 'Remoción Equipo Wifi',
                'list' => json_encode([
                    'Remoción de equipo Antena/cable /poe/modem'
                ]),
            ],
            [
                'name' => 'Reparar Wifi',
                'list' => json_encode([
                    'Preparar materiales /herramientas',
                    'Reparacion del problema',
                    'Realizar testeo',
                ]),
            ],
        ];

        foreach ($valuesTemplate as $value) {
            TemplateVerification::create($value);
        }

        Schema::table('field_modules', function (Blueprint $table) {
            $table->string('class_label')->after('class_col')->default('col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center')->nullable();
            $table->string('class_field')->after('class_label')->default('col-sm-12 col-md-9')->nullable();
        });

        $fieldVueDatePickerSingle = FieldType::create([
            'name' => 'input_vue_datepicker_single',
        ]);

        $fieldHourVuePicker = FieldType::create([
            'name' => 'input_vue_hour_picker',
        ]);

        $inputModalGoogleMaps = FieldType::create([
            'name' => 'input_modal_google_map',
        ]);

        $selectTemplateListVerification = FieldType::create([
            'name' => 'select_template_list_verification',
        ]);

        $module = Module::where('name', 'Task')->first();
        $module->fields()->delete();

        $fieldTypeRepository = new FieldTypeRepository();
        $fieldTypeSearchCLientId = $fieldTypeRepository->getIdByName('select_component_with_search_client');
        $fieldTypeRepository = new FieldTypeRepository();
        $fieldTypeSearch = $fieldTypeRepository->getIdByName('select_component_with_search');

        $fields = [
            [
                'name' => 'template_id',
                'label' => 'Selecciona Plantilla',
                'type' => 22,
                'placeholder' => 'Selecciona Plantilla',
                'position' => 1,
                'additional_field' => false,
                'value' => '',
                'options' => null,
                'search' => json_encode([
                    'model' => 'App\Models\DocumentTemplate',
                    'id' => 'id',
                    'text' => 'name',
                ]),
                'class_label' => "col-12",
                'class_field' => "col-12"
            ],
            [
                'name' => 'title',
                'label' => 'Título',
                'placeholder' => 'Título',
                'type' => 1,
                'position' => 2,
                'additional_field' => false,
                'class_label' => "col-12",
                'class_field' => "col-12"
            ],
            [
                'name' => 'description',
                'label' => 'Descripción',
                'placeholder' => 'Descripción',
                'type' => 5,
                'position' => 3,
                'additional_field' => false,
                'class_label' => "col-12",
                'class_field' => "col-12"
            ],
            [
                'name' => 'project_id',
                'label' => 'Proyecto',
                'type' => 22,
                'placeholder' => 'Selecciona Proyecto',
                'position' => 4,
                'additional_field' => false,
                'value' => '',
                'options' => null,
                'search' => json_encode([
                    'model' => 'App\Models\Project',
                    'id' => 'id',
                    'text' => 'name',
                ]),
                'class_col' => 'partial',
                'class_label' => "col-12",
                'class_field' => "col-12"
            ],
            [
                'name' => 'assigned_to',
                'label' => 'Asignado a',
                'type' => $fieldTypeSearch,
                'placeholder' => '',
                'position' => 5,
                'additional_field' => false,
                'value' => '',
                'search' => json_encode([
                    'model' => 'App\Models\User',
                    'id' => 'id',
                    'text' => 'name',
                    'scope' => 'leadProject'
                ]),
                'class_col' => 'partial',
                'class_label' => "col-12",
                'class_field' => "col-12"
            ],
            [
                'name' => 'priority',
                'label' => 'Prioridad',
                'type' => 22,
                'placeholder' => '',
                'position' => 6,
                'additional_field' => false,
                'value' => '',
                'options' => json_encode([
                    'Alta' => 'Alta',
                    'Media' => 'Media',
                    'Baja' => 'Baja'
                ]),
                'class_col' => 'partial',
                'class_label' => "col-12",
                'class_field' => "col-12"
            ],
            [
                'name' => 'status',
                'label' => 'Estado',
                'type' => 22,
                'placeholder' => '',
                'position' => 7,
                'additional_field' => false,
                'value' => '',
                'options' => json_encode([
                    'ToDo' => 'To Do',
                    'InProgress' => 'In Progress',
                    'Done' => 'Done'
                ]),
                'class_col' => 'partial',
                'class_label' => "col-12",
                'class_field' => "col-12"


            ],
            [
                'name' => 'client_main_information_id',
                'label' => 'Cliente',
                'placeholder' => 'Por Favor introduzca 2 o mas caracteres',
                'type' => $fieldTypeSearchCLientId,
                'position' => 8,
                'additional_field' => false,
                'search' => json_encode([
                    'model' => 'App\Models\ClientMainInformation',
                    'id' => 'id',
                    'text' => 'name',
                    'append' => 'client_name_with_fathers_names'
                ]),
                'class_label' => "col-12",
                'class_field' => "col-12"
            ],

            [
                'name' => 'partner_id',
                'label' => 'Socio',
                'type' => 22,
                'placeholder' => '',
                'position' => 9,
                'additional_field' => false,
                'value' => '',
                'search' => json_encode([
                    'model' => 'App\Models\Partner',
                    'id' => 'id',
                    'text' => 'name',
                ]),
                'class_col' => 'partial',
                'class_label' => "col-12",
                'class_field' => "col-12"
            ],

            [
                'name' => 'location_id',
                'label' => 'Ubicación',
                'type' => 22,
                'placeholder' => '',
                'position' => 10,
                'additional_field' => false,
                'value' => '',
                'search' => json_encode([
                    'model' => 'App\Models\Location',
                    'id' => 'id',
                    'text' => 'name',
                ]),
                'class_col' => 'partial',
                'class_label' => "col-12",
                'class_field' => "col-12"
            ],
            [
                'name' => 'address',
                'label' => 'Dirección',
                'placeholder' => 'Dirección',
                'type' => 1,
                'position' => 11,
                'additional_field' => false,
                'class_label' => "col-12",
                'class_field' => "col-12"
            ],
            [
                'name' => 'geo_data',
                'label' => 'Datos geográficos',
                'placeholder' => 'Datos geográficos',
                'type' => $inputModalGoogleMaps->id,
                'position' => 12,
                'additional_field' => false,
                'class_label' => "col-12",
                'class_field' => "col-12"
            ],
            [
                'name' => 'template_verification',
                'label' => 'Plantilla de la lista de Verificación',
                'type' => $selectTemplateListVerification->id,
                'placeholder' => '',
                'position' => 13,
                'additional_field' => false,
                'value' => '',
                'search' => json_encode([
                    'model' => 'App\Models\TemplateVerification',
                    'id' => 'id',
                    'text' => 'name',
                ]),
                'class_label' => "col-form-label pr-2",
                'class_field' => "col-sm-12"
            ],
            [
                'name' => 'start_time',
                'label' => 'Hora de Inicio',
                'type' => $fieldVueDatePickerSingle->id,
                'placeholder' => '',
                'position' => 14,
                'additional_field' => false,
                'value' => '',
                'class_col' => 'partial',
                'class_label' => "col-form-label pr-2",
                'class_field' => "col-sm-12"
            ],
            [
                'name' => 'end_time',
                'label' => 'Hora de Terminación',
                'type' => $fieldVueDatePickerSingle->id,
                'placeholder' => '',
                'position' => 15,
                'additional_field' => false,
                'value' => '',
                'class_col' => 'partial',
                'class_label' => "col-form-label pr-2",
                'class_field' => "col-sm-12"
            ],

            [
                'name' => 'time_to_task_location',
                'label' => 'Travel time to task location',
                'type' => $fieldHourVuePicker->id,
                'placeholder' => '0h 0m',
                'position' => 16,
                'additional_field' => false,
                'value' => '',
                'class_col' => 'partial',
                'class_label' => "col-form-label pr-2",
                'class_field' => "col-sm-12"
            ],
            [
                'name' => 'time_from_task_location',
                'label' => 'Travel time from task location',
                'type' => $fieldHourVuePicker->id,
                'placeholder' => '0h 0m',
                'position' => 17,
                'additional_field' => false,
                'value' => '',
                'class_col' => 'partial',
                'class_label' => "col-form-label pr-2",
                'class_field' => "col-sm-12"
            ],
        ];
        $module->fields()->createMany($fields);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_verifications');
        Schema::table('field_modules', function (Blueprint $table) {
            $table->dropColumn('class_label');
            $table->dropColumn('class_field');
        });
        $module = Module::where('name', 'Task')->first();
        $module->fields()->delete();

        FieldType::where('name', 'input_vue_datepicker_single')->delete();
        FieldType::where('name', 'input_vue_hour_picker')->delete();
        FieldType::where('name', 'input_modal_google_map')->delete();
        FieldType::where('name', 'select_template_list_verification')->delete();
    }
};
