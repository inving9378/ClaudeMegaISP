<?php

namespace App\Services\Asistente\Datos;

/** Una consulta que no se corre (o que falló). El mensaje es para la IA, que corrige o le explica al usuario. */
class ConsultaRechazada extends \RuntimeException
{
}
