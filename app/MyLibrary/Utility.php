<?php


namespace App\MyLibrary;

use App\Http\Repository\FieldTypeRepository;
use App\Models\Module;
use Illuminate\Support\Facades\Log;

class Utility
{
    public static function modifyValueForCheckbox($input, $module)
    {
        $module = Module::where('name', $module)->with('fields')->first();
      
         $typeFieldRepository = new FieldTypeRepository();
         $inputCheckbox = $typeFieldRepository->getIdByName('input-checkbox');
         $typeFieldRepository = new FieldTypeRepository();
         $inputCheckboxWithInputs = $typeFieldRepository->getIdByName('input-checkbox-with-inputs');

        $fields = $module->fields
            ->whereIn('type', [$inputCheckbox, $inputCheckboxWithInputs])
            ->pluck('name', 'type')->toArray();
        foreach ($fields as $field) {
            $input[$field] = (isset($input[$field]) && $input[$field] == true);
        }

        return $input;
    }
}
