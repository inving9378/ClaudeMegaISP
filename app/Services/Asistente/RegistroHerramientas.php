<?php

namespace App\Services\Asistente;

use App\Models\User;
use App\Services\Asistente\Herramientas;
use App\Services\Asistente\Herramientas\Herramienta;

/**
 * Catálogo de herramientas de JARVIS. Para darle una capacidad nueva: crear la clase en
 * Herramientas/ (con su permiso) y sumarla aquí. limites.json decide si se ejecuta sola, pide
 * confirmación o queda bloqueada.
 */
class RegistroHerramientas
{
    private const CLASES = [
        Herramientas\ConsultarGuia::class,
        Herramientas\BuscarEnManual::class,
        Herramientas\BuscarCliente::class,
        Herramientas\ListarClientes::class,
        Herramientas\ListarProspectos::class,
        Herramientas\BuscarColonia::class,
        Herramientas\FichaCliente::class,
        Herramientas\PagosCliente::class,
        Herramientas\FacturasCliente::class,
        Herramientas\TicketsCliente::class,
        Herramientas\ResumenTickets::class,
        Herramientas\ListarTickets::class,
        Herramientas\ResumenPagosDelDia::class,
        Herramientas\ListarPagos::class,
        Herramientas\Recordar::class,
        Herramientas\ActualizarContactoCliente::class,
        Herramientas\CrearTicket::class,
        Herramientas\CrearProspecto::class,
        Herramientas\CrearCliente::class,
        Herramientas\AnotarPendiente::class,
    ];

    /** @var array<string, Herramienta>|null */
    private ?array $todas = null;

    public function __construct(private ConocimientoAsistente $conocimiento)
    {
    }

    /** @return array<string, Herramienta> */
    public function todas(): array
    {
        if ($this->todas === null) {
            $this->todas = [];
            foreach (self::CLASES as $clase) {
                $h = new $clase($this->conocimiento);
                $this->todas[$h->nombre()] = $h;
            }
        }

        return $this->todas;
    }

    /** Las que este usuario puede usar: no bloqueadas en limites.json y con su permiso. */
    public function disponiblesPara(User $user): array
    {
        return array_filter(
            $this->todas(),
            fn (Herramienta $h) => $this->conocimiento->modoDe($h->nombre(), $h->esCambio()) !== 'bloqueada'
                && PermisosAsistente::puede($user, $h->permiso())
        );
    }

    public function obtener(string $nombre): ?Herramienta
    {
        return $this->todas()[$nombre] ?? null;
    }
}
