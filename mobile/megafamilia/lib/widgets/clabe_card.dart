import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../models/models.dart';

/// Tarjeta reutilizable que muestra la CLABE virtual del cliente con QR,
/// copiar-al-portapapeles, e información del beneficiario.
///
/// Recibe una [ClabeInfo] (puede venir de ClienteProvider.clabe).
/// El callback [onNotifyTransfer] se llama cuando el usuario presiona
/// "Ya realicé mi transferencia" — el padre abre el bottom sheet de upload.
class ClabeCard extends StatelessWidget {
  final ClabeInfo clabe;
  final VoidCallback? onNotifyTransfer;

  const ClabeCard({
    super.key,
    required this.clabe,
    this.onNotifyTransfer,
  });

  @override
  Widget build(BuildContext context) {
    // QR generado server-side por api.qrserver.com — sin dep extra (usa
    // cached_network_image ya en pubspec). Si esto se quiere offline, agregar
    // qr_flutter y reemplazar.
    final qrUrl =
        'https://api.qrserver.com/v1/create-qr-code/?data=${Uri.encodeComponent(clabe.clabe)}&size=240x240&margin=8';

    return Card(
      elevation: 2,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // ── Encabezado ───────────────────────────────────────────
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: Theme.of(context).colorScheme.primary.withOpacity(0.1),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Icon(Icons.account_balance, color: Theme.of(context).colorScheme.primary, size: 28),
                ),
                const SizedBox(width: 12),
                const Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Transferencia SPEI', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 17)),
                      Text('Deposita desde cualquier banco', style: TextStyle(fontSize: 12, color: Colors.grey)),
                    ],
                  ),
                ),
              ],
            ),

            const SizedBox(height: 18),

            // ── CLABE grande + copy ─────────────────────────────────
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: Colors.grey.shade100,
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: Colors.grey.shade300),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('CLABE (18 dígitos)', style: TextStyle(fontSize: 11, color: Colors.grey)),
                        const SizedBox(height: 4),
                        Text(
                          clabe.clabeFormat,
                          style: const TextStyle(
                            fontFamily: 'monospace',
                            fontSize: 18,
                            fontWeight: FontWeight.bold,
                            letterSpacing: 1.2,
                          ),
                        ),
                      ],
                    ),
                  ),
                  IconButton(
                    icon: const Icon(Icons.content_copy),
                    tooltip: 'Copiar CLABE',
                    onPressed: () => _copy(context),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 16),

            // ── QR + datos beneficiario ─────────────────────────────
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  padding: const EdgeInsets.all(6),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    border: Border.all(color: Colors.grey.shade300),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: CachedNetworkImage(
                    imageUrl: qrUrl,
                    width: 130,
                    height: 130,
                    placeholder: (_, __) => const SizedBox(
                      width: 130,
                      height: 130,
                      child: Center(child: CircularProgressIndicator(strokeWidth: 2)),
                    ),
                    errorWidget: (_, __, ___) => const SizedBox(
                      width: 130,
                      height: 130,
                      child: Icon(Icons.qr_code_2, size: 60, color: Colors.grey),
                    ),
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _DataRow(label: 'Beneficiario', value: clabe.beneficiario),
                      const SizedBox(height: 8),
                      _DataRow(label: 'Banco', value: clabe.banco),
                      const SizedBox(height: 8),
                      _DataRow(label: 'Concepto', value: clabe.concepto),
                    ],
                  ),
                ),
              ],
            ),

            const SizedBox(height: 18),

            // ── Botón "Ya hice mi transferencia" ─────────────────────
            if (onNotifyTransfer != null)
              SizedBox(
                width: double.infinity,
                child: FilledButton.icon(
                  onPressed: onNotifyTransfer,
                  icon: const Icon(Icons.receipt_long),
                  label: const Text('Ya realicé mi transferencia'),
                  style: FilledButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 14)),
                ),
              ),
          ],
        ),
      ),
    );
  }

  void _copy(BuildContext context) {
    Clipboard.setData(ClipboardData(text: clabe.clabe));
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('CLABE copiada al portapapeles'), duration: Duration(seconds: 2)),
    );
  }
}

class _DataRow extends StatelessWidget {
  final String label;
  final String value;
  const _DataRow({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: const TextStyle(fontSize: 11, color: Colors.grey)),
        Text(value, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w500)),
      ],
    );
  }
}
