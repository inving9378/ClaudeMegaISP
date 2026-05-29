import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../models/models.dart';
import '../../providers/embajador_provider.dart';

class ProspectosScreen extends StatefulWidget {
  const ProspectosScreen({super.key});

  @override
  State<ProspectosScreen> createState() => _ProspectosScreenState();
}

class _ProspectosScreenState extends State<ProspectosScreen> {
  final _searchCtrl = TextEditingController();
  String? _filtroStatus;
  final _scrollCtrl = ScrollController();

  static const _statusFiltros = [
    ('', 'Todos'),
    ('new', 'Nuevo'),
    ('contacted', 'Contactado'),
    ('interested', 'Interesado'),
    ('quoted', 'Cotizado'),
    ('converted', 'Convertido'),
    ('lost', 'Perdido'),
  ];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<EmbajadorProvider>().loadProspectos();
    });
    _scrollCtrl.addListener(_onScroll);
  }

  void _onScroll() {
    if (_scrollCtrl.position.pixels >= _scrollCtrl.position.maxScrollExtent - 200) {
      context.read<EmbajadorProvider>().loadNextPage();
    }
  }

  void _buscar() {
    context.read<EmbajadorProvider>().loadProspectos(
          search: _searchCtrl.text.trim(),
          status: _filtroStatus,
        );
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    _scrollCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final prov = context.watch<EmbajadorProvider>();

    return Scaffold(
      appBar: AppBar(
        title: const Text('Mis Prospectos'),
        actions: [
          IconButton(
            icon: const Icon(Icons.upload_file),
            tooltip: 'Importar desde agenda',
            onPressed: () => context.push('/cliente/embajador/prospectos/import'),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () async {
          final ok = await context.push<bool>('/cliente/embajador/prospectos/nuevo');
          if (ok == true) _buscar();
        },
        icon: const Icon(Icons.person_add),
        label: const Text('Nuevo'),
      ),
      body: Column(
        children: [
          // ── Buscador
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 10, 12, 4),
            child: TextField(
              controller: _searchCtrl,
              decoration: InputDecoration(
                hintText: 'Buscar por nombre o teléfono…',
                prefixIcon: const Icon(Icons.search),
                suffixIcon: _searchCtrl.text.isNotEmpty
                    ? IconButton(
                        icon: const Icon(Icons.clear),
                        onPressed: () {
                          _searchCtrl.clear();
                          _buscar();
                        },
                      )
                    : null,
                border: const OutlineInputBorder(
                    borderRadius: BorderRadius.all(Radius.circular(12))),
                isDense: true,
              ),
              onSubmitted: (_) => _buscar(),
              textInputAction: TextInputAction.search,
            ),
          ),

          // ── Filtros de status
          SizedBox(
            height: 44,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 12),
              children: _statusFiltros.map((f) {
                final selected = (_filtroStatus ?? '') == f.$1;
                return Padding(
                  padding: const EdgeInsets.only(right: 6),
                  child: FilterChip(
                    label: Text(f.$2),
                    selected: selected,
                    onSelected: (_) {
                      setState(() => _filtroStatus = f.$1.isEmpty ? null : f.$1);
                      _buscar();
                    },
                  ),
                );
              }).toList(),
            ),
          ),

          // ── Contador
          if (!prov.loading)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 4, 16, 0),
              child: Align(
                alignment: Alignment.centerLeft,
                child: Text(
                  '${prov.total} prospecto${prov.total == 1 ? '' : 's'}',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ),
            ),

          // ── Lista
          Expanded(
            child: prov.loading
                ? const Center(child: CircularProgressIndicator())
                : prov.error != null
                    ? _ErrorView(msg: prov.error!, onRetry: _buscar)
                    : prov.prospectos.isEmpty
                        ? _EmptyView(onNuevo: () => context.push('/cliente/embajador/prospectos/nuevo'))
                        : RefreshIndicator(
                            onRefresh: () => context.read<EmbajadorProvider>().loadProspectos(
                                  search: _searchCtrl.text.trim(),
                                  status: _filtroStatus,
                                ),
                            child: ListView.builder(
                              controller: _scrollCtrl,
                              itemCount: prov.prospectos.length + (prov.hasMore ? 1 : 0),
                              itemBuilder: (ctx, i) {
                                if (i == prov.prospectos.length) {
                                  return const Padding(
                                    padding: EdgeInsets.all(16),
                                    child: Center(child: CircularProgressIndicator()),
                                  );
                                }
                                return _ProspectCard(prospect: prov.prospectos[i]);
                              },
                            ),
                          ),
          ),
        ],
      ),
    );
  }
}

