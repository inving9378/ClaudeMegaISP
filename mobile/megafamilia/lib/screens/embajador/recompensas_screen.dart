import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/models.dart';
import '../../providers/embajador_provider.dart';
import '../../theme.dart';
import '../../utils/fechas.dart';

/// Pantalla de recompensas acumuladas (mensualidades gratis).
/// Solo para plan single_reward.
class RecompensasScreen extends StatefulWidget {
  const RecompensasScreen({super.key});

  @override
  State<RecompensasScreen> createState() => _RecompensasScreenState();
}

class _RecompensasScreenState extends State<RecompensasScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<EmbajadorProvider>().loadRecompensas();
    });
  }

  @override
  Widget build(BuildContext context) {
    final prov = context.watch<EmbajadorProvider>();

    return Scaffold(
      appBar: AppBar(
        title: const Text('Mis Mensualidades Gratis'),
        actions: [
          if (!prov.loadingRecompensas)
            IconButton(
              icon: const Icon(Icons.refresh),
              onPressed: () => context.read<EmbajadorProvider>().loadRecompensas(),
            ),
        ],
      ),
      body: _buildBody(prov),
    );
  }

  Widget _buildBody(EmbajadorProvider prov) {
    if (prov.loadingRecompensas) {
      return const Center(child: CircularProgressIndicator());
    }
    if (prov.recompensasError != null) {
      return _ErrorView(
        message: prov.recompensasError!,
        onRetry: () => context.read<EmbajadorProvider>().loadRecompensas(),
      );
    }
    return Column(
      children: [
        _SummaryHeader(summary: prov.recompensasSummary),
        Expanded(
          child: prov.recompensas.isEmpty
              ? const _EmptyView()
              : ListView.separated(
                  padding: const EdgeInsets.all(16),
                  itemCount: prov.recompensas.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 8),
                  itemBuilder: (_, i) => _RecompensaTile(
                    recompensa: prov.recompensas[i],
                    onAplicar: () => _aplicar(prov.recompensas[i]),
                  ),
                ),
        ),
      ],
    );
  }

  Future<void> _aplicar(EmbajadorRecompensa r) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Aplicar mensualidad'),
        content: Text(
          '¿Deseas aplicar esta mensualidad gratis de \$${r.planValue.toStringAsFixed(2)} '
          'a tu próxima factura?\n\nEsta acción no se puede deshacer.',
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancelar')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Aplicar')),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    final ok = await context.read<EmbajadorProvider>().aplicarRecompensa(r.id);
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
      content: Text(ok
          ? '¡Mensualidad aplicada! Se descontará en tu próxima factura.'
          : context.read<EmbajadorProvider>().recompensasError ?? 'Error al aplicar'),
      backgroundColor: ok ? BrandColors.success : BrandColors.danger,
    ));
  }
}

// ── Summary header ────────────────────────────────────────────────────────────

class _SummaryHeader extends StatelessWidget {
  final Map<String, int> summary;
  const _SummaryHeader({required this.summary});

  @override
  Widget build(BuildContext context) {
    final available = summary['available'] ?? 0;
    final applied   = summary['applied']   ?? 0;
    final pending   = summary['pending']   ?? 0;

    return Container(
      color: BrandColors.primary,
      padding: const EdgeInsets.all(20),
      child: Row(
        children: [
          Expanded(child: _StatBox(label: 'Disponibles', value: '$available', color: Colors.greenAccent)),
          Expanded(child: _StatBox(label: 'Aplicadas',   value: '$applied',   color: Colors.lightBlueAccent)),
          Expanded(child: _StatBox(label: 'Pendientes',  value: '$pending',   color: Colors.orangeAccent)),
        ],
      ),
    );
  }
}

class _StatBox extends StatelessWidget {
  final String label;
  final String value;
  final Color color;

