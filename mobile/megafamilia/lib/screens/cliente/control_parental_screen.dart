import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../providers/control_parental_provider.dart';
import '../../theme.dart';
import '../../widgets/widgets.dart';

class ControlParentalScreen extends StatefulWidget {
  const ControlParentalScreen({super.key});
  @override
  State<ControlParentalScreen> createState() => _ControlParentalScreenState();
}

class _ControlParentalScreenState extends State<ControlParentalScreen> {
  @override
  void initState() {
    super.initState();
    Future.microtask(() => context.read<ControlParentalProvider>().loadProfiles());
  }

  Future<void> _mostrarDialogoAgregarPerfil() async {
    final formKey = GlobalKey<FormState>();
    final nameCtrl = TextEditingController();
    int edad = 8;
    bool saving = false;

    await showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setS) => AlertDialog(
          title: const Text('Agregar perfil'),
          content: Form(
            key: formKey,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextFormField(
                  controller: nameCtrl,
                  decoration: const InputDecoration(labelText: 'Nombre del hijo'),
                  textCapitalization: TextCapitalization.words,
                  validator: (v) => (v == null || v.trim().isEmpty) ? 'Ingresa un nombre' : null,
                ),
                const SizedBox(height: 16),
                Row(
                  children: [
                    const Text('Edad:'),
                    const SizedBox(width: 16),
                    IconButton(
                      onPressed: () { if (edad > 4) setS(() => edad--); },
                      icon: const Icon(Icons.remove_circle_outline),
                    ),
                    Text('$edad años', style: const TextStyle(fontWeight: FontWeight.bold)),
                    IconButton(
                      onPressed: () { if (edad < 17) setS(() => edad++); },
                      icon: const Icon(Icons.add_circle_outline),
                    ),
                  ],
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: saving ? null : () => Navigator.of(ctx).pop(),
              child: const Text('Cancelar'),
            ),
            FilledButton(
              onPressed: saving
                  ? null
                  : () async {
                      if (!formKey.currentState!.validate()) return;
                      setS(() => saving = true);
                      final prov = context.read<ControlParentalProvider>();
                      final result = await prov.createProfile(
                        name: nameCtrl.text.trim(),
                        age: edad,
                        schoolLevel: edad <= 12 ? 'primaria' : (edad <= 15 ? 'secundaria' : 'preparatoria'),
                        profileType: edad <= 10 ? 'nino' : (edad <= 14 ? 'preadolescente' : 'adolescente'),
                      );
                      if (!ctx.mounted) return;
                      Navigator.of(ctx).pop();
                      if (result == null && prov.error != null) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(content: Text(prov.error!), backgroundColor: Colors.red),
                        );
                      }
                    },
              child: saving
                  ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : const Text('Guardar'),
            ),
          ],
        ),
      ),
    );
    nameCtrl.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final p = context.watch<ControlParentalProvider>();
    return Scaffold(
      appBar: AppBar(title: const Text('Control parental')),
      body: p.loading && p.profiles.isEmpty
          ? const LoadingView()
          : p.profiles.isEmpty
              ? const CenteredState(message: 'Aún no has registrado perfiles de hijos.', icon: Icons.family_restroom)
              : ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    SizedBox(
                      height: 170,
                      child: ListView.separated(
                        scrollDirection: Axis.horizontal,
                        itemCount: p.profiles.length,
                        separatorBuilder: (_, __) => const SizedBox(width: 12),
                        itemBuilder: (_, i) {
                          final c = p.profiles[i];
                          return SizedBox(
                            width: 160,
                            child: InkWell(
                              onTap: () => context.go('/cliente/parental/${c.id}'),
                              child: Card(
                                child: Padding(
                                  padding: const EdgeInsets.all(12),
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.center,
                                    children: [
                                      CircleAvatar(
                                        radius: 32,
                                        backgroundColor: _colors[c.id % _colors.length],
                                        child: Text(c.initials, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 18)),
                                      ),
                                      const SizedBox(height: 8),
                                      Text(c.name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.bold)),
                                      Text('${c.age ?? "—"} años', style: const TextStyle(fontSize: 12, color: BrandColors.textMuted)),
                                      const SizedBox(height: 6),
                                      Chip(
                                        avatar: const Icon(Icons.smartphone, size: 14, color: BrandColors.secondary),
                                        label: Text('${c.devicesCount} disp.'),
                                        labelStyle: const TextStyle(fontSize: 11),
                                        padding: EdgeInsets.zero,
                                        materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                                        visualDensity: VisualDensity.compact,
                                      ),
                                    ],
                                  ),
                                ),
                              ),
                            ),
                          );
                        },
                      ),
                    ),
                    const SizedBox(height: 12),
                    OutlinedButton.icon(
                      onPressed: _mostrarDialogoAgregarPerfil,
                      icon: const Icon(Icons.add),
                      label: const Text('Añadir perfil'),
                    ),
                    const SizedBox(height: 24),
                    const Text('Resumen del día', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                    const SizedBox(height: 8),
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          children: [
                            const Icon(Icons.shield_moon_outlined, size: 48, color: BrandColors.warning),
                            const SizedBox(height: 8),
                            Text('${p.profiles.length} perfiles activos · ${p.profiles.fold<int>(0, (a, b) => a + b.devicesCount)} dispositivos en total', textAlign: TextAlign.center),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
    );
  }
}

const _colors = [
  Color(0xFF5B8DEF),
  Color(0xFF38C172),
  Color(0xFFFF8C00),
  Color(0xFFE91E63),
  Color(0xFF9B59B6),
  Color(0xFF16A085),
];
