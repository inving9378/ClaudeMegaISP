import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../providers/cliente_provider.dart';
import '../../theme.dart';
import '../../widgets/widgets.dart';

class FacturaDetailScreen extends StatelessWidget {
  final int facturaId;
  const FacturaDetailScreen({required this.facturaId, super.key});

  @override
  Widget build(BuildContext context) {
    final c = context.watch<ClienteProvider>();
    final f = c.facturas.firstWhere(
      (x) => x.id == facturaId,
      orElse: () => c.facturas.isNotEmpty ? c.facturas.first : throw StateError('factura $facturaId no encontrada'),
    );
    final df = DateFormat('d MMMM yyyy', 'es');
    final mf = NumberFormat.currency(locale: 'es_MX', symbol: '\$');

    return Scaffold(
      appBar: AppBar(title: Text('Factura ${f.number}')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(f.number, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                      StatusBadge(f.status),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text('Fecha de emisión: ${f.date != null ? df.format(f.date!) : "—"}', style: const TextStyle(color: BrandColors.textMuted)),
                  const Divider(height: 32),
                  const Text('Total a pagar', style: TextStyle(color: BrandColors.textMuted)),
                  const SizedBox(height: 4),
                  Text(mf.format(f.amount), style: const TextStyle(fontSize: 32, fontWeight: FontWeight.bold, color: BrandColors.primary)),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          FilledButton.icon(
            onPressed: () {
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('Descarga de PDF — pendiente de implementar en backend')),
              );
            },
            icon: const Icon(Icons.picture_as_pdf),
            label: const Text('Descargar PDF'),
          ),
          const SizedBox(height: 8),
          OutlinedButton.icon(
            onPressed: () {
              Navigator.of(context).pushNamed('/cliente/pagos');
            },
            icon: const Icon(Icons.payment),
            label: const Text('Pagar esta factura'),
          ),
        ],
      ),
    );
  }
}
