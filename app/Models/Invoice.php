<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'number',
        'client_id',
        'client_invoice_id',
        'transaction_id',
        'payment_id',
        'due_date',
        'payment_date',
        'is_sent',
        'subtotal',
        'tax',
        'total',
        'pending_balance',
        'status',
        'payment_method',
        'notes',
        'type',
        'period',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_sent' => 'boolean',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'pending_balance' => 'decimal:2'
    ];

    /**
     * Estados posibles de una factura
     */
    const STATUS_DRAFT = 'draft'; // emitida
    const STATUS_ISSUED = 'issued'; // enviada
    const STATUS_PARTIALLY_PAID = 'partially_paid'; // parcialmente pagada
    const STATUS_PAID = 'paid'; // pagada
    const STATUS_OVERDUE = 'overdue'; // vencida
    const STATUS_CANCELLED = 'cancelled'; // cancelada

    /**
     * Tipos de factura
     */
    const TYPE_PAYMENT = 'payment';
    const TYPE_PROFORMA = 'proforma';

    /**
     * Documento vigente de la factura (item #505): UN solo documento por factura según su estado.
     * Lo decide documentoVigente() y lo replica en SQL filtrarPorDocumento(); los dos viven aquí y
     * nada más decide qué documento corresponde (ícono único, Enviar, columna «Documento»).
     * NO depende de `type` (proforma/payment): ese campo lo usan InvoiceService y
     * ContratableTrialService para no duplicar proformas y no se toca al pagar.
     */
    const DOC_PREFACTURA = 'prefactura'; // emitida / enviada / parcial / vencida
    const DOC_FACTURA = 'factura';       // pagada sin CFDI (sin valor fiscal)
    const DOC_CFDI = 'cfdi';             // pagada con CFDI timbrado

    /** Etiqueta del badge de la columna «Documento» (colores: clases .doc-factura--{documento}). */
    const DOCUMENTOS = [
        self::DOC_PREFACTURA => ['etiqueta' => 'Proforma'],
        self::DOC_FACTURA    => ['etiqueta' => 'Factura s/v fiscal'],
        self::DOC_CFDI       => ['etiqueta' => 'Factura CFDI'],
    ];

    /** Columnas de la tabla que no existen en la BD (no se buscan, filtran ni ordenan por SQL). */
    const COLUMNAS_CALCULADAS = ['action', 'documento'];

    /**
     * `payment_method` guarda el id de method_of_payments como texto… o la marca 'retroactive' que
     * puso CreateProformaInvoiceOverdueClientActiveCommand (71,337 facturas del 2025-09-07, sin pago
     * ligado). Se muestra el nombre del catálogo; 'retroactive' → «Retroactiva»; vacío o id que no
     * existe → «—» (item #505).
     */
    const METODO_RETROACTIVO = 'retroactive';

    protected $appends = ['status_name'];

    /**
     * Relación con el cliente
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * Relación morph a servicios (paralela a ClientInvoice::client_bundle_service()
     * vía client_serviceables, item roadmap #719 — Fase 1, sin consumidores todavía).
     */
    public function client_bundle_service()
    {
        return $this->morphedByMany(\App\Models\ClientBundleService::class, 'invoice_serviceable');
    }

    public function client_internet_service()
    {
        return $this->morphedByMany(\App\Models\ClientInternetService::class, 'invoice_serviceable');
    }

    public function client_voz_service()
    {
        return $this->morphedByMany(\App\Models\ClientVozService::class, 'invoice_serviceable');
    }

    public function client_custom_service()
    {
        return $this->morphedByMany(\App\Models\ClientCustomService::class, 'invoice_serviceable');
    }


    /**
     * Relación con la transacción
     */
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Relación con el pago
     */
    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Scope para facturas vencidas
     */
    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
            ->where('status', '!=', self::STATUS_PAID)
            ->where('status', '!=', self::STATUS_CANCELLED);
    }

    /**
     * Scope para facturas pendientes de pago
     */
    public function scopePending($query)
    {
        return $query->where('pending_balance', '>', 0)
            ->where('status', '!=', self::STATUS_CANCELLED);
    }

    /**
     * Scope para facturas no pagadas
     */

    public function scopeNotPaid($query)
    {
        return $query->where('status', '!=', self::STATUS_PAID)->where('status', '!=', self::STATUS_CANCELLED);
    }

    public function scopeProforma($query)
    {
        return $query->where('type', self::TYPE_PROFORMA);
    }

    /**
     * Scope por período
     */
    public function scopeForPeriod($query, $period)
    {
        return $query->where('period', $period);
    }

    /**
     * Verificar si la factura está vencida
     */
    public function isOverdue(): bool
    {
        return $this->due_date < now() &&
            $this->pending_balance > 0 &&
            $this->status !== self::STATUS_CANCELLED;
    }

    /**
     * Marcar factura como enviada
     */
    /** id → nombre de method_of_payments (una consulta por proceso). */
    public static function catalogoMetodosPago(): array
    {
        static $catalogo = null;

        return $catalogo ??= \DB::table('method_of_payments')->pluck('type', 'id')
            ->map(fn ($tipo) => (string) $tipo)->all();
    }

    public static function etiquetaMetodoPago($valor): string
    {
        if ($valor === self::METODO_RETROACTIVO) {
            return 'Retroactiva';
        }
        $catalogo = self::catalogoMetodosPago();

        return is_numeric($valor) && isset($catalogo[(int) $valor]) ? $catalogo[(int) $valor] : '—';
    }

    /** Ordenar la tabla por el NOMBRE del método (no por el id guardado). */
    public static function ordenMetodoPagoSql()
    {
        return \DB::raw("(CASE WHEN invoices.payment_method = '" . self::METODO_RETROACTIVO . "' THEN 'Retroactiva' "
            . 'ELSE (SELECT mp.type FROM method_of_payments mp WHERE mp.id = invoices.payment_method) END)');
    }

    /** Valores guardados cuyo NOMBRE coincide con la búsqueda (para el buscador de la tabla). */
    public static function metodosPagoQueCoinciden(string $busqueda): array
    {
        $valores = [];
        foreach (self::catalogoMetodosPago() as $id => $tipo) {
            if ($busqueda === '' || mb_stripos($tipo, $busqueda) !== false || (string) $id === $busqueda) {
                $valores[] = (string) $id;
            }
        }
        if ($busqueda === '' || mb_stripos('Retroactiva', $busqueda) !== false) {
            $valores[] = self::METODO_RETROACTIVO;
        }

        return $valores;
    }

    /** null = cancelada (no tiene documento que mostrar ni enviar). */
    public function documentoVigente(): ?string
    {
        if ($this->status === self::STATUS_CANCELLED) {
            return null;
        }
        if ($this->status === self::STATUS_PAID) {
            return $this->hasCfdi() ? self::DOC_CFDI : self::DOC_FACTURA;
        }

        return self::DOC_PREFACTURA;
    }

    /**
     * ¿Tiene CFDI timbrado vigente? Preparado para la Fase 4 (#483): hoy los CFDI que se adjuntan a
     * mano (client_invoice_cfdi) se ligan a client_invoices y `invoices.client_invoice_id` está vacío
     * en todas las facturas, así que siempre es false. Debe coincidir con filtrarPorDocumento().
     */
    public function hasCfdi(): bool
    {
        if (empty($this->client_invoice_id)) {
            return false;
        }

        return ClientInvoiceCfdi::where('client_invoice_id', $this->client_invoice_id)
            ->whereNull('cancelado_at')
            ->exists();
    }

    /** SQL equivalente a documentoVigente() para filtrar la tabla por documento. */
    public static function filtrarPorDocumento($query, array $documentos)
    {
        $conCfdi = function ($q) {
            $q->select(\DB::raw(1))->from('client_invoice_cfdi')
                ->whereColumn('client_invoice_cfdi.client_invoice_id', 'invoices.client_invoice_id')
                ->whereNull('client_invoice_cfdi.cancelado_at')
                ->whereNull('client_invoice_cfdi.deleted_at');
        };

        return $query->where(function ($q) use ($documentos, $conCfdi) {
            foreach (array_intersect($documentos, array_keys(self::DOCUMENTOS)) as $documento) {
                $q->orWhere(function ($q) use ($documento, $conCfdi) {
                    if ($documento === self::DOC_PREFACTURA) {
                        $q->where(fn ($w) => $w->whereNull('status')
                            ->orWhereNotIn('status', [self::STATUS_PAID, self::STATUS_CANCELLED]));
                    } elseif ($documento === self::DOC_FACTURA) {
                        $q->where('status', self::STATUS_PAID)->whereNotExists($conCfdi);
                    } else {
                        $q->where('status', self::STATUS_PAID)->whereExists($conCfdi);
                    }
                });
            }
        });
    }

    /** #520: ¿ya existe la columna folio? (se consulta una vez por proceso). */
    private static ?bool $tieneFolio = null;

    /**
     * #520: toda factura NUEVA recibe su folio corto «MN-AAAA-NNNNNN» al crearse, venga de donde venga
     * (cajero, proformas, conciliación, espejo). Sin la columna (antes de migrar) no hace nada.
     */
    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice) {
            self::$tieneFolio ??= \Illuminate\Support\Facades\Schema::hasColumn('invoices', 'folio');
            if (self::$tieneFolio && empty($invoice->folio)) {
                $invoice->folio = \App\Services\Finance\Invoice\FolioFactura::siguiente();
            }
        });
    }

    public function markAsSent(): bool
    {
        // Solo una emitida (draft) pasa a «Enviada». Antes pasaba TODO a issued: enviar una factura
        // pagada (o parcial/vencida) la dejaba como «Enviada» y se veía sin pagar, aunque el pago y
        // el saldo 0 siguieran ahí (item #498: 10 casos en la copia de prod del 2026-10-15).
        $cambios = ['is_sent' => true];
        if ($this->status === self::STATUS_DRAFT) {
            $cambios['status'] = self::STATUS_ISSUED;
        }

        return $this->update($cambios);
    }


    /**
     * Marcar factura como pagada
     */
    public function markAsPaid(): bool
    {
        return $this->update([
            'status' => self::STATUS_PAID,
            'payment_date' => now(),
            'pending_balance' => 0,
            'observations' => $this->observations . PHP_EOL . 'Pagada desde la tabla por ' . auth()->user()->name . ' el ' . now()->format('d-m-Y')
        ]);
    }

    /**
     * Actualizar el saldo pendiente
     */
    public function updatePendingBalance($amount): bool
    {
        $newBalance = $this->pending_balance - $amount;

        if ($newBalance <= 0) {
            $this->status = self::STATUS_PAID;
            $this->payment_date = now();
            $this->pending_balance = 0;
        } elseif ($newBalance < $this->total) {
            $this->status = self::STATUS_PARTIALLY_PAID;
            $this->pending_balance = $newBalance;
        } else {
            $this->pending_balance = $newBalance;
        }

        return $this->save();
    }

    public function scopeFilters($query, $columns, $search = null, $filter = null)
    {
        if (isset($search) && empty($filter)) {
            return $query->where(function ($query) use ($search, $columns) {
                foreach (
                    collect($columns)->filter(function ($value) {
                        return ! in_array($value, self::COLUMNAS_CALCULADAS, true);
                    })->toArray() as $value
                ) {
                    if ($value === 'payment_method') {
                        // Se busca por el nombre del método, no por el id guardado.
                        $query->orWhereIn('payment_method', self::metodosPagoQueCoinciden((string) $search));
                        continue;
                    }
                    $query->orWhere($value, 'like', '%' . $search . '%');
                }
            });
        } elseif (!empty($filter)) {
            return $query->where(function ($query) use ($filter) {
                // Aplica los filtros
                foreach ($filter as $field => $values) {
                    if ($field == 'payment_method_id' && is_array($values) && !empty($values)) {
                        $query->whereHas('payment_method', function ($query) use ($values) {
                            $query->whereIn('id', $values);
                        });
                    }

                    if ($field == "due_date" && is_array($values) && !empty($values)) {
                        $from = Carbon::parse($values[0])->format('Y-m-d');
                        $to = $values[1];
                        if (!$to) {
                            $to = Carbon::now()->format('Y-m-d');
                        } else {
                            $to = Carbon::parse($values[1])->format('Y-m-d');
                        }
                        $query->whereDate('due_date', '>=', $from)
                            ->whereDate('due_date', '<=', $to);
                    }

                    if ($field == "payment_date" && is_array($values) && !empty($values)) {
                        $from = Carbon::parse($values[0])->format('Y-m-d');
                        $to = $values[1];
                        if (!$to) {
                            $to = Carbon::now()->format('Y-m-d');
                        } else {
                            $to = Carbon::parse($values[1])->format('Y-m-d');
                        }
                        $query->whereDate('payment_date', '>=', $from)
                            ->whereDate('payment_date', '<=', $to);
                    }

                    if ($field == "status" && !empty($values)) {
                        $query->whereIn('status', $values);
                    }

                    if ($field == "period" && !empty($values)) {
                        $query->where('period', $values);
                    }

                    if ($field == "client_id" && !empty($values)) {
                        $query->where('client_id', $values);
                    }

                    if ($field == "documento" && !empty($values)) {
                        self::filtrarPorDocumento($query, (array) $values);
                    }
                }
            })->where(function ($query) use ($search, $columns) {
                // Aplica la búsqueda solo después de haber aplicado los filtros
                foreach (
                    collect($columns)->filter(function ($value) {
                        return ! in_array($value, self::COLUMNAS_CALCULADAS, true);
                    })->toArray() as $value
                ) {
                    if ($value === 'payment_method') {
                        // Se busca por el nombre del método, no por el id guardado.
                        $query->orWhereIn('payment_method', self::metodosPagoQueCoinciden((string) $search));
                        continue;
                    }
                    $query->orWhere($value, 'like', '%' . $search . '%');
                }
            });
        }
    }


    public function getStatusNameAttribute()
    {
        // Usar la propiedad del modelo en lugar de attributes directamente
        $status = $this->status;

        if ($status === null) {
            return 'Desconocido';
        }

        $allStatus = [
            self::STATUS_DRAFT => 'Emitida',
            self::STATUS_ISSUED => 'Enviada',
            self::STATUS_PARTIALLY_PAID => 'Parcialmente Pagada',
            self::STATUS_PAID => 'Pagada',
            self::STATUS_OVERDUE => 'Vencida',
            self::STATUS_CANCELLED => 'Cancelada',
        ];

        return $allStatus[$status] ?? 'Estado desconocido';
    }
}
