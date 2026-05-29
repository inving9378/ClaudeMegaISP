import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/models.dart';
import '../../providers/embajador_provider.dart';
import '../../theme.dart';

/// Historial de comisiones mes a mes con gráfico de barras.
class ComisionesHistorialScreen extends StatefulWidget {
  const ComisionesHistorialScreen({super.key});

  @override
  State<ComisionesHistorialScreen> createState() => _ComisionesHistorialScreenState();
}

class _ComisionesHistorialScreenState extends State<ComisionesHistorialScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<EmbajadorProvider>().loadComisionesHistorial();
    });
  }

  @override
  Widget build(BuildContext context) {
    final prov = context.watch<EmbajadorProvider>();

    return Scaffold(
      appBar: AppBar(
        title: const Text('Historial de Comisiones'),
        actions: [
          if (!prov.loadingComisiones)
            IconButton(
              icon: const Icon(Icons.refresh),
              onPressed: () => context.read<EmbajadorProvider>().loadComisionesHistorial(),
            ),
        ],
      ),
      body: _buildBody(prov),
    );
  }

  Widget _buildBody(EmbajadorProvider prov) {
    if (prov.loadingComisiones) {
      return const Center(child: CircularProgressIndicator());
    }
    if (prov.comisionesError != null) {
      return _ErrorView(
        message: prov.comisionesError!,
        onRetry: () => context.read<EmbajadorProvider>().loadComisionesHistorial(),
      );
    }
    if (prov.comisionesHistorial.isEmpty) {
      return const _EmptyView();
    }
    return _ComisionesBody(
      meses: prov.comisionesHistorial,
      grandTotal: prov.comisionesGrandTotal,
      planType: prov.comisionesPlanType,
    );
  }
}

// ── Main body ─────────────────────────────────────────────────────────────────

class _ComisionesBody extends StatelessWidget {
  final List<ComisionMes> meses;
  final double grandTotal;
  final String planType;

  const _ComisionesBody({
    required this.meses,
    required this.grandTotal,
    required this.planType,
  });

  @override
  Widget build(BuildContext context) {
    // Solo mostrar los últimos 6 meses en el gráfico
    final chartMeses = meses.take(6).toList().reversed.toList();

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        _TotalCard(total: grandTotal),
        const SizedBox(height: 16),
        if (chartMeses.isNotEmpty) ...[
          _BarChart(meses: chartMeses),
          const SizedBox(height: 16),
        ],
        const Text('Detalle por mes',
            style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
        const SizedBox(height: 8),
        ...meses.map((m) => _MesTile(mes: m)),
      ],
    );
  }
}

class _TotalCard extends StatelessWidget {
  final double total;
  const _TotalCard({required this.total});

