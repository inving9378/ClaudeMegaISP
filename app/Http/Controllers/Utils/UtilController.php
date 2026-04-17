<?php

namespace App\Http\Controllers\Utils;

use App\Http\Controllers\Controller;
use App\Models\Receipt;
use Carbon\Carbon;

class UtilController extends Controller
{
    public function getPaymentPeriod()
    {
        $actualMonth = Carbon::now()->monthName;
        $nextMonth = Carbon::now()->addMonth()->monthName;
        return $actualMonth . ' - ' . $nextMonth;
    }

    public function getRecordsCsv($dir)
    {
        $file = fopen($dir, "r");

        $total_records = -1;
        $records = [];
        $csv_headers = [];
        while (($data = fgetcsv($file, 100000, ",")) !== FALSE) {
            if ($total_records < 0) {
                $total_records++;
                $csv_headers[] = $data;
                continue;
            }

            if (count($data) == 0 || is_null($data)) {
                $total_records++;
                continue;
            } else {
                $records[] = array_combine($csv_headers[0], $data);
            }
            $total_records++;
        }

        return [
            'csv_headers' => $csv_headers,
            'records' => $records,
            'total_records' => $total_records,
        ];
    }

    public static function getDateFormatted($date)
    {
        $formats = ComunConstantsController::FORMAT_DATES;
        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $date);
            } catch (\Exception $exception) {
            }
        }
        return null;
    }
}
