import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../providers/hijo_provider.dart';
import '../../theme.dart';

class SolicitarScreen extends StatefulWidget {
  const SolicitarScreen({super.key});
  @override
  State<SolicitarScreen> createState() => _SolicitarScreenState();
}

class _SolicitarScreenState extends State<SolicitarScreen> {
  String _type = 'time_extra';
  final _message = TextEditingController();
  bool _submitting = false;

  @override
  void dispose() {
    _message.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() => _submitting = true);
    final ok = await context.read<HijoProvider>().enviarSolicitud({
      'type': _type,
      'message': _message.text.trim(),
    });
    setState(() => _submitting = false);
    if (ok && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Solicitud enviada — tus papás recibirán la notificación')));
      context.go('/hijo');
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Solicitar')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          const Text('¿Qué necesitas?', style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
          const SizedBox(height: 16),
          _option(value: 'time_extra', label: 'Más tiempo', subtitle: '+30 min de pantalla', icon: Icons.timer, color: BrandColors.success),
          _option(value: 'app_unlock', label: 'Desbloquear app', subtitle: 'Pedir acceso a una app bloqueada', icon: Icons.lock_open, color: BrandColors.secondary),
          _option(value: 'web_unlock', label: 'Acceder a sitio web', subtitle: 'Pedir permiso para un sitio bloqueado', icon: Icons.public, color: BrandColors.warning),
          const SizedBox(height: 20),
          const Text('Mensaje (opcional)', style: TextStyle(fontWeight: FontWeight.bold)),
          const SizedBox(height: 8),
          TextField(
            controller: _message,
            maxLines: 4,
            decoration: const InputDecoration(hintText: 'Cuéntale a tus papás por qué lo necesitas…'),
          ),
          const SizedBox(height: 24),
          FilledButton.icon(
            onPressed: _submitting ? null : _submit,
            icon: const Icon(Icons.send),
            label: _submitting
                ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                : const Text('Enviar solicitud'),
          ),
          const SizedBox(height: 12),
          const Center(
            child: Text('Tu papá/mamá recibirá tu solicitud.', style: TextStyle(color: BrandColors.textMuted, fontStyle: FontStyle.italic)),
          ),
        ],
      ),
    );
  }

  Widget _option({required String value, required String label, required String subtitle, required IconData icon, required Color color}) {
    final selected = _type == value;
    return Card(
      color: selected ? color.withOpacity(0.08) : null,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(color: selected ? color : Colors.grey.shade200, width: selected ? 2 : 1),
      ),
      child: ListTile(
        leading: CircleAvatar(backgroundColor: color.withOpacity(0.15), child: Icon(icon, color: color)),
        title: Text(label, style: TextStyle(fontWeight: FontWeight.bold, color: selected ? color : null)),
        subtitle: Text(subtitle),
        trailing: Radio<String>(
          value: value,
          groupValue: _type,
          onChanged: (v) => setState(() => _type = v ?? value),
          activeColor: color,
        ),
        onTap: () => setState(() => _type = value),
      ),
    );
  }
}
