<?php

namespace App\Traits;

trait CleanSignals
{
    public function cleanSignal($signal)
    {
        return ($signal && $signal !== '-')
            ? str_replace([' dBm', ' '], '', $signal)
            : null;
    }
}
