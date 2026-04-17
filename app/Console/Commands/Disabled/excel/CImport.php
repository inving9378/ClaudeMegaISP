<?php

namespace App\Console\Commands\Disabled\excel;

use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToArray;

class CImport implements ToArray
{
    use Importable;

    public function array(array $array)
    {
    }
}
