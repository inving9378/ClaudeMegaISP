import 'package:flutter/material.dart';
import 'package:flutter_contacts/flutter_contacts.dart';
import 'package:provider/provider.dart';

import '../../services/api_service.dart';
import '../../theme.dart';

/// Envío masivo del enlace de referido por WhatsApp a múltiples contactos
/// seleccionados desde la agenda del dispositivo.
class ShareMasivoScreen extends StatefulWidget {
  const ShareMasivoScreen({super.key});

  @override
  State<ShareMasivoScreen> createState() => _ShareMasivoScreenState();
}

class _ShareMasivoScreenState extends State<ShareMasivoScreen> {
  List<Contact> _contacts = [];
  final Set<String> _selected = {};
  bool _loadingContacts = true;
  bool _permissionDenied = false;
  bool _sending = false;
  String _search = '';

  static const int _maxContacts = 50;

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

  void _toggle(String id) {
    setState(() {
      if (_selected.contains(id)) {
        _selected.remove(id);
      } else {
        if (_selected.length >= _maxContacts) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('Máximo $_maxContacts contactos por envío'),
              behavior: SnackBarBehavior.floating,
            ),
          );
          return;
        }
        _selected.add(id);
      }
    });
  }

  Future<void> _enviar() async {
    if (_selected.isEmpty) return;

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Confirmar envío'),
        content: Text(
          '¿Enviar tu link de referido por WhatsApp a ${_selected.length} contacto${_selected.length == 1 ? '' : 's'}?\n\n'
          'Cada contacto recibirá un mensaje personalizado.',
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancelar')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Enviar')),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    setState(() => _sending = true);

    // Armar lista de {name, phone}
    final selectedContacts = _contacts.where((c) => _selected.contains(c.id)).toList();
    final payload = selectedContacts.map((c) => {
      'name': c.displayName,
      'phone': c.phones.first.number,
    }).toList();

    try {
      final api = context.read<ApiService>();
      final result = await api.shareMasivo(payload);
      if (!mounted) return;
      setState(() => _selected.clear());
      _showResult(result);
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(e.toString()),
        backgroundColor: BrandColors.danger,
      ));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  void _showResult(Map<String, dynamic> result) {
    final sent   = (result['sent']   as num?)?.toInt() ?? 0;
    final failed = (result['failed'] as num?)?.toInt() ?? 0;
    final msg    = result['message']?.toString() ?? '';

    showDialog(
      context: context,
      builder: (_) => AlertDialog(
        title: Row(
          children: [
            Icon(sent > 0 ? Icons.check_circle : Icons.warning,
                color: sent > 0 ? BrandColors.success : BrandColors.warning),
            const SizedBox(width: 8),
            const Text('Envío completado'),
          ],
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(msg),
            if (failed > 0) ...[
              const SizedBox(height: 8),
              const Text(
                'Los números no disponibles pueden no tener WhatsApp o estar fuera de servicio.',
                style: TextStyle(fontSize: 12, color: BrandColors.textMuted),
                textAlign: TextAlign.center,
              ),
            ],
          ],
        ),
        actions: [
          FilledButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Listo'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Compartir a contactos'),
        actions: [
          if (_selected.isNotEmpty && !_sending)
            Padding(
              padding: const EdgeInsets.only(right: 8),
              child: FilledButton.icon(
                onPressed: _enviar,
                icon: const Icon(Icons.send, size: 18),
                label: Text('Enviar (${_selected.length})'),
                style: FilledButton.styleFrom(
                  backgroundColor: Colors.white,
                  foregroundColor: BrandColors.primary,
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                ),
              ),
            ),
        ],
      ),
      body: _buildBody(),
      bottomNavigationBar: _selected.isNotEmpty
          ? _BottomBar(count: _selected.length, sending: _sending, onSend: _enviar)
          : null,
    );
  }

  Widget _buildBody() {
    if (_loadingContacts) {
      return const Center(child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          CircularProgressIndicator(),
          SizedBox(height: 16),
          Text('Cargando agenda…', style: TextStyle(color: BrandColors.textMuted)),
        ],
      ));
    }

    if (_permissionDenied) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.contacts, size: 64, color: BrandColors.textMuted),
              const SizedBox(height: 16),
              const Text('Permiso de agenda requerido',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
              const SizedBox(height: 8),
              const Text(
                'Para compartir tu link de referido por WhatsApp, necesitamos acceder a tu agenda.',
                textAlign: TextAlign.center,
                style: TextStyle(color: BrandColors.textMuted),
              ),
              const SizedBox(height: 16),
              FilledButton.icon(
                onPressed: _requestAndLoad,
                icon: const Icon(Icons.settings),
                label: const Text('Dar permiso'),
              ),
            ],
          ),
        ),
      );
    }

    if (_sending) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const CircularProgressIndicator(),
            const SizedBox(height: 16),
            Text('Enviando a ${_selected.length} contacto${_selected.length == 1 ? '' : 's'}…',
                style: const TextStyle(color: BrandColors.textMuted)),
          ],
        ),
      );
    }

    final filtered = _filtered;

    return Column(
      children: [
        _InfoBanner(selectedCount: _selected.length, maxCount: _maxContacts),
        _SearchBar(
          onChanged: (v) => setState(() => _search = v),
        ),
        Expanded(
          child: filtered.isEmpty
              ? const Center(child: Text('Sin resultados', style: TextStyle(color: BrandColors.textMuted)))
              : ListView.builder(
                  itemCount: filtered.length,
                  itemBuilder: (_, i) {
                    final c = filtered[i];
                    final isSelected = _selected.contains(c.id);
                    final phone = c.phones.isNotEmpty ? c.phones.first.number : '';

                    return CheckboxListTile(
                      value: isSelected,
                      onChanged: (_) => _toggle(c.id),
                      secondary: CircleAvatar(
                        backgroundColor: isSelected ? BrandColors.primary : Colors.grey.shade200,
                        child: Text(
                          c.displayName.isNotEmpty ? c.displayName[0].toUpperCase() : '?',
                          style: TextStyle(
                            color: isSelected ? Colors.white : BrandColors.textMuted,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ),
                      title: Text(c.displayName,
                          style: TextStyle(
                              fontWeight: isSelected ? FontWeight.bold : FontWeight.normal)),
                      subtitle: phone.isNotEmpty ? Text(phone, style: const TextStyle(fontSize: 12)) : null,
                      activeColor: BrandColors.primary,
                    );
                  },
                ),
        ),
      ],
    );
  }
}

