<?php

namespace App\Modules\Addons\Marketing\Services\Tts\Support;

class SpeechTextPreprocessor
{
    public function preprocess(string $text): string
    {
        $text = $this->formatPhoneNumbers($text);
        $text = $this->formatStandaloneNumbers($text);
        $text = $this->expandCommonAbbreviations($text);
        return $text;
    }

    protected function formatPhoneNumbers(string $text): string
    {
        $mode = \App\Models\Marketing\Setting::get('tts_phone_format', 1) ?? 'digit_by_digit';

        return preg_replace_callback(
            '/(\+?\d[\d\s\-]{7,15}\d)/',
            function ($match) use ($mode) {
                $raw     = $match[1];
                $hasPlus = str_starts_with(trim($raw), '+');
                $digits  = preg_replace('/\D/', '', $raw);

                if (strlen($digits) < 8) {
                    return $raw;
                }

                $prefix = '';
                if ($hasPlus && str_starts_with($digits, '52') && strlen($digits) >= 12) {
                    $prefix = 'más cincuenta y dos, ';
                    $digits = substr($digits, 2);
                }

                if ($mode === 'natural') {
                    return $raw;
                }

                if ($mode === 'digit_by_digit') {
                    static $words = [
                        '0' => 'cero',  '1' => 'uno',   '2' => 'dos',   '3' => 'tres',
                        '4' => 'cuatro','5' => 'cinco',  '6' => 'seis',  '7' => 'siete',
                        '8' => 'ocho',  '9' => 'nueve',
                    ];
                    $spelled = array_map(fn($d) => $words[$d] ?? $d, str_split($digits));
                    return $prefix . implode(', ', $spelled);
                }

                // 'groups_of_2' (default legacy)
                $groups = [];
                $i = 0;
                while ($i < strlen($digits)) {
                    $remaining = strlen($digits) - $i;
                    $chunk     = $remaining === 3 ? 3 : ($remaining >= 2 ? 2 : $remaining);
                    $groups[]  = substr($digits, $i, $chunk);
                    $i        += $chunk;
                }
                return $prefix . implode(', ', $groups);
            },
            $text
        );
    }

    protected function formatStandaloneNumbers(string $text): string
    {
        return preg_replace_callback(
            '/\b(\d{5,7})\b/',
            function ($match) {
                return implode(', ', str_split($match[1]));
            },
            $text
        );
    }

    protected function expandCommonAbbreviations(string $text): string
    {
        $replacements = [
            '/\bMbps\b/i'  => 'megas por segundo',
            '/\bMB\/s\b/i' => 'megabytes por segundo',
            '/\bGB\b/i'    => 'gigas',
            '/\bMB\b/i'    => 'megabytes',
            '/\bKB\b/i'    => 'kilobytes',
            '/\bISP\b/i'   => 'proveedor de internet',
            '/\bWiFi\b/i'  => 'wifi',
            '/\bIP\b/i'    => 'I P',
            '/\bTV\b/i'    => 'tele',
            '/\$(\d+)/i'   => '$1 pesos',
            '/&/'          => ' y ',
            '/%/'          => ' por ciento',
        ];

        return preg_replace(array_keys($replacements), array_values($replacements), $text);
    }
}
