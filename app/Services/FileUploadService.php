<?php

namespace App\Services;

use Intervention\Image\Facades\Image;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class FileUploadService
{
    public function uploadImage($file, $path)
    {
        try {
            // Validar que el archivo es una imagen
            if (!in_array(strtolower($file->extension()), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                throw new Exception('El archivo no es una imagen válida.');
            }

            // Generar un nombre único para la imagen
            $nameImage = Str::uuid() . '.' . strtolower($file->extension());

            // Crear la ruta del almacenamiento
            $userDirectory = "uploads/{$path}/";
            $fullDirectoryPath = storage_path("app/public/{$userDirectory}");
            $imagePath = $userDirectory . $nameImage;

            // Asegurar que el directorio existe con permisos adecuados
            if (!File::exists($fullDirectoryPath)) {
                try {
                    // Crear directorio recursivamente con permisos
                    if (!File::makeDirectory($fullDirectoryPath, 0775, true)) {
                        throw new Exception("No se pudo crear el directorio: {$fullDirectoryPath}");
                    }

                    // Verificar que el directorio se creó antes de cambiar permisos
                    if (File::exists($fullDirectoryPath)) {
                        chmod($fullDirectoryPath, 0775);
                    } else {
                        throw new Exception("El directorio no se creó: {$fullDirectoryPath}");
                    }
                } catch (Exception $e) {
                    throw new Exception("Error al crear directorio: " . $e->getMessage());
                }
            }

            // Verificar que el directorio es escribible
            if (!is_writable($fullDirectoryPath)) {
                throw new Exception("El directorio {$fullDirectoryPath} no tiene permisos de escritura. Permisos actuales: " . substr(sprintf('%o', fileperms($fullDirectoryPath)), -4));
            }

            // Procesar y ajustar la imagen con Intervention Image
            $image = Image::make($file);

            // Guardar la imagen usando el almacenamiento de Laravel
            $fullImagePath = $fullDirectoryPath . $nameImage;
            $image->save($fullImagePath);

            // Verificar que el archivo se creó correctamente
            if (!file_exists($fullImagePath)) {
                throw new Exception("No se pudo guardar la imagen en {$fullImagePath}");
            }

            // Cambiar permisos del archivo subido
            chmod($fullImagePath, 0664);

            return $imagePath;
        } catch (Exception $e) {
            Log::error("Error al subir la imagen: " . $e->getMessage(), [
                'file' => $file->getClientOriginalName(),
                'path' => $path,
                'error' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    public function uploadFile($file, $directory = '')
    {
        // Definir la ruta base (uploads)
        $basePath = 'uploads';

        // Si se especifica un subdirectorio, lo añadimos a la ruta base
        $fullPath = $directory ? $basePath . '/' . $directory : $basePath;

        // Asegurarse de que el directorio exista (si no existe, se crea)
        Storage::disk('local')->makeDirectory($fullPath);

        // Generar un nombre único para el archivo
        $fileName = time() . '_' . $file->getClientOriginalName();

        // Definir la ruta completa del archivo
        $filePath = $fullPath . '/' . $fileName;

        // Guardar el archivo en el disco 'local' (storage/app)
        Storage::disk('local')->put($filePath, file_get_contents($file));
        $formattedPath = '/storage/app/' . $filePath;

        // Obtener las propiedades del archivo
        $properties = [
            'name' => $file->getClientOriginalName(), // Nombre original del archivo
            'type' => $file->getClientMimeType(),     // Tipo MIME del archivo
            'path' => $formattedPath,                      // Ruta del archivo
            'size' => $file->getSize(),               // Tamaño del archivo en bytes
        ];

        // Devolver el array con las propiedades
        return $properties;
    }


    public function uploadVideo($file, $directory = '')
    {
        // Definir la ruta base (uploads)
        $basePath = 'uploads';
        $fullPath = $directory ? $basePath . '/' . $directory : $basePath;

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        // Asegurar que el directorio exista
        $disk->makeDirectory($fullPath);

        // Generar un nombre único para el archivo
        $fileName = time() . '_' . $file->getClientOriginalName();

        // Guardado seguro sin cargar el archivo completo en memoria (ideal para videos)
        $disk->putFileAs($fullPath, $file, $fileName);

        // Obtener la URL pública (gracias a php artisan storage:link)
        $publicUrl = $disk->url($fullPath . '/' . $fileName);

        // Devolver las propiedades del archivo subido
        return [
            'name' => $file->getClientOriginalName(),
            'type' => $file->getClientMimeType(),
            'path' => $publicUrl,
            'size' => $file->getSize(),
        ];
    }



    public function uploadImageAndReturnProperties($file, $path)
    {
        $url = $this->uploadImage($file, $path);
        $properties = [
            'name' => $file->getClientOriginalName(), // Nombre original del archivo
            'type' => $file->getClientMimeType(),     // Tipo MIME del archivo
            'path' => $url,                      // Ruta del archivo
            'size' => $file->getSize(),               // Tamaño del archivo en bytes
        ];
        return $properties;
    }
}
