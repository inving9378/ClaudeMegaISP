import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:percent_indicator/linear_percent_indicator.dart';
import 'package:provider/provider.dart';

import '../../providers/auth_provider.dart';
import '../../providers/cliente_provider.dart';
import '../../services/update_service.dart';
import '../../theme.dart';
import '../../utils/fechas.dart';
import '../../widgets/widgets.dart';

class ClienteDashboard extends StatefulWidget {
  const ClienteDashboard({super.key});
  @override
  State<ClienteDashboard> createState() => _ClienteDashboardState();
}

class _ClienteDashboardState extends State<ClienteDashboard> {
  int _bottomIndex = 0;

  @override
  void initState() {
    super.initState();
    Future.microtask(() async {
      final p = context.read<ClienteProvider>();
      p.loadServicio();
      p.loadTickets();
      await _checkUpdate();
    });
  }

  Future<void> _checkUpdate() async {
    final info = await UpdateService().checkForUpdate();
    if (info == null || !mounted) return;
    final accepted = await showDialog<bool>(
      context: context,
      barrierDismissible: !info.mandatory,
      builder: (_) => AlertDialog(
        title: const Text('Actualización disponible'),
        content: Text(
          'Hay una versión nueva (${info.version}) lista para instalar.'
          '${info.releaseNotes != null && info.releaseNotes!.isNotEmpty ? "\n\n${info.releaseNotes}" : ""}'
          '\n\nAl tocar "Descargar", el sistema bajará el APK y te pedirá actualizar la app — no necesitas desinstalar.',
        ),
        actions: [
          if (!info.mandatory)
            TextButton(onPressed: () => Navigator.of(context).pop(false), child: const Text('Más tarde')),
          FilledButton(onPressed: () => Navigator.of(context).pop(true), child: const Text('Descargar')),
        ],
      ),
    );
    if (accepted == true) {
      await UpdateService().openApkUrl(info.apkUrl);
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final cliente = context.watch<ClienteProvider>();

    return Scaffold(
      appBar: AppBar(
        title: Text('Hola, ${auth.session?.name ?? "cliente"} 👋', style: const TextStyle(fontSize: 18)),
        actions: [
          IconButton(
            onPressed: () => auth.logout(),
            icon: const Icon(Icons.logout),
            tooltip: 'Cerrar sesión',
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async {
          await cliente.loadServicio();
          await cliente.loadTickets();
        },
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            _ServicioCard(),
            const SizedBox(height: 16),
            _QuickActionsGrid(),
            const SizedBox(height: 24),
            _RecentTickets(),
          ],
        ),
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _bottomIndex,
        onDestinationSelected: (i) {
          setState(() => _bottomIndex = i);
          switch (i) {
            case 1: context.go('/cliente/servicio'); break;
            case 2: context.go('/cliente/tickets'); break;
            case 3: context.go('/cliente/perfil'); break;
          }
        },
        destinations: const [
          NavigationDestination(icon: Icon(Icons.home_outlined), selectedIcon: Icon(Icons.home), label: 'Inicio'),
          NavigationDestination(icon: Icon(Icons.router_outlined), selectedIcon: Icon(Icons.router), label: 'Servicio'),
          NavigationDestination(icon: Icon(Icons.support_agent_outlined), selectedIcon: Icon(Icons.support_agent), label: 'Soporte'),
          NavigationDestination(icon: Icon(Icons.person_outline), selectedIcon: Icon(Icons.person), label: 'Perfil'),
        ],
      ),
    );
  }
}

class _ServicioCard extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    final c = context.watch<ClienteProvider>();
    if (c.loadingServicio && c.servicio == null) return const Card(child: Padding(padding: EdgeInsets.all(20), child: Center(child: CircularProgressIndicator())));
    final s = c.servicio;
    if (s == null) {
      return const Card(
        child: Padding(
          padding: EdgeInsets.all(20),
          child: Row(children: [
            Icon(Icons.info_outline, color: BrandColors.textMuted),
            SizedBox(width: 12),
            Expanded(child: Text('Aún no tienes un servicio asociado a tu cuenta.', style: TextStyle(color: BrandColors.textMuted))),
          ]),
        ),
      );
    }

    final used = s.consumoGb ?? 0;
    final limit = s.consumoLimite ?? 1;
    final pct = (used / limit).clamp(0.0, 1.0);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(s.planName, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                      const SizedBox(height: 4),
                      Text(s.speed, style: const TextStyle(color: BrandColors.textMuted)),
                    ],
                  ),
                ),
                StatusBadge(s.estado),
              ],
            ),
            const SizedBox(height: 12),
            if (s.nextPaymentDate != null)
              Row(
                children: [
                  const Icon(Icons.event, size: 16, color: BrandColors.textMuted),
                  const SizedBox(width: 6),
                  Text('Próximo pago: ${fechaCorta(s.nextPaymentDate!)}', style: const TextStyle(color: BrandColors.textMuted, fontSize: 13)),
                ],
              ),
            const SizedBox(height: 14),
            if (s.consumoGb != null && s.consumoLimite != null) ...[
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text('Consumo del mes', style: TextStyle(fontSize: 12, color: Colors.grey.shade700)),
                  Text('${used.toStringAsFixed(1)} GB / ${limit.toStringAsFixed(0)} GB', style: const TextStyle(fontWeight: FontWeight.w600)),
                ],
              ),
              const SizedBox(height: 6),
              LinearPercentIndicator(
                percent: pct,
                lineHeight: 10,
                animation: true,
                backgroundColor: Colors.grey.shade200,
                progressColor: pct > 0.9 ? BrandColors.danger : (pct > 0.7 ? BrandColors.warning : BrandColors.success),
                barRadius: const Radius.circular(5),
                padding: EdgeInsets.zero,
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _QuickActionsGrid extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    final actions = [
      ('Facturas', Icons.receipt_long, BrandColors.primary, '/cliente/facturas'),
      ('Pagos', Icons.credit_card, BrandColors.success, '/cliente/pagos'),
      ('Tickets', Icons.confirmation_number, BrandColors.secondary, '/cliente/tickets'),
      ('Control parental', Icons.shield_moon, BrandColors.warning, '/cliente/parental'),
    ];

    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: 2, mainAxisSpacing: 10, crossAxisSpacing: 10, childAspectRatio: 1.6),
      itemCount: actions.length,
      itemBuilder: (_, i) {
        final (label, icon, color, route) = actions[i];
        return QuickAction(icon: icon, label: label, color: color, onTap: () => context.go(route));
      },
    );
  }
}

class _RecentTickets extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    final tickets = context.watch<ClienteProvider>().tickets.take(3).toList();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            const Text('Últimos tickets', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
            TextButton(onPressed: () => context.go('/cliente/tickets'), child: const Text('Ver todos')),
          ],
        ),
        if (tickets.isEmpty)
          const Padding(padding: EdgeInsets.symmetric(vertical: 12), child: Text('Aún no has creado tickets.', style: TextStyle(color: BrandColors.textMuted))),
        ...tickets.map((t) => Card(
              child: ListTile(
                leading: const Icon(Icons.confirmation_number_outlined),
                title: Text(t.subject),
                subtitle: Text(t.number),
                trailing: StatusBadge(t.status),
                onTap: () => context.go('/cliente/tickets'),
              ),
            )),
      ],
    );
  }
}
