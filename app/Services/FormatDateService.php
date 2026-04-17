<?php

namespace App\Services;

use App\Models\Interface\ServiceInterface;
use Carbon\Carbon;
use Exception;

class FormatDateService
{
    protected $date;
    protected $formatWithTime;
    protected $formatWithoutTime;

    public function __construct($date)
    {
        $this->date = $date;
        $this->formatWithTime = 'd-m-Y H:i:s';
        $this->formatWithoutTime = 'd-m-Y';
    }

    public function formatDate()
    {
        // Si $date es null, devolver una cadena vacía
        if (is_null($this->date)) {
            return '';
        }

        try {
            // Si $date es una instancia de Carbon o DateTime, formatearla directamente
            if ($this->date instanceof Carbon || $this->date instanceof \DateTime) {
                return $this->date->format($this->formatWithoutTime);
            }

            // Si $date es un número, asumir que es un timestamp UNIX
            if (is_numeric($this->date)) {
                return Carbon::createFromTimestamp($this->date)->format($this->formatWithoutTime);
            }

            // Si $date es una cadena de texto en el formato 'Y-m-d'
            if (is_string($this->date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date)) {
                return Carbon::createFromFormat('Y-m-d', $this->date)->format($this->formatWithoutTime);
            }

            // Si $date es una cadena de texto en el formato 'Y-m-d H:i:s'
            if (is_string($this->date) && preg_match('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $this->date)) {
                return Carbon::createFromFormat('Y-m-d H:i:s', $this->date)->format($this->formatWithoutTime);
            }

            // Si $date es una cadena de texto en el formato 'Y-m-d H:i'
            if (is_string($this->date) && preg_match('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}/', $this->date)) {
                return Carbon::createFromFormat('Y-m-d H:i', $this->date)->format($this->formatWithoutTime);
            }

            // Si $date es una cadena de texto en formato ISO 8601 (como '2024-11-06T17:59:37.615Z')
            if (is_string($this->date) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d+Z$/', $this->date)) {
                return Carbon::parse($this->date)->format($this->formatWithoutTime);
            }

            // Si $date es una cadena de texto en formato ISO 8601 sin segundos ni milisegundos (como '2025-02-10T10:45')
            if (is_string($this->date) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $this->date)) {
                return Carbon::createFromFormat('Y-m-d\TH:i', $this->date)->format($this->formatWithoutTime);
            }
            // Si $date es una cadena de texto en el formato 'd/m/Y' (como '12/05/2023')
            if (is_string($this->date) && preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $this->date)) {
                return Carbon::createFromFormat('d/m/Y', $this->date)->format($this->formatWithoutTime);
            }

            // Si $date es una cadena con formato de texto largo en inglés y con zona horaria
            if (is_string($this->date)) {
                // Limpiar el texto de la zona horaria
                $cleanedDate = preg_replace('/\sGMT[-+]\d{4}.*$/', '', $this->date);

                // Intentar crear una instancia de Carbon desde la cadena limpiada
                $date = Carbon::createFromFormat('D M d Y H:i:s', $cleanedDate);
                if ($date) {
                    return $date->format($this->formatWithoutTime);
                }
            }

            // Si $date es una cadena de texto en otro formato, intentar parsearla
            $date = Carbon::parse($this->date);
            return $date->format($this->formatWithoutTime);
        } catch (Exception $e) {
            // En caso de error, retornar un mensaje o manejar el error según sea necesario
            return $this->date;
        }
    }



    public function formatDateWithTime()
    {
        // Si $date es null, devolver una cadena vacía
        if (is_null($this->date)) {
            return '';
        }

        // Definir el formato con hora en 12 horas (añade 'h' para horas 1-12 y 'A' para AM/PM)
        $twelveHourFormat = 'Y-m-d h:i:s A'; // o el formato que prefieras

        try {
            // Si $date es una instancia de Carbon o DateTime, formatearla directamente
            if ($this->date instanceof Carbon || $this->date instanceof \DateTime) {
                return $this->date->format($twelveHourFormat);
            }

            // Si $date es un número, asumir que es un timestamp UNIX
            if (is_numeric($this->date)) {
                return Carbon::createFromTimestamp($this->date)->format($twelveHourFormat);
            }

            // Si $date es una cadena de texto en el formato 'Y-m-d'
            if (is_string($this->date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date)) {
                return Carbon::createFromFormat('Y-m-d', $this->date)->format($twelveHourFormat);
            }

            // Si $date es una cadena de texto en el formato 'Y-m-d H:i:s'
            if (is_string($this->date) && preg_match('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $this->date)) {
                return Carbon::createFromFormat('Y-m-d H:i:s', $this->date)->format($twelveHourFormat);
            }

            // Si $date es una cadena de texto en el formato 'Y-m-d H:i'
            if (is_string($this->date) && preg_match('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}/', $this->date)) {
                return Carbon::createFromFormat('Y-m-d H:i', $this->date)->format($twelveHourFormat);
            }

            // Si $date es una cadena de texto en formato ISO 8601 (como '2024-11-06T17:59:37.615Z')
            if (is_string($this->date) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d+Z$/', $this->date)) {
                return Carbon::parse($this->date)->format($twelveHourFormat);
            }

            // Si $date es una cadena con formato de texto largo en inglés y con zona horaria
            if (is_string($this->date)) {
                // Limpiar el texto de la zona horaria
                $cleanedDate = preg_replace('/\sGMT[-+]\d{4}.*$/', '', $this->date);

                // Intentar crear una instancia de Carbon desde la cadena limpiada
                $date = Carbon::createFromFormat('D M d Y H:i:s', $cleanedDate);
                if ($date) {
                    return $date->format($twelveHourFormat);
                }
            }

            // Si $date es una cadena de texto en otro formato, intentar parsearla
            $date = Carbon::parse($this->date);
            return $date->format($twelveHourFormat);
        } catch (Exception $e) {
            // En caso de error, retornar un mensaje o manejar el error según sea necesario
            return 'Invalid date format';
        }
    }
}
