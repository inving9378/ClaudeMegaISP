import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:http/http.dart' as http;
import 'package:provider/provider.dart';

import '../../config.dart';
import '../../providers/auth_provider.dart';
import '../../theme.dart';
import '../../widgets/widgets.dart';

class PerfilScreen extends StatefulWidget {
  const PerfilScreen({super.key});
  @override
  State<PerfilScreen> createState() => _PerfilScreenState();
}

class _PerfilScreenState extends State<PerfilScreen> {
  Map<String, dynamic>? _profile;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final auth = context.read<AuthProvider>();
      final token = auth.session?.token ?? '';
      final res = await http.get(
        Uri.parse('${AppConfig.apiBaseUrl}/profile'),
        headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
      ).timeout(const Duration(seconds: 10));
      if (res.statusCode == 200) {
        setState(() {
          _profile = jsonDecode(res.body) as Map<String, dynamic>;
          _loading = false;
        });
      } else {
        setState(() {
          _error = 'No se pudo cargar el perfil (HTTP ${res.statusCode})';
          _loading = false;
        });
      }
    } catch (e) {
      setState(() {
        _error = 'Sin conexión';
        _loading = false;
      });
    }
  }

  String _initials(String name) {
    final parts = name.trim().split(RegExp(r'\s+')).where((s) => s.isNotEmpty).toList();
    if (parts.isEmpty) return '?';
    return parts.take(2).map((p) => p[0].toUpperCase()).join();
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final sessionName = auth.session?.name ?? 'Cliente';
    final p = _profile;

    return Scaffold(
      appBar: AppBar(title: const Text('Perfil')),
      body: _loading
          ? const LoadingView()
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Center(
                  child: CircleAvatar(
                    radius: 48,
                    backgroundColor: BrandColors.primary,
                    child: Text(
                      _initials(p?['name']?.toString() ?? sessionName),
                      style: const TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.bold),
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                Center(
                  child: Text(
                    p?['name']?.toString() ?? sessionName,
                    style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
                  ),
                ),
                if (p?['role'] != null) Center(
                  child: Padding(
                    padding: const EdgeInsets.only(top: 4),
                    child: Text(p!['role'].toString().toUpperCase(), style: const TextStyle(color: BrandColors.textMuted, fontSize: 12, letterSpacing: 1)),
                  ),
                ),
                const SizedBox(height: 24),
                if (_error != null)
                  Card(
                    color: BrandColors.warning.withOpacity(0.1),
                    child: Padding(
                      padding: const EdgeInsets.all(12),
                      child: Row(children: [
                        const Icon(Icons.warning_amber, color: BrandColors.warning),
                        const SizedBox(width: 8),
                        Expanded(child: Text(_error!)),
                      ]),
                    ),
                  ),
                Card(
                  child: Column(
                    children: [
                      _row(Icons.email_outlined, 'Email', p?['email']?.toString() ?? auth.session?.email ?? '—'),
                      const Divider(height: 1),
                      _row(Icons.phone_outlined, 'Teléfono', p?['phone']?.toString() ?? '—'),
                      const Divider(height: 1),
                      _row(Icons.location_on_outlined, 'Dirección', p?['address']?.toString() ?? 'No registrada'),
                    ],
                  ),
                ),
                const SizedBox(height: 24),
                OutlinedButton.icon(
                  onPressed: () async {
                    final confirmed = await showDialog<bool>(
                      context: context,
                      builder: (_) => AlertDialog(
                        title: const Text('¿Cerrar sesión?'),
                        content: const Text('Tendrás que volver a iniciar sesión la próxima vez.'),
                        actions: [
                          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancelar')),
                          FilledButton(
                            style: FilledButton.styleFrom(backgroundColor: BrandColors.danger),
                            onPressed: () => Navigator.pop(context, true),
                            child: const Text('Cerrar sesión'),
                          ),
                        ],
                      ),
                    );
                    if (confirmed == true && context.mounted) {
                      await context.read<AuthProvider>().logout();
                      if (context.mounted) context.go('/login');
                    }
                  },
                  icon: const Icon(Icons.logout, color: BrandColors.danger),
                  label: const Text('Cerrar sesión', style: TextStyle(color: BrandColors.danger)),
                  style: OutlinedButton.styleFrom(
                    side: const BorderSide(color: BrandColors.danger),
                    minimumSize: const Size.fromHeight(48),
                  ),
                ),
              ],
            ),
    );
  }

  Widget _row(IconData icon, String label, String value) {
    return ListTile(
      leading: Icon(icon, color: BrandColors.textMuted),
      title: Text(label, style: const TextStyle(fontSize: 12, color: BrandColors.textMuted)),
      subtitle: Text(value, style: const TextStyle(fontSize: 15, color: BrandColors.textPrimary)),
    );
  }
}
