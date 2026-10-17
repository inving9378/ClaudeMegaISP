<?php

namespace Tests\Unit\Support;

use App\Support\Clabe;
use PHPUnit\Framework\TestCase;

/** FAC-13 (item #521): dígito de control de la CLABE (ejemplos públicos válidos). */
class ClabeTest extends TestCase
{
    public function test_valida_y_rechaza(): void
    {
        $this->assertTrue(Clabe::valida('002010077777777771'));
        $this->assertTrue(Clabe::valida('032180000118359719'));
        $this->assertFalse(Clabe::valida('0021 8000 0118 3597 19'), 'control que no cuadra');
        foreach (['002010077777777770', '00201007777777777', '0020100777777777711', 'abcdefghijklmnopqr', '', null] as $mala) {
            $this->assertFalse(Clabe::valida($mala), var_export($mala, true));
        }
        $this->assertTrue(Clabe::valida('0020 1007 7777 7777 71'), 'tolera espacios');
    }
}
