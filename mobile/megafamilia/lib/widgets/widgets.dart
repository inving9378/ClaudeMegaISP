import 'package:flutter/material.dart';

import '../theme.dart';

/// Badge de estado con color por palabra clave (Activo, Suspendido, Pagada, etc).
class StatusBadge extends StatelessWidget {
  final String text;
  final Color? color;
  const StatusBadge(this.text, {this.color, super.key});

  Color _autoColor() {
    final t = text.toLowerCase();
    if (t.contains('activo') || t.contains('pagad') || t.contains('compl') || t.contains('resuelto') || t.contains('online')) return BrandColors.success;
    if (t.contains('proceso') || t.contains('curso') || t.contains('cotiz')) return BrandColors.secondary;
    if (t.contains('pend') || t.contains('nuevo') || t.contains('esperando')) return BrandColors.warning;
    if (t.contains('suspend') || t.contains('cancel') || t.contains('venc') || t.contains('bloqu')) return BrandColors.danger;
    return Colors.grey;
  }

  @override
  Widget build(BuildContext context) {
    final c = color ?? _autoColor();
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: c.withOpacity(0.15),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        text,
        style: TextStyle(color: c, fontSize: 12, fontWeight: FontWeight.w600),
      ),
    );
  }
}

/// Tarjeta KPI usada en dashboards.
class StatCard extends StatelessWidget {
  final String label;
  final String value;
  final IconData icon;
  final Color iconColor;
  final String? sub;

  const StatCard({
    required this.label,
    required this.value,
    required this.icon,
    this.iconColor = BrandColors.primary,
    this.sub,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Row(
          children: [
            CircleAvatar(
              radius: 22,
              backgroundColor: iconColor.withOpacity(0.12),
              child: Icon(icon, color: iconColor, size: 22),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(label, style: const TextStyle(color: BrandColors.textMuted, fontSize: 12)),
                  const SizedBox(height: 2),
                  Text(value, style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold)),
                  if (sub != null && sub!.isNotEmpty) ...[
                    const SizedBox(height: 2),
                    Text(sub!, style: const TextStyle(color: BrandColors.textMuted, fontSize: 11)),
                  ]
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Card de acción rápida — icono grande + label, usado en grids del dashboard.
class QuickAction extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;
  const QuickAction({required this.icon, required this.label, required this.color, required this.onTap, super.key});

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Card(
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              CircleAvatar(radius: 24, backgroundColor: color.withOpacity(0.12), child: Icon(icon, color: color, size: 24)),
              const SizedBox(height: 8),
              Text(label, textAlign: TextAlign.center, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
            ],
          ),
        ),
      ),
    );
  }
}

/// View genérica de carga / error / vacío.
class CenteredState extends StatelessWidget {
  final String message;
  final IconData icon;
  final Color iconColor;
  final Widget? action;
  const CenteredState({required this.message, this.icon = Icons.info_outline, this.iconColor = BrandColors.textMuted, this.action, super.key});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 48, color: iconColor),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center, style: const TextStyle(color: BrandColors.textMuted)),
            if (action != null) ...[const SizedBox(height: 16), action!],
          ],
        ),
      ),
    );
  }
}

class LoadingView extends StatelessWidget {
  const LoadingView({super.key});
  @override
  Widget build(BuildContext context) => const Center(child: CircularProgressIndicator(color: BrandColors.primary));
}
