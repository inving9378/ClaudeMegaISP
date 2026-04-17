<?php

namespace App\Observers;

use App\Models\File;

class FileObserver
{
    /**
     * Handle the File "deleted" event.
     *
     * @param \App\Models\File $file
     * @return void
     */
    public function deleted(File $file)
    {
        if (isset($file->path)) {
            $dir = str_replace('/storage', 'app/public', $file->path);
            unlink(storage_path($dir));
        }
    }
}
