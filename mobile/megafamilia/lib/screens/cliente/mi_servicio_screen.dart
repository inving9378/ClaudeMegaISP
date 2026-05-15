import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../providers/auth_provider.dart';
import '../../providers/cliente_provider.dart';
import '../../theme.dart';
import '../../widgets/widgets.dart';

class MiServicioScreen extends StatefulWidget {
  const MiServicioScreen({super.key});
  @override
  State<MiServicioScreen> createState() => _MiServicioScreenState();
}

class _MiServicioScreenState extends State<MiServicioScreen> {
  @override
  void initState() {
    super.initState();
    Future.microtask(() => context.read<ClienteProvider>().loadServicio());
  }

  @override
  Widget build(BuildContext context) {
    final c = context.watch<ClienteProvider>();
    final s = c.servicio;
    final auth = context.watch<AuthProvider>();
    final df = DateFormat('d MMM yyyy', 'es');

    return Scaffold(
      appBar: AppBar(title: const Text('Mi servicio')),
      body: c.loadingServicio && s == null
          ? const LoadingView()
          : s == null
              ? const CenteredState(message: 'No fue posible cargar tu servicio.')
              : ListView(
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
                                Expanded(child: Text(s.planName, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold))),
                                StatusBadge(s.estado),
                              ],
                            ),
                            const SizedBox(height: 4),
                            Text(s.speed, style: const TextStyle(fontSize: 16, color: BrandColors.textMuted)),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),
                    _InfoSection(title: 'Datos del servicio', items: [
                      ('Contrato', s.contractNumber.isEmpty ? '—' : s.contractNumber),
                      ('Dirección', s.address.isEmpty ? '—' : s.address),
                      ('Próximo pago', s.nextPaymentDate != null ? df.format(s.nextPaymentDate!) : '—'),
                    ]),
                    const SizedBox(height: 16),
                    _InfoSection(title: 'Titular', items: [
                      ('Nombre', auth.session?.name ?? '—'),
                      ('Email', auth.session?.email ?? '—'),
                    ]),
                  ],
                ),
    );
  }
}

class _InfoSection extends StatelessWidget {
  final String title;
  final List<(String, String)> items;
  const _InfoSection({required this.title, required this.items});

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
            const SizedBox(height: 8),
            ...items.map((row) => Padding(
                  padding: const EdgeInsets.symmetric(vertical: 6),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      SizedBox(width: 100, child: Text(row.$1, style: const TextStyle(color: BrandColors.textMuted, fontSize: 13))),
                      Expanded(child: Text(row.$2, style: const TextStyle(fontWeight: FontWeight.w500))),
                    ],
                  ),
                )),
          ],
        ),
      ),
    );
  }
}
