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
    public function markAsSent(): bool
    {
        return $this->update(['is_sent' => true, 'status' => self::STATUS_ISSUED]);
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
                        return $value != 'action';
                    })->toArray() as $value
                ) {
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
                }
            })->where(function ($query) use ($search, $columns) {
                // Aplica la búsqueda solo después de haber aplicado los filtros
                foreach (
                    collect($columns)->filter(function ($value) {
                        return $value != 'action';
                    })->toArray() as $value
                ) {
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
