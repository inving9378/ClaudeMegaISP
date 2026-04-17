<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;

class FileController extends Controller
{
    public function processSingleFileAndReturnProperties($file, $model_path, $id){
        $extension = $file->getClientOriginalExtension();
        $name = Str::slug($file->getClientOriginalName());
        $name_to_save = Str::replaceLast($extension, '.'.$extension, $name);
        $path = '/storage/'.$model_path.'/'.$id.'/'.$name_to_save;
        $size = $file->getSize();

        return [
            'name' => $name_to_save,
            'type' => $extension,
            'path' => $path,
            'size' => $size,
            'preview' => false
        ];
    }


    public function processSingleFileImporAndReturnProperties($file, $model_path){
        $extension = $file->getClientOriginalExtension();
        $name = Str::slug($file->getClientOriginalName());
        $name_to_save = Str::replaceLast($extension, '.'.$extension, $name);
        $path = '/storage/'.$model_path.'/'.$name_to_save;
        $size = $file->getSize();

        return [
            'name' => $name_to_save,
            'type' => $extension,
            'path' => $path,
            'size' => $size,
            'preview' => false
        ];
    }
}
