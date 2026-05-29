import 'package:flutter/material.dart';
import 'package:flutter_contacts/flutter_contacts.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../providers/embajador_provider.dart';

/// Importación masiva de prospectos desde la agenda del dispositivo.
/// Requiere permiso READ_CONTACTS (solicitado en tiempo de ejecución).
class ProspectoImportScreen extends StatefulWidget {
  const ProspectoImportScreen({super.key});

  @override
  State<ProspectoImportScreen> createState() => _ProspectoImportScreenState();
}

class _ProspectoImportScreenState extends State<ProspectoImportScreen> {
  List<Contact> _contacts = [];
  final Set<String> _selected = {};
  bool _loadingContacts = true;
  bool _permissionDenied = false;
  bool _importing = false;
  String _search = '';

  @override
  void initState() {
    super.initState();
    _requestAndLoad();
  }

  Future<void> _requestAndLoad() async {
    final granted = await FlutterContacts.requestPermission();
    if (!mounted) return;

    if (!granted) {
      setState(() {
        _permissionDenied = true;
        _loadingContacts = false;
      });
      return;
    }

    final contacts = await FlutterContacts.getContacts(
        withProperties: true, withPhoto: false);
    if (!mounted) return;

    // Solo contactos con al menos un número de teléfono
    final valid = contacts.where((c) => c.phones.isNotEmpty).toList()
      ..sort((a, b) => a.displayName.compareTo(b.displayName));

    setState(() {
      _contacts = valid;
      _loadingContacts = false;
    });
  }

  List<Contact> get _filtered {
    if (_search.isEmpty) return _contacts;
    final q = _search.toLowerCase();
    return _contacts
        .where((c) =>
            c.displayName.toLowerCase().contains(q) ||
            c.phones.any((p) => p.number.contains(q)))
        .toList();
  }

  void _toggleAll(bool select) {
    setState(() {
      if (select) {
        _selected.addAll(_filtered.map((c) => c.id));
      } else {
        _selected.removeAll(_filtered.map((c) => c.id));
      }
    });
  }

  Future<void> _importar() async {
    if (_selected.isEmpty) return;
    setState(() => _importing = true);

    final toImport = _contacts
        .where((c) => _selected.contains(c.id))
        .map((c) => {
              'name': c.displayName,
              'phone': c.phones.first.number,
              if (c.emails.isNotEmpty) 'email': c.emails.first.address,
            })
        .toList();

    final result = await context.read<EmbajadorProvider>().importarContactos(toImport);

    if (!mounted) return;
    setState(() => _importing = false);

    if (result != null) {
      final created = result['created'] ?? 0;
      final skipped = result['skipped'] ?? 0;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text('$created importado(s), $skipped duplicado(s) omitido(s).'),
      ));
      context.pop(true);
    } else {
      final error = context.read<EmbajadorProvider>().error ?? 'Error al importar.';
      ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(error), backgroundColor: Colors.red));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(_selected.isEmpty
            ? 'Importar contactos'
            : '${_selected.length} seleccionado${_selected.length == 1 ? '' : 's'}'),
        actions: [
          if (!_loadingContacts && !_permissionDenied && _contacts.isNotEmpty)
            PopupMenuButton<String>(
              onSelected: (v) => _toggleAll(v == 'all'),
              itemBuilder: (_) => [
                const PopupMenuItem(value: 'all', child: Text('Seleccionar todos')),
                const PopupMenuItem(value: 'none', child: Text('Deseleccionar todos')),
              ],
            ),
        ],
      ),
      body: _buildBody(),
      bottomNavigationBar: _selected.isNotEmpty
          ? SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: FilledButton(
                  onPressed: _importing ? null : _importar,
                  child: _importing
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(
                              strokeWidth: 2, color: Colors.white))
                      : Text('Importar ${_selected.length} contacto${_selected.length == 1 ? '' : 's'}'),
                ),
              ),
            )
          : null,
    );
  }

  Widget _buildBody() {
    if (_loadingContacts) {
      return const Center(
        child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
          CircularProgressIndicator(),
          SizedBox(height: 12),
          Text('Cargando agenda…'),
        ]),
      );
    }

    if (_permissionDenied) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
            Icon(Icons.contacts, size: 64, color: Colors.grey[400]),
            const SizedBox(height: 16),
            const Text('Permiso de contactos denegado',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
                textAlign: TextAlign.center),
            const SizedBox(height: 8),
            Text(
                'Para importar desde tu agenda, activa el permiso de contactos en Configuración.',
                textAlign: TextAlign.center,
                style: TextStyle(color: Colors.grey[600])),
            const SizedBox(height: 16),
            OutlinedButton(
                onPressed: _requestAndLoad, child: const Text('Reintentar')),
          ]),
        ),
      );
    }

    if (_contacts.isEmpty) {
      return const Center(child: Text('No se encontraron contactos con número de teléfono.'));
    }

    final filtered = _filtered;

    return Column(
      children: [
        // ── Buscador
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 10, 12, 6),
          child: TextField(
            decoration: InputDecoration(
              hintText: 'Buscar en agenda…',
              prefixIcon: const Icon(Icons.search),
              border: const OutlineInputBorder(
                  borderRadius: BorderRadius.all(Radius.circular(12))),
              isDense: true,
              suffixIcon: _search.isNotEmpty
                  ? IconButton(
                      icon: const Icon(Icons.clear),
                      onPressed: () => setState(() => _search = ''))
                  : null,
            ),
            onChanged: (v) => setState(() => _search = v),
          ),
        ),

        // ── Contador
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          child: Row(children: [
            Text('${filtered.length} contacto${filtered.length == 1 ? '' : 's'}',
                style: Theme.of(context).textTheme.bodySmall),
          ]),
        ),

        // ── Lista
        Expanded(
          child: ListView.builder(
            itemCount: filtered.length,
            itemBuilder: (ctx, i) {
              final c = filtered[i];
              final isSelected = _selected.contains(c.id);
              return CheckboxListTile(
                value: isSelected,
                onChanged: (_) {
                  setState(() {
                    if (isSelected) {
                      _selected.remove(c.id);
                    } else {
                      _selected.add(c.id);
                    }
                  });
                },
                secondary: CircleAvatar(
                  child: Text(c.displayName.isNotEmpty
                      ? c.displayName[0].toUpperCase()
                      : '?'),
                ),
                title: Text(c.displayName,
                    style: const TextStyle(fontWeight: FontWeight.w500)),
                subtitle: Text(c.phones.first.number,
                    style: const TextStyle(fontSize: 12)),
                controlAffinity: ListTileControlAffinity.trailing,
              );
            },
          ),
        ),
      ],
    );
  }
}