// ── Tarjeta de prospecto ────────────────────────────────────────────────────

class _ProspectCard extends StatelessWidget {
  final Prospect prospect;
  const _ProspectCard({required this.prospect});

  @override
  Widget build(BuildContext context) {
    final color = Color(prospect.statusColorValue);

    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: color.withOpacity(0.15),
          child: Text(
            prospect.name.isNotEmpty ? prospect.name[0].toUpperCase() : '?',
            style: TextStyle(color: color, fontWeight: FontWeight.bold),
          ),
        ),
        title: Text(prospect.name, style: const TextStyle(fontWeight: FontWeight.w600)),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(prospect.phone, style: const TextStyle(fontSize: 12)),
            const SizedBox(height: 2),
            Row(children: [
              _StatusChip(status: prospect.status, label: prospect.statusLabel),
              if ((prospect.followupsCount ?? 0) > 0) ...[
                const SizedBox(width: 6),
                Icon(Icons.comment_outlined, size: 12, color: Colors.grey[600]),
                const SizedBox(width: 2),
                Text('${prospect.followupsCount}',
                    style: TextStyle(fontSize: 11, color: Colors.grey[600])),
              ],
            ]),
          ],
        ),
        trailing: const Icon(Icons.chevron_right),
        onTap: () => context.push('/cliente/embajador/prospectos/${prospect.id}'),
      ),
    );
  }
}

class _StatusChip extends StatelessWidget {
  final String status;
  final String label;
  const _StatusChip({required this.status, required this.label});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(
        color: Color(Prospect(
          id: 0, name: '', phone: '', source: '', status: status,
        ).statusColorValue).withOpacity(0.15),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Text(label,
          style: TextStyle(
            fontSize: 11,
            color: Color(Prospect(
              id: 0, name: '', phone: '', source: '', status: status,
            ).statusColorValue),
          )),
    );
  }
}

// ── States vacíos ───────────────────────────────────────────────────────────

class _EmptyView extends StatelessWidget {
  final VoidCallback onNuevo;
  const _EmptyView({required this.onNuevo});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
        Icon(Icons.people_outline, size: 64, color: Colors.grey[400]),
        const SizedBox(height: 12),
        const Text('Aún no tienes prospectos',
            style: TextStyle(fontSize: 16, fontWeight: FontWeight.w500)),
        const SizedBox(height: 6),
        Text('Agrega tu primer contacto potencial',
            style: TextStyle(color: Colors.grey[600])),
        const SizedBox(height: 16),
        FilledButton.icon(
          onPressed: onNuevo,
          icon: const Icon(Icons.person_add),
          label: const Text('Agregar prospecto'),
        ),
      ]),
    );
  }
}

class _ErrorView extends StatelessWidget {
  final String msg;
  final VoidCallback onRetry;
  const _ErrorView({required this.msg, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
        Icon(Icons.error_outline, size: 48, color: Colors.red[300]),
        const SizedBox(height: 8),
        Text(msg, textAlign: TextAlign.center,
            style: const TextStyle(fontSize: 14)),
        const SizedBox(height: 12),
        OutlinedButton(onPressed: onRetry, child: const Text('Reintentar')),
      ]),
    );
  }
}
