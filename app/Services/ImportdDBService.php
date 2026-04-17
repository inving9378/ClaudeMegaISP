<?php

namespace App\Services;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Repository\FieldTypeRepository;
use App\Http\Repository\NetworkRepository;
use App\Http\Repository\RouterRepository;
use App\Models\FieldType;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Style\Color;

class ImportdDBService
{
    public function exportExample($module)
    {
        $model = $module->getNameModel();
        $tabla = $this->getTableForThisModel($model);
        $translatedColumns = trans('translation_columns_table');
        $columns = $this->getColumnsForThisModule($module, $translatedColumns, $tabla);
        $nonNullableColumns = $this->getColumnsNonNullableForThisModulue($module, $translatedColumns, $tabla, $model);
        $enumColumns = $this->getColumnsEnumnsForThisModule($module, $translatedColumns, $tabla);
        $booleanColumns = $this->getColumnsBooleanForThisModule($module, $translatedColumns, $tabla);
        $columns = array_values($columns);
        $data = [$columns];

        $directory = public_path('storage/example/' . $tabla);
        $filename = 'example-' . $tabla . '.xlsx'; // Nombre del archivo
        $filePath = $directory . '/' . $filename;

        // Eliminar el archivo si ya existe
        if (File::exists($filePath)) {
            File::delete($filePath);
        }

        // Crear la carpeta si no existe
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0777, true, true);
        }

        $title = ucwords(str_replace('_', '', $tabla));
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($title);
        $sheet->fromArray($data, null, 'A1');

        $this->aplyStyleNonNullableColumns($columns, $nonNullableColumns, $sheet);
        $this->aplyStyleEnumColumns($columns, $enumColumns, $sheet);
        $this->createEnumSheet($spreadsheet, $enumColumns);
        $this->aplyStylebooleanColumns($columns, $booleanColumns, $sheet);
        $this->createNewSheet($spreadsheet, $booleanColumns, 'Booleans', 'FFFF00FF');

        $spreadsheet->setActiveSheetIndex(0);
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        //Ancho de las columnas
        $columnWidth = 25;
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $columnCount = $sheet->getHighestColumn();
            for ($column = 'A'; $column <= $columnCount; $column++) {
                $sheet->getColumnDimension($column)->setWidth($columnWidth);
            }
        }
        $writer->save($filePath);


        $downloadUrl = asset('storage/example/' . $tabla . '/' . $filename);
        return response()->json(['file' => $downloadUrl]);
    }


    /* Start get columns */

    public function getColumnsForThisModule($module, $translatedColumns, $tabla)
    {
        $specialOrder = ComunConstantsController::ORDER_COLUMNS_MODULE_TO_EXPORT_EXCEL;
        $typeFieldRepository = new FieldTypeRepository();
        $fields = $module->fields()->where('type', '!=', $typeFieldRepository->getIdByName('input-hidden'))->pluck('name')->toArray();
        $nameModule = $module->name;
        if (isset($specialOrder[$nameModule])) {
            $moduleFields = $specialOrder[$nameModule];
            $fields = array_merge($moduleFields, $fields);
        }

        $lastColumnsToImport = ComunConstantsController::LAST_COLUMNS_TO_IMPORT;
        if (isset($lastColumnsToImport[$nameModule])) {
            $moduleFields = $lastColumnsToImport[$nameModule];
            foreach ($moduleFields as $field) {
                $fields[] = $field;
            }
        }
        $fields = array_unique($fields);

        $fields = $this->deleteColumnsAccordingModule($fields, $nameModule);
        foreach ($fields as $key => $value) {
            if (isset($translatedColumns[$tabla][$value]) && $translatedColumns[$tabla][$value] == "") {
                $value = $value;
            } else if (!isset($translatedColumns[$tabla][$value])) {
                $value = $value;
            } else {
                $value = $translatedColumns[$tabla][$value] ?? ($translatedColumns[$tabla][$value] == "" ? $value : $translatedColumns[$tabla][$value]);
            }
            $fields[$key] = $value;
        }

        return $fields;
    }

    public function deleteColumnsAccordingModule($fields, $nameModule)
    {
        if ($nameModule == 'ClientTransaction') {
            $arrayExcept = ['input-price-transaction'];
            $fields = array_diff($fields, $arrayExcept);
            $fields = array_values($fields);
        }
        if ($nameModule == 'ClientInternetService' || $nameModule == 'ClientVozService' || $nameModule == 'ClientCustomService') {
            $arrayExcept = ['description', 'price', 'amount', 'unity'];
            $fields = array_diff($fields, $arrayExcept);
            $fields = array_values($fields);
        }
        if ($nameModule == 'ClientCustomService') {
            $arrayExcept = ['ip'];
            $fields = array_diff($fields, $arrayExcept);
            $fields = array_values($fields);
        }

        return $fields;
    }

    public function getColumnsNonNullableForThisModulue($module, $translatedColumns, $tabla, $model)
    {
        $fields = $module->fields()->pluck('name')->toArray();
        $model = class_basename($model);
        $rules = ComunConstantsController::RULES;
        $rules = $rules[$model];
        $nonNullableColumns = [];
        foreach ($fields as $column) {
            if (isset($rules[$column]) && (strpos($rules[$column], 'required') !== false)) {
                $nonNullableColumns[] = $column;
            }
        }
        foreach ($nonNullableColumns as $key => $value) {
            if (isset($translatedColumns[$tabla][$value]) && $translatedColumns[$tabla][$value] == "") {
                $value = $value;
            } else if (!isset($translatedColumns[$tabla][$value])) {
                $value = $value;
            } else {
                $value = $translatedColumns[$tabla][$value] ?? ($translatedColumns[$tabla][$value] == "" ? $value : $translatedColumns[$tabla][$value]);
            }
            $nonNullableColumns[$key] = $value;
        }


        $nonNullableColumns = $this->addNonNullableColumnsThatDoNotExistInModel($model, $nonNullableColumns);


        return $nonNullableColumns;
    }

    public function addNonNullableColumnsThatDoNotExistInModel($model, $nonNullableColumns)
    {
        if ($model == 'Client') { //TODO Quitar despues de la primera importacion
            $nonNullableColumns[] = 'client_id_old';
        }
        if ($model == 'Transaction' || $model == 'Payment' || $model == 'ClientInternetService' || $model == 'ClientVozService' || $model == 'ClientCustomService') {
            $nonNullableColumns[] = 'client_id';
        }
        return $nonNullableColumns;
    }

    public function getColumnsEnumnsForThisModule($module, $translatedColumns, $tabla)
    {
        $typeFieldSelect = FieldType::FIELD_SELECTS;
        $typesSelect = [];
        foreach ($typeFieldSelect as $value) {
            $typeFieldRepository = new FieldTypeRepository();
            $typesSelect[] =  $typeFieldRepository->getIdByName($value);
        }
        $enumColumns = [];

        $enums = $module->fields()->whereIn('type', $typesSelect)->get();
        foreach ($enums as $value) {
            $name = $value->name;
            $options = $value->options;
            $search = $value->search;
            $id = $value->id;
            if ($name != 'colony_id') {
                if (!empty($search)) {
                    $enumColumns[$translatedColumns[$tabla][$name] ?? $name] = $this->getSearch($search);
                } elseif (!empty($options)) {
                    $enumColumns[$translatedColumns[$tabla][$name] ?? $name] = $this->getOptions($options);
                }
            }
        }

        if ($module->name == "ClientInternetService" || $module->name == "ClientCustomService") {
            $enumColumns = $this->getEnumColumnsForClientService($enumColumns, $translatedColumns, $tabla);
        }

        return $enumColumns;
    }

    public function getColumnsBooleanForThisModule($module, $translatedColumns, $tabla)
    {
        $typeFieldCheckboxs = FieldType::FIELD_CHECKBOXS;
        $typesCheck = [];
        foreach ($typeFieldCheckboxs as $value) {
            $typeFieldRepository = new FieldTypeRepository();
            $typesCheck[] =  $typeFieldRepository->getIdByName($value);
        }
        $checkboxes = $module->fields()->whereIn('type', $typesCheck)->get();
        $booleanColumns = [];
        foreach ($checkboxes as $value) {
            $name = $value->name;
            $booleanColumns[] = $translatedColumns[$tabla][$name] ?? $name;
        }
        return $booleanColumns;
    }



    public function getOptions($options)
    {
        $options = json_decode($options, true);
        $optionReturned = [];
        foreach ($options as $opt => $value) {
            $optionReturned[] = $value;
        }
        return $optionReturned;
    }

    public function getSearch($search)
    {
        $model = json_decode($search)->model ?? null;
        $id = json_decode($search)->id ?? null;
        $text = json_decode($search)->text ?? null;
        $scope = json_decode($search)->scope ?? null;
        $modelClass = $model;
        $modelInstance = new $modelClass;
        if ($scope) {
            $results = $modelInstance->$scope()->get()->pluck($text, $id)->toArray();
            return $results;
        }
        $results = $modelInstance->get()->pluck($text, $id)->toArray();
        return $results;
    }

    /* End Get Columns */


    /* Start Create Sheet */
    public function createNumericsSheet($spreadsheet, $numericColumns)
    {
        // Crear una segunda hoja para las columnas enteras
        $integerSheet = $spreadsheet->createSheet();
        $integerSheet->setTitle('Numeric Columns');
        $integerSheet->getTabColor()->setARGB('FF00FFFF');

        $columnData = array_map(function ($column) {
            return [$column];
        }, $numericColumns);

        $integerSheet->fromArray($columnData, null, 'A1');

        $integerSheet->getStyle('A:A')->getFont()->setColor(new Color(Color::COLOR_CYAN));
    }


    public function createNewSheet($spreadsheet, $columns, $title, $tabColor)
    {
        $integerSheet = $spreadsheet->createSheet();
        $integerSheet->setTitle($title);
        $integerSheet->getTabColor()->setARGB($tabColor);

        $columnData = array_map(function ($column) {
            return [$column];
        }, $columns);

        $integerSheet->fromArray($columnData, null, 'A1');

        $integerSheet->getStyle('A:A')->getFont()->setColor(new Color($tabColor));
    }

    public function createEnumSheet($spreadsheet, $enumColumns)
    {
        $enumSheet = $spreadsheet->createSheet();
        $enumSheet->setTitle('Enum Columns');
        $enumSheet->getTabColor()->setARGB('FF0000FF');
        $enumData = [];
        foreach ($enumColumns as $column => $options) {
            $enumData[] = array_merge([$column], $options);
        }
        if (!empty($enumData)) {
            $maxOptions = max(array_map('count', $enumData));
            $enumData = array_map(function ($row) use ($maxOptions) {
                return array_pad($row, $maxOptions, '');
            }, $enumData);

            $enumSheet->fromArray($enumData, null, 'A1');
        }
        $enumSheet->getStyle('A:A')->getFont()->setColor(new Color(Color::COLOR_BLUE));
    }

    /* End Create Sheet */

    /* Start Style Sheet */
    public function aplyStyleNonNullableColumns($columns, $nonNullableColumns, $sheet)
    {
        // Establecer estilo para las columnas no nulas Rojo
        $style = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFC7CE']],
        ];
        // Encuentra los elementos comunes entre las dos variables
        $elementosComunes = array_intersect($columns, $nonNullableColumns);
        foreach ($elementosComunes as $columnIndex => $column) {
            $cellCoordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex + 1);
            $sheet->getStyle($cellCoordinate . '1')->applyFromArray($style);
        }
    }

    public function aplyStyleEnumColumns($columns, $enumColumns, $sheet)
    {
        // Establecer estilo para las columnas enumeradas Azul
        $style = [
            'font' => ['bold' => true, 'color' => ['rgb' => '0000FF']],
        ];
        foreach ($columns as $columnIndex => $column) {
            if ($column == 'state_id') {
                $column = 'Estado';
            }
            if ($column == 'municipality_id') {
                $column = 'Municipio';
            }
            if (isset($enumColumns[$column])) {
                $cellCoordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex + 1);
                $sheet->getStyle($cellCoordinate . '1')->applyFromArray($style);
            }
        }
    }

    public function aplyStylebooleanColumns($columns, $booleanColumns, $sheet)
    {
        // Establecer estilo para las columnas no nulas Rojo
        $style = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFF00FF']],
        ];
        $elementosComunes = array_intersect($columns, $booleanColumns);
        foreach ($elementosComunes as $columnIndex => $column) {
            $cellCoordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex + 1);
            $sheet->getStyle($cellCoordinate . '1')->applyFromArray($style);
        }
    }


    public function aplyStyleNumericsColumns($columns, $numericColumns, $sheet)
    {
        // Establecer estilo para las columnas no nulas CYAN
        $style = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FF00FFFF']],
        ];
        $elementosComunes = array_intersect($columns, $numericColumns);
        foreach ($elementosComunes as $columnIndex => $column) {
            $cellCoordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex + 1);
            $sheet->getStyle($cellCoordinate . '1')->applyFromArray($style);
        }
    }

    public function applyCombinedStyle($columns, $fontText, $baseText, $sheet, $fontColor, $baseColor)
    {
        $style = [
            'font' => ['bold' => true, 'color' => ['rgb' => $fontColor]],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => $baseColor],
            ],
        ];
        $arrayColumns = array_merge(array_intersect($fontText, $baseText));
        $elementosComunes = array_intersect($columns, $arrayColumns);
        foreach ($elementosComunes as $columnIndex => $column) {
            $cellCoordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex + 1);
            $sheet->getStyle($cellCoordinate . '1')->applyFromArray($style);
        }
    }

    public function applyCombinedStyleWithEnumn($columns, $fontText, $baseText, $sheet, $fontColor, $baseColor)
    {
        $style = [
            'font' => ['bold' => true, 'color' => ['rgb' => $fontColor]],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => $baseColor],
            ],
        ];
        $columnsEnum = [];
        foreach ($baseText as $column => $key) {
            $columnsEnum[] = $column;
        }
        $arrayColumns = array_merge(array_intersect($fontText, $columnsEnum));
        $elementosComunes = array_intersect($columns, $arrayColumns);
        foreach ($elementosComunes as $columnIndex => $column) {
            $cellCoordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex + 1);
            $sheet->getStyle($cellCoordinate . '1')->applyFromArray($style);
        }
    }
    /* End Style Sheet */

    /* Functions */

    public function getLabelColumnsForThisModuleInColumnsDatatableModules($tabla)
    {
        $translates = trans('translation_columns_table');
        $columns = Schema::getColumnListing($tabla);
        foreach ($columns as $key => $value) {
            $columns[$key] = $translates[$tabla][$value] ?? $value;
        }
        return $columns;
    }

    public function tableIsClient($tabla)
    {
        return $tabla == 'clients';
    }

    public function getTableForThisModel($model)
    {
        $modelo = new $model();
        return $modelo->getTable();
    }


    public function moduleRequireClientId($nameModule)
    {
        return $nameModule == 'ClientPayment' || $nameModule == 'ClientTransaction';
    }

    public function getEnumColumnsForClientService($enumColumns, $translatedColumns, $tabla)
    {
        $routers = json_encode(
            [
                'model' => 'App\Models\Router',
                'id' => 'id',
                'text' => 'title'
            ]
        );
        $enumColumns[$translatedColumns[$tabla]['router_id']] = $this->getSearch($routers);
        $ipv4Assigment = json_encode([
            "IP Estatica" => "IP Estatica",
            "Pool IP" => "Pool IP",
        ]);
        $enumColumns[$translatedColumns[$tabla]['ipv4_assignment']] = $this->getOptions($ipv4Assigment);

        $poolsIp = json_encode(
            [
                'model' => 'App\Models\Network',
                'id' => 'id',
                'text' => 'network',
                'scope' => 'isPool'
            ]
        );
        $enumColumns[$translatedColumns[$tabla]['ipv4_pool']] = $this->getSearch($poolsIp);

        return $enumColumns;
    }


    public function processInputImportByModule($input, $module)
    {
        $input = $this->processEnumFields($input, $module);
        $input = $this->processCheckboxFields($input, $module);
        return $input;
    }

    public function processCheckboxFields($input, $module)
    {
        $typeCheckboxFields = FieldType::FIELD_CHECKBOXS;
        $typesCheck = [];
        foreach ($typeCheckboxFields as $value) {
            $typeFieldRepository = new FieldTypeRepository();
            $typesCheck[] =  $typeFieldRepository->getIdByName($value);
        }
        $checkboxes = $module->fields()
            ->whereIn('type', $typesCheck)
            ->get();
        $valoresAdmitidosPositivos = ['si', 'yes', 'true', 1];
        $valoresAdmitidosNegativos = ['no', 'not', 'false', 0];
        foreach ($checkboxes as $check) {
            $name = $check->name;
            if (isset($input[$name])) {
                $value = $input[$name];
                if (in_array(strtolower($value), $valoresAdmitidosPositivos)) {
                    $input[$name] = 1;
                } elseif (in_array(strtolower($value), $valoresAdmitidosNegativos)) {
                    $input[$name] = 0;
                } else {
                    $input[$name] = $check->default_value ?? null;
                }
            }
        }
        return $input;
    }

    public function processEnumFields($input, $module)
    {
        $typeFieldSelect = FieldType::FIELD_SELECTS;
        $typesSelect = [];
        foreach ($typeFieldSelect as $value) {
            $typeFieldRepository = new FieldTypeRepository();
            $typesSelect[] =  $typeFieldRepository->getIdByName($value);
        }
        $enums = $module->fields()
            ->whereIn('type', $typesSelect)
            ->where(function ($query) {
                $query->whereNotNull('search')
                    ->orWhereNotNull('options');
            })
            ->get();

        foreach ($enums as $enum) {
            $name = $enum->name;
            if (isset($input[$name])) {
                $options = $enum->options;
                $search = $enum->search;
                if (!empty($search)) {
                    $search = json_decode($enum->search, true);
                    $model = $search['model'];
                    $column = $search['text'];
                    if (!is_numeric($input[$name])) {
                        $input[$name] = $model::whereRaw('LOWER(' . $column . ') = ?', [strtolower($input[$name])])->first()->id ?? null;
                    } else {
                        $input[$name] = $input[$name];
                    }
                } elseif (!empty($options)) {
                    $options = json_decode($options, true);
                    foreach ($options as $opt => $value) {
                        if ($input[$name] == $value || $input[$name] == $opt) {
                            $input[$name] = $opt;
                        }
                    }
                } else {
                    $input[$name] = $enum->default_value ?? null;
                }
            }
        }
        if ($module->name == 'ClientInternetService' || $module->name == 'ClientCustomService') {
            $routerRepository = new RouterRepository();
            $routerId = $routerRepository->getRouterByTitle($input['router_id'])->id ?? null;
            $input['router_id'] = $routerId;

            $networkRepository = new NetworkRepository();
            $networkId = $networkRepository->getNetworkByNetwork($input['ipv4_pool'])->id ?? null;
            $input['ipv4_pool'] = $networkId;
        }

        return $input;
    }

    public function formatearFechaAnoMesDiaHMS($fechaOriginal)
    {
        $timestamp = strtotime($fechaOriginal);
        if ($timestamp !== false) {
            $fechaFormateada = date('Y-m-d H:i:s', $timestamp);
            return $fechaFormateada;
        }
        return Carbon::now()->toDateTimeString();
    }
}
