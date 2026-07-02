<?php

namespace App\Modules\Addons\Payments\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Payments\Services\Extraction\PaymentReceiptExtractor;
use Illuminate\Http\Request;

/**
 * FASE PAGOS 3 (pieza 2) — Pantalla AISLADA de prueba de extracción por IA.
 * Irving sube una imagen de comprobante y ve qué extrajo la IA (campos +
 * confianza + ilegibles + raw). NO aplica pagos, NO busca cliente, NO conecta
 * WhatsApp. Gateada por rol (super-administrator / DESARROLLADOR) en las rutas.
 */
class ReceiptExtractionTestController extends Controller
{
    public function index(PaymentReceiptExtractor $extractor)
    {
        return view('addon-payments::extraccion-comprobante', [
            'types' => $extractor->supportedTypes(),
        ]);
    }

    public function procesar(Request $request, PaymentReceiptExtractor $extractor)
    {
        $request->validate([
            'imagen' => 'required|file|mimes:jpeg,jpg,png,webp|max:10240', // 10 MB
            'tipo'   => 'required|string|in:' . implode(',', $extractor->supportedTypes()),
        ]);

        $file  = $request->file('imagen');
        $bytes = file_get_contents($file->getRealPath()) ?: '';
        $mime  = $file->getMimeType() ?: 'image/jpeg';

        // SOLO extrae. No aplica pagos ni toca nada.
        $result = $extractor->extract($bytes, $mime, $request->input('tipo'));

        return response()->json($result);
    }
}