class _InfoBanner extends StatelessWidget {
  final int selectedCount;
  final int maxCount;
  const _InfoBanner({required this.selectedCount, required this.maxCount});

  @override
  Widget build(BuildContext context) {
    return Container(
      color: BrandColors.selectedTint,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      child: Row(
        children: [
          const Icon(Icons.chat_bubble, color: Color(0xFF25D366), size: 18),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              selectedCount == 0
                  ? 'Selecciona contactos para enviarles tu link por WhatsApp (máx. $maxCount)'
                  : '$selectedCount seleccionado${selectedCount == 1 ? '' : 's'} — toca Enviar para continuar',
              style: const TextStyle(fontSize: 12, color: BrandColors.textMuted),
            ),
          ),
        ],
      ),
    );
  }
}

class _SearchBar extends StatelessWidget {
  final ValueChanged<String> onChanged;
  const _SearchBar({required this.onChanged});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 10, 12, 4),
      child: TextField(
        decoration: InputDecoration(
          hintText: 'Buscar contacto…',
          prefixIcon: const Icon(Icons.search),
          border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
          contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          isDense: true,
          filled: true,
          fillColor: Colors.white,
        ),
        onChanged: onChanged,
      ),
    );
  }
}

class _BottomBar extends StatelessWidget {
  final int count;
  final bool sending;
  final VoidCallback onSend;

  const _BottomBar({required this.count, required this.sending, required this.onSend});

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: SizedBox(
          width: double.infinity,
          height: 50,
          child: FilledButton.icon(
            onPressed: sending ? null : onSend,
            icon: sending
                ? const SizedBox(
                    width: 18, height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                : const Icon(Icons.send),
            label: Text(sending
                ? 'Enviando…'
                : 'Enviar a $count contacto${count == 1 ? '' : 's'} por WhatsApp'),
          ),
        ),
      ),
    );
  }
}
