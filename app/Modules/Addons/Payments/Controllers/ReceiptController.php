<?php

namespace App\Modules\Addons\Payments\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Modules\Addons\Payments\Models\PaymentReceipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Acceso a comprobantes adjuntos a un pago. Los archivos viven en
 * storage/app/ (disk 'local', no público) — se sirven vía streaming
 * con autenticación.
 */
class ReceiptController extends Controller
{
    /**
     * GET /finanzas/payments/{id}/receipt
     * Lista todos los comprobantes adjuntos al pago.
     */
    public function index(int $paymentId): JsonResponse
    {
        $payment = Payment::findOrFail($paymentId);

        $receipts = PaymentReceipt::where('payment_id', $payment->id)
            ->orderBy('id', 'desc')
            ->get(['id', 'type', 'file_path', 'original_name', 'size', 'metadata', 'created_at'])
            ->map(fn (PaymentReceipt $r) => [
                'id'            => $r->id,
                'type'          => $r->type,
                'original_name' => $r->original_name,
                'size'          => $r->size,
                'size_human'    => $this->humanSize((int) $r->size),
                'metadata'      => $r->metadata,
                'created_at'    => $r->created_at,
                'download_url'  => route('payments.receipt.download', [
                    'payment'  => $payment->id,
                    'receipt'  => $r->id,
                ]),
            ]);

        return response()->json([
            'success'    => true,
            'payment_id' => $payment->id,
            'receipts'   => $receipts,
        ]);
    }

    /**
     * GET /finanzas/payments/{paymentId}/receipt/{receiptId}/download
     * Streams el archivo. Mismo permission que index.
     */
    public function download(int $paymentId, int $receiptId): StreamedResponse
    {
        $receipt = PaymentReceipt::where('payment_id', $paymentId)
            ->where('id', $receiptId)
            ->firstOrFail();

        if (!Storage::disk('local')->exists($receipt->file_path)) {
            abort(404, 'Archivo no encontrado en disco.');
        }

        $filename = $receipt->original_name ?: basename($receipt->file_path);
        return Storage::disk('local')->download($receipt->file_path, $filename);
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
        return round($bytes / 1073741824, 1) . ' GB';
    }
}
