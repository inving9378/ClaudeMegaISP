<?php

namespace App\Http\Traits;

trait SmartOltTrait
{

    public function cleanSignal($signal)
    {
        return ($signal && $signal !== '-')
            ? str_replace([' dBm', ' '], '', $signal)
            : null;
    }

    public function extractClientId($name)
    {
        if (!in_array($name, ['Local2', 'BODEGA MEGANET 1'])) {
            preg_match_all('/\d+/', $name, $matches);
            if (empty($matches[0])) {
                return null;
            }
            $ids = array_map('intval', $matches[0]);
            return max($ids);
        }
        return null;
    }
}
