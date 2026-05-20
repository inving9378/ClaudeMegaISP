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

  // En release Flutter pinta un cuadro gris cuando un widget lanza una
  // excepción. Lo reemplazamos por una caja roja con el error legible
  // para que NUNCA volvamos a ver "está todo gris" sin saber por qué.
  ErrorWidget.builder = (FlutterErrorDetails details) {
    return Material(
      color: const Color(0xFFFFCDD2),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: SingleChildScrollView(
          child: Text(
            'Error de UI:\n${details.exceptionAsString()}',
            style: const TextStyle(color: Color(0xFFB71C1C), fontSize: 12),
          ),
        ),
      ),
    );
  };

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