  const _StatBox({required this.label, required this.value, required this.color});

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Text(value,
            style: TextStyle(color: color, fontSize: 28, fontWeight: FontWeight.bold)),
        Text(label,
            style: const TextStyle(color: Colors.white70, fontSize: 12)),
      ],
    );
  }
}

// ── Recompensa tile ──────────────────────────────────────────────────────────

class _RecompensaTile extends StatelessWidget {
  final EmbajadorRecompensa recompensa;
  final VoidCallback onAplicar;

  const _RecompensaTile({required this.recompensa, required this.onAplicar});

  @override
  Widget build(BuildContext context) {
    final r = recompensa;
    final color = Color(r.statusColor);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Header: valor + status
            Row(
              children: [
                Icon(Icons.card_giftcard, color: color, size: 28),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('\$${r.planValue.toStringAsFixed(2)} de mensualidad gratis',
                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                      Text('Por: ${r.referidoName}',
                          style: const TextStyle(color: BrandColors.textMuted, fontSize: 13)),
                    ],
                  ),
                ),
                _StatusBadge(label: r.statusLabel, color: color),
              ],
            ),

            const Divider(height: 20),

            // Fechas
            Row(
              children: [
                if (r.availableAt != null)
                  _DateRow(icon: Icons.check_circle_outline, label: 'Disponible', date: r.availableAt!),
                if (r.appliedAt != null) ...[
                  const SizedBox(width: 16),
                  _DateRow(icon: Icons.receipt_long, label: 'Aplicada', date: r.appliedAt!),
                ],
                if (r.expiresAt != null && r.status != 'applied') ...[
                  const SizedBox(width: 16),
                  _DateRow(icon: Icons.timer_outlined, label: 'Vence', date: r.expiresAt!),
                ],
              ],
            ),

            // Botón aplicar
            if (r.isAvailable) ...[
              const SizedBox(height: 14),
              SizedBox(
                width: double.infinity,
                child: FilledButton.icon(
                  onPressed: onAplicar,
                  icon: const Icon(Icons.check),
                  label: const Text('Aplicar a mi próxima factura'),
                  style: FilledButton.styleFrom(backgroundColor: BrandColors.success),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _StatusBadge extends StatelessWidget {
  final String label;
  final Color color;
  const _StatusBadge({required this.label, required this.color});

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
        decoration: BoxDecoration(
          color: color.withAlpha(30),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: color.withAlpha(80)),
        ),
        child: Text(label,
            style: TextStyle(color: color, fontWeight: FontWeight.bold, fontSize: 12)),
      );
}

class _DateRow extends StatelessWidget {
  final IconData icon;
  final String label;
  final DateTime date;
  const _DateRow({required this.icon, required this.label, required this.date});

  @override
  Widget build(BuildContext context) => Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 13, color: BrandColors.textMuted),
          const SizedBox(width: 3),
          Text('$label: ${fechaCorta(date)}',
              style: const TextStyle(fontSize: 11, color: BrandColors.textMuted)),
        ],
      );
}

// ── Empty / Error ─────────────────────────────────────────────────────────────

class _EmptyView extends StatelessWidget {
  const _EmptyView();
  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.card_giftcard_outlined, size: 72, color: Colors.grey.shade300),
              const SizedBox(height: 16),
              const Text('Sin mensualidades aún',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
              const SizedBox(height: 8),
              const Text(
                'Cada cliente que refieras te dará una mensualidad gratis cuando complete sus primeros 3 pagos.',
                textAlign: TextAlign.center,
                style: TextStyle(color: BrandColors.textMuted),
              ),
            ],
          ),
        ),
      );
}

class _ErrorView extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;
  const _ErrorView({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.error_outline, size: 48, color: BrandColors.danger),
              const SizedBox(height: 16),
              Text(message, textAlign: TextAlign.center, style: const TextStyle(color: BrandColors.textMuted)),
              const SizedBox(height: 16),
              FilledButton.icon(onPressed: onRetry, icon: const Icon(Icons.refresh), label: const Text('Reintentar')),
            ],
          ),
        ),
      );
}
