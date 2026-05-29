import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/models.dart';
import '../../providers/embajador_provider.dart';
import '../../theme.dart';
import '../../utils/fechas.dart'; // fechaCorta, fechaCortaConHora

/// Historial de notificaciones WhatsApp enviadas por el sistema.
class NotificacionesLogScreen extends StatefulWidget {
  const NotificacionesLogScreen({super.key});

  @override
  State<NotificacionesLogScreen> createState() => _NotificacionesLogScreenState();
}

class _NotificacionesLogScreenState extends State<NotificacionesLogScreen> {
  final _scrollCtrl = ScrollController();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<EmbajadorProvider>().loadNotifLogs();
    });
    _scrollCtrl.addListener(_onScroll);
  }

  void _onScroll() {
    if (_scrollCtrl.position.pixels >= _scrollCtrl.position.maxScrollExtent - 200) {
      context.read<EmbajadorProvider>().loadMoreNotifLogs();
    }
  }

  @override
  void dispose() {
    _scrollCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final prov = context.watch<EmbajadorProvider>();

    return Scaffold(
      appBar: AppBar(
        title: const Text('Notificaciones enviadas'),
        actions: [
          if (!prov.loadingNotifLogs)
            IconButton(
              icon: const Icon(Icons.refresh),
              onPressed: () => context.read<EmbajadorProvider>().loadNotifLogs(),
            ),
        ],
      ),
      body: _buildBody(prov),
    );
  }

  Widget _buildBody(EmbajadorProvider prov) {
    if (prov.loadingNotifLogs) {
      return const Center(child: CircularProgressIndicator());
    }
    if (prov.notifLogsError != null) {
      return _ErrorView(
        message: prov.notifLogsError!,
        onRetry: () => context.read<EmbajadorProvider>().loadNotifLogs(),
      );
    }
    if (prov.notifLogs.isEmpty) {
      return const _EmptyView();
    }

    return Column(
      children: [
        _InfoBanner(),
        Expanded(
          child: ListView.separated(
            controller: _scrollCtrl,
            padding: const EdgeInsets.all(16),
            itemCount: prov.notifLogs.length + (prov.loadingMoreNotifLogs ? 1 : 0),
            separatorBuilder: (_, __) => const SizedBox(height: 8),
            itemBuilder: (_, i) {
              if (i == prov.notifLogs.length) {
                return const Padding(
                  padding: EdgeInsets.symmetric(vertical: 12),
                  child: Center(child: CircularProgressIndicator()),
                );
              }
              return _NotifTile(log: prov.notifLogs[i]);
            },
          ),
        ),
      ],
    );
  }
}

class _InfoBanner extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Container(
      color: BrandColors.selectedTint,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      child: Row(
        children: const [
          Icon(Icons.info_outline, size: 14, color: BrandColors.textMuted),
          SizedBox(width: 8),
          Expanded(
            child: Text(
              'Últimos 30 días. El sistema te notifica automáticamente por WhatsApp en eventos clave.',
              style: TextStyle(fontSize: 12, color: BrandColors.textMuted),
            ),
          ),
        ],
      ),
    );
  }
}

// ── Log tile ─────────────────────────────────────────────────────────────────

class _NotifTile extends StatelessWidget {
  final NotifLog log;
  const _NotifTile({required this.log});

  static const Map<String, IconData> _eventIcons = {
    'prospect_converted': Icons.person_add_alt_1,
    'threshold_covered': Icons.star,
    'commission_generated': Icons.monetization_on,
    'commissions_applied': Icons.account_balance_wallet,
    'reward_expiring_soon': Icons.timer_off,
  };

  @override
  Widget build(BuildContext context) {
    final icon = _eventIcons[log.eventType] ?? Icons.notifications;
    final color = log.success ? BrandColors.success : BrandColors.danger;

    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: log.bodySent != null ? () => _showBody(context) : null,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: color.withAlpha(30),
                  shape: BoxShape.circle,
                ),
                child: Icon(icon, color: color, size: 20),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(log.eventLabel,
                              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                        ),
                        _StatusBadge(success: log.success),
                      ],
                    ),
                    if (log.sentAt != null) ...[
                      const SizedBox(height: 2),
                      Text(fechaCortaConHora(log.sentAt!),
                          style: const TextStyle(fontSize: 12, color: BrandColors.textMuted)),
                    ],
                    if (!log.success && log.errorMessage != null) ...[
                      const SizedBox(height: 4),
                      Text(log.errorMessage!,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(fontSize: 12, color: BrandColors.danger)),
                    ],
                    if (log.bodySent != null && log.success)
                      const Align(
                        alignment: Alignment.centerRight,
                        child: Text('Ver mensaje →',
                            style: TextStyle(fontSize: 11, color: BrandColors.primary)),
                      ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _showBody(BuildContext context) {
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (_) => Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(log.eventLabel,
                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
            const SizedBox(height: 8),
            if (log.sentAt != null)
              Text('Enviado: ${fechaCortaConHora(log.sentAt!)}',
                  style: const TextStyle(fontSize: 12, color: BrandColors.textMuted)),
            const SizedBox(height: 16),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: Colors.grey.shade100,
                borderRadius: BorderRadius.circular(8),
              ),
              child: Text(log.bodySent ?? '', style: const TextStyle(fontSize: 14)),
            ),
            const SizedBox(height: 16),
          ],
        ),
      ),
    );
  }
}

class _StatusBadge extends StatelessWidget {
  final bool success;
  const _StatusBadge({required this.success});

  @override
  Widget build(BuildContext context) {
    final color = success ? BrandColors.success : BrandColors.danger;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(
        color: color.withAlpha(30),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Text(
        success ? 'Enviado' : 'Fallido',
        style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.bold),
      ),
    );
  }
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
              Icon(Icons.notifications_none, size: 72, color: Colors.grey.shade300),
              const SizedBox(height: 16),
              const Text('Sin notificaciones aún',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
              const SizedBox(height: 8),
              const Text(
                'El sistema te enviará mensajes por WhatsApp en eventos importantes del programa.',
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
