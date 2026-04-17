<?php

namespace App\Http\Traits;

use App\Models\Module;
use Illuminate\Support\Str;

trait IncludeLibraryTrait {
    public function includeLibraryDinamic($model){

        $name_model = Str::after($model,'Models\\');
        $module = Module::where('name',$name_model)->first();
        if ($module){
            $this->data['packages']['css'] = [];
            $packages = $module->packages()->get();
            foreach ($packages->where('type','css') as $package){
                $this->data['packages']['css'][] = $package;
            }

            $this->data['packages']['js'] = [];
            foreach ($packages->where('type','js') as $package){
                $this->data['packages']['js'][] = $package;
            }
        }
    }
}
