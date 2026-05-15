import 'dart:async';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../theme.dart';

class BlockedScreen extends StatefulWidget {
  const BlockedScreen({super.key});
  @override
  State<BlockedScreen> createState() => _BlockedScreenState();
}

class _BlockedScreenState extends State<BlockedScreen> {
  Duration _remaining = const Duration(minutes: 47, seconds: 21);
  Timer? _t;

  @override
  void initState() {
    super.initState();
    _t = Timer.periodic(const Duration(seconds: 1), (_) {
      if (!mounted) return;
      setState(() {
        if (_remaining.inSeconds <= 0) {
          _t?.cancel();
        } else {
          _remaining = _remaining - const Duration(seconds: 1);
        }
      });
    });
  }

  @override
  void dispose() {
    _t?.cancel();
    super.dispose();
  }

  String _fmt(Duration d) {
    String two(int n) => n.toString().padLeft(2, '0');
    return '${two(d.inHours)}:${two(d.inMinutes.remainder(60))}:${two(d.inSeconds.remainder(60))}';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: BrandColors.danger,
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.lock_outline, color: Colors.white, size: 120),
              const SizedBox(height: 24),
              const Text(
                'Esta aplicación está bloqueada',
                textAlign: TextAlign.center,
                style: TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 12),
              const Text(
                'Motivo: Horario familiar',
                textAlign: TextAlign.center,
                style: TextStyle(color: Colors.white70, fontSize: 16),
              ),
              const SizedBox(height: 32),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
                decoration: BoxDecoration(
                  color: Colors.white.withOpacity(0.15),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Column(
                  children: [
                    const Text('Desbloqueo en', style: TextStyle(color: Colors.white70)),
                    const SizedBox(height: 4),
                    Text(_fmt(_remaining), style: const TextStyle(color: Colors.white, fontSize: 40, fontWeight: FontWeight.bold, fontFeatures: [FontFeature.tabularFigures()])),
                  ],
                ),
              ),
              const Spacer(),
              FilledButton.icon(
                onPressed: () => context.go('/hijo/solicitar'),
                icon: const Icon(Icons.help_outline),
                label: const Text('Solicitar permiso'),
                style: FilledButton.styleFrom(
                  backgroundColor: Colors.white,
                  foregroundColor: BrandColors.danger,
                  minimumSize: const Size.fromHeight(48),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
