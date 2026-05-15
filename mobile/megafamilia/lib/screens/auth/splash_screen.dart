import 'package:flutter/material.dart';

import '../../theme.dart';

class SplashScreen extends StatelessWidget {
  const SplashScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: BrandColors.primary,
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: const [
            Icon(Icons.shield_outlined, size: 96, color: Colors.white),
            SizedBox(height: 16),
            Text(
              'MegaFamilia',
              style: TextStyle(color: Colors.white, fontSize: 32, fontWeight: FontWeight.bold, letterSpacing: 1.2),
            ),
            SizedBox(height: 4),
            Text(
              'powered by MegaISP',
              style: TextStyle(color: Colors.white70, fontSize: 12),
            ),
            SizedBox(height: 32),
            SizedBox(
              width: 28, height: 28,
              child: CircularProgressIndicator(strokeWidth: 3, color: Colors.white),
            ),
          ],
        ),
      ),
    );
  }
}
