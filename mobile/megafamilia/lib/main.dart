import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'providers/auth_provider.dart';
import 'providers/cliente_provider.dart';
import 'providers/control_parental_provider.dart';
import 'providers/hijo_provider.dart';
import 'providers/tecnico_provider.dart';
import 'router.dart';
import 'services/api_service.dart';
import 'services/storage_service.dart';
import 'theme.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  final api = ApiService();
  final storage = StorageService();

  runApp(
    MultiProvider(
      providers: [
        Provider<ApiService>.value(value: api),
        Provider<StorageService>.value(value: storage),
        ChangeNotifierProvider(
          create: (_) => AuthProvider(api: api, storage: storage)..bootstrap(),
        ),
        ChangeNotifierProvider(create: (_) => ClienteProvider(api: api)),
        ChangeNotifierProvider(create: (_) => ControlParentalProvider(api: api)),
        ChangeNotifierProvider(create: (_) => TecnicoProvider(api: api)),
        ChangeNotifierProvider(create: (_) => HijoProvider(api: api)),
      ],
      child: const MegaFamiliaApp(),
    ),
  );
}

class MegaFamiliaApp extends StatelessWidget {
  const MegaFamiliaApp({super.key});

  @override
  Widget build(BuildContext context) {
    return Builder(builder: (ctx) {
      final router = buildRouter(ctx);
      return MaterialApp.router(
        title: 'MegaFamilia',
        debugShowCheckedModeBanner: false,
        theme: AppTheme.light(),
        darkTheme: AppTheme.dark(),
        routerConfig: router,
      );
    });
  }
}