  @override
  Widget build(BuildContext context) {
    return Card(
      color: BrandColors.primary,
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Row(
          children: [
            const Icon(Icons.account_balance_wallet, color: Colors.white, size: 36),
            const SizedBox(width: 16),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Total acreditado', style: TextStyle(color: Colors.white70, fontSize: 13)),
                Text('\$${total.toStringAsFixed(2)}',
                    style: const TextStyle(
                        color: Colors.white, fontSize: 28, fontWeight: FontWeight.bold)),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

// ── Bar Chart ─────────────────────────────────────────────────────────────────

class _BarChart extends StatelessWidget {
  final List<ComisionMes> meses;
  const _BarChart({required this.meses});

  @override
  Widget build(BuildContext context) {
    final maxVal = meses.map((m) => m.total).fold(0.0, (a, b) => a > b ? a : b);
    final double yMax = maxVal > 0 ? maxVal * 1.25 : 100.0;

    return Card(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(12, 16, 12, 8),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Últimos 6 meses',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
            const SizedBox(height: 16),
            SizedBox(
              height: 160,
              child: BarChart(
                BarChartData(
                  maxY: yMax,
                  barTouchData: BarTouchData(
                    touchTooltipData: BarTouchTooltipData(
                      getTooltipItem: (group, _, rod, __) {
                        final mes = meses[group.x];
                        return BarTooltipItem(
                          '${mes.periodLabel}\n\$${rod.toY.toStringAsFixed(2)}',
                          const TextStyle(color: Colors.white, fontSize: 12),
                        );
                      },
                    ),
                  ),
                  titlesData: FlTitlesData(
                    leftTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                    rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                    topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                    bottomTitles: AxisTitles(
                      sideTitles: SideTitles(
                        showTitles: true,
                        reservedSize: 28,
                        getTitlesWidget: (v, _) {
                          final i = v.toInt();
                          if (i < 0 || i >= meses.length) return const SizedBox.shrink();
                          final mes = meses[i];
                          return Text(mes.periodLabel,
                              style: const TextStyle(fontSize: 10, color: BrandColors.textMuted));
                        },
                      ),
                    ),
                  ),
                  gridData: const FlGridData(show: false),
                  borderData: FlBorderData(show: false),
                  barGroups: List.generate(meses.length, (i) {
                    final mes = meses[i];
                    return BarChartGroupData(
                      x: i,
                      barRods: [
                        BarChartRodData(
                          toY: mes.total,
                          color: BrandColors.primary,
                          width: 28,
                          borderRadius: const BorderRadius.vertical(top: Radius.circular(4)),
                          backDrawRodData: BackgroundBarChartRodData(
                            show: true,
                            toY: yMax,
                            color: Colors.grey.shade100,
                          ),
                        ),
                      ],
                    );
                  }),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ── Month tile ────────────────────────────────────────────────────────────────

class _MesTile extends StatelessWidget {
  final ComisionMes mes;
  const _MesTile({required this.mes});

  static const _statusLabels = {
    'pending': 'Pendientes',
    'approved': 'Aprobadas',
    'applied': 'Acreditadas',
    'cancelled': 'Canceladas',
  };

  static const _statusColors = {
    'pending': BrandColors.warning,
    'approved': Color(0xFF1565C0),
    'applied': BrandColors.success,
    'cancelled': BrandColors.textMuted,
  };

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ExpansionTile(
        leading: CircleAvatar(
          backgroundColor: BrandColors.selectedTint,
          child: Text(
            mes.periodMonth.toString(),
            style: const TextStyle(color: BrandColors.primary, fontWeight: FontWeight.bold),
          ),
        ),
        title: Text(mes.periodLabel, style: const TextStyle(fontWeight: FontWeight.w600)),
        subtitle: Text(
          '${mes.count} comisión${mes.count == 1 ? '' : 'es'}',
          style: const TextStyle(fontSize: 12, color: BrandColors.textMuted),
        ),
        trailing: Text(
          '\$${mes.total.toStringAsFixed(2)}',
          style: const TextStyle(
              fontWeight: FontWeight.bold, color: BrandColors.success, fontSize: 15),
        ),
        children: mes.byStatus.entries
            .map((e) => ListTile(
                  dense: true,
                  contentPadding: const EdgeInsets.symmetric(horizontal: 24),
                  leading: Icon(Icons.circle,
                      size: 10,
                      color: _statusColors[e.key] ?? BrandColors.textMuted),
                  title: Text(
                    _statusLabels[e.key] ?? e.key,
                    style: const TextStyle(fontSize: 13),
                  ),
                  trailing: Text(
                    '\$${e.value.toStringAsFixed(2)}',
                    style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
                  ),
                ))
            .toList(),
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
              Icon(Icons.bar_chart_outlined, size: 72, color: Colors.grey.shade300),
              const SizedBox(height: 16),
              const Text('Sin comisiones aún',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
              const SizedBox(height: 8),
              const Text('Las comisiones aparecerán aquí mes a mes conforme tus referidos paguen.',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: BrandColors.textMuted)),
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
