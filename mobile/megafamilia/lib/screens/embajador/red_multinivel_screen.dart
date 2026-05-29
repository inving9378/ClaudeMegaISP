import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/models.dart';
import '../../providers/embajador_provider.dart';
import '../../theme.dart';

/// Visualizador del árbol de red multinivel del embajador.
/// Solo accesible para plan_type = 'multilevel'.
class RedMultinivelScreen extends StatefulWidget {
  const RedMultinivelScreen({super.key});

  @override
  State<RedMultinivelScreen> createState() => _RedMultinivelScreenState();
}

class _RedMultinivelScreenState extends State<RedMultinivelScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<EmbajadorProvider>().loadRed();
    });
  }

  @override
  Widget build(BuildContext context) {
    final prov = context.watch<EmbajadorProvider>();

    return Scaffold(
      appBar: AppBar(
        title: const Text('Mi Red Multinivel'),
        actions: [
          if (!prov.loadingRed)
            IconButton(
              icon: const Icon(Icons.refresh),
              onPressed: () => context.read<EmbajadorProvider>().loadRed(),
            ),
        ],
      ),
      body: _buildBody(prov),
    );
  }

  Widget _buildBody(EmbajadorProvider prov) {
    if (prov.loadingRed) {
      return const Center(child: CircularProgressIndicator());
    }
    if (prov.redError != null) {
      return _ErrorView(
        message: prov.redError!,
        onRetry: () => context.read<EmbajadorProvider>().loadRed(),
      );
    }
    if (prov.redNodos.isEmpty) {
      return const _EmptyView();
    }
    return _TreeView(nodos: prov.redNodos, total: prov.redTotal);
  }
}

// ── Tree view ────────────────────────────────────────────────────────────────

class _TreeView extends StatelessWidget {
  final List<RedNodo> nodos;
  final int total;

  const _TreeView({required this.nodos, required this.total});

  /// Construye lista ordenada: primero por depth, después por nombre.
  List<RedNodo> get _sorted =>
      [...nodos]..sort((a, b) => a.depth != b.depth ? a.depth.compareTo(b.depth) : a.name.compareTo(b.name));

  int _childrenCount(int parentId) =>
      nodos.where((n) => n.parentId == parentId).length;

  @override
  Widget build(BuildContext context) {
    final sorted = _sorted;
    final maxDepth = sorted.isEmpty ? 0 : sorted.last.depth;

    return Column(
      children: [
        _SummaryBar(total: total, maxDepth: maxDepth),
        _LevelLegend(maxDepth: maxDepth),
        Expanded(
          child: ListView.builder(
            padding: const EdgeInsets.fromLTRB(12, 8, 12, 24),
            itemCount: sorted.length,
            itemBuilder: (_, i) {
              final nodo = sorted[i];
              return _NodoTile(
                nodo: nodo,
                childrenCount: _childrenCount(nodo.clientId),
              );
            },
          ),
        ),
      ],
    );
  }
}

class _SummaryBar extends StatelessWidget {
  final int total;
  final int maxDepth;

  const _SummaryBar({required this.total, required this.maxDepth});

  @override
  Widget build(BuildContext context) {
    return Container(
      color: BrandColors.primary,
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
      child: Row(
        children: [
          const Icon(Icons.account_tree, color: Colors.white, size: 28),
          const SizedBox(width: 12),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('$total referido${total == 1 ? '' : 's'} en tu red',
                  style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 16)),
              Text('Hasta $maxDepth nivel${maxDepth == 1 ? '' : 'es'} de profundidad',
                  style: const TextStyle(color: Colors.white70, fontSize: 13)),
            ],
          ),
        ],
      ),
    );
  }
}

class _LevelLegend extends StatelessWidget {
  final int maxDepth;
  const _LevelLegend({required this.maxDepth});

  @override
  Widget build(BuildContext context) {
    if (maxDepth == 0) return const SizedBox.shrink();
    return Container(
      color: BrandColors.selectedTint,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: Row(
        children: [
          const Icon(Icons.info_outline, size: 14, color: BrandColors.textMuted),
          const SizedBox(width: 6),
          Text('Nivel 1 = referido directo tuyo  ·  Nivel 2 = referido de tu referido…',
              style: const TextStyle(fontSize: 11, color: BrandColors.textMuted)),
        ],
      ),
    );
  }
}

class _NodoTile extends StatelessWidget {
  final RedNodo nodo;
  final int childrenCount;

  const _NodoTile({required this.nodo, required this.childrenCount});

  static const List<Color> _levelColors = [
    Color(0xFF1565C0), // nivel 1 — azul
    Color(0xFF2E7D32), // nivel 2 — verde
    Color(0xFF6A1B9A), // nivel 3 — morado
    Color(0xFFE65100), // nivel 4 — naranja
    Color(0xFF37474F), // nivel 5 — gris oscuro
  ];

  Color get _levelColor {
    final idx = (nodo.depth - 1).clamp(0, _levelColors.length - 1);
    return _levelColors[idx];
  }

  @override
  Widget build(BuildContext context) {
    final indent = (nodo.depth - 1) * 20.0;
    final initials = nodo.name.trim().split(RegExp(r'\s+')).take(2).map((s) => s[0].toUpperCase()).join();

    return Padding(
      padding: EdgeInsets.only(left: indent, top: 4, bottom: 4),
      child: Card(
        margin: EdgeInsets.zero,
        child: ListTile(
          contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
          leading: CircleAvatar(
            backgroundColor: _levelColor,
            radius: 20,
            child: Text(initials,
                style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13)),
          ),
          title: Text(nodo.name,
              style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
              maxLines: 1,
              overflow: TextOverflow.ellipsis),
          subtitle: Row(
            children: [
              _LevelChip(depth: nodo.depth, color: _levelColor),
              const SizedBox(width: 6),
              _StatusChip(nodo: nodo),
            ],
          ),
          trailing: childrenCount > 0
              ? Chip(
                  label: Text('$childrenCount referido${childrenCount == 1 ? '' : 's'}',
                      style: const TextStyle(fontSize: 11)),
                  padding: EdgeInsets.zero,
                  visualDensity: VisualDensity.compact,
                  backgroundColor: BrandColors.selectedTint,
                )
              : null,
        ),
      ),
    );
  }
}

class _LevelChip extends StatelessWidget {
  final int depth;
  final Color color;
  const _LevelChip({required this.depth, required this.color});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
      decoration: BoxDecoration(
        color: color.withAlpha(30),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: color.withAlpha(80)),
      ),
      child: Text('Nivel $depth',
          style: TextStyle(fontSize: 10, color: color, fontWeight: FontWeight.bold)),
    );
  }
}

class _StatusChip extends StatelessWidget {
  final RedNodo nodo;
  const _StatusChip({required this.nodo});

  @override
  Widget build(BuildContext context) {
    final color = Color(nodo.statusColor);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
      decoration: BoxDecoration(
        color: color.withAlpha(30),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Text(nodo.statusLabel,
          style: TextStyle(fontSize: 10, color: color, fontWeight: FontWeight.w600)),
    );
  }
}

// ── Empty / Error states ─────────────────────────────────────────────────────

class _EmptyView extends StatelessWidget {
  const _EmptyView();

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.account_tree_outlined, size: 72, color: Colors.grey.shade300),
            const SizedBox(height: 16),
            const Text('Aún no tienes referidos',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            const Text('Comparte tu código de referido para ver crecer tu red aquí.',
                textAlign: TextAlign.center,
                style: TextStyle(color: BrandColors.textMuted)),
          ],
        ),
      ),
    );
  }
}

class _ErrorView extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;

  const _ErrorView({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(
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
}
