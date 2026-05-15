import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import 'providers/auth_provider.dart';
import 'screens/auth/login_screen.dart';
import 'screens/auth/splash_screen.dart';
import 'screens/cliente/child_detail_screen.dart';
import 'screens/cliente/cliente_dashboard.dart';
import 'screens/cliente/control_parental_screen.dart';
import 'screens/cliente/factura_detail_screen.dart';
import 'screens/cliente/facturas_screen.dart';
import 'screens/cliente/mi_servicio_screen.dart';
import 'screens/cliente/new_ticket_screen.dart';
import 'screens/cliente/pagos_screen.dart';
import 'screens/cliente/tickets_screen.dart';
import 'screens/hijo/blocked_screen.dart';
import 'screens/hijo/hijo_dashboard.dart';
import 'screens/hijo/logros_screen.dart';
import 'screens/hijo/solicitar_screen.dart';
import 'screens/hijo/tareas_screen.dart';
import 'screens/tecnico/completar_orden_screen.dart';
import 'screens/tecnico/orden_detail_screen.dart';
import 'screens/tecnico/ordenes_screen.dart';
import 'screens/tecnico/tecnico_dashboard.dart';
import 'screens/tecnico/work_flow_screen.dart';

/// Construye un GoRouter cuyo redirect lee AuthProvider para decidir si:
///   - hay que mostrar /login (no autenticado)
///   - hay que mandar al dashboard del rol
GoRouter buildRouter(BuildContext context) {
  return GoRouter(
    initialLocation: '/splash',
    refreshListenable: context.read<AuthProvider>(),
    debugLogDiagnostics: false,
    redirect: (ctx, state) {
      final auth = ctx.read<AuthProvider>();
      final loc = state.matchedLocation;

      // Mientras AuthProvider boot está pendiente, dejamos /splash.
      if (auth.state == AuthState.unknown) return loc == '/splash' ? null : '/splash';

      final atLogin = loc == '/login';
      final atSplash = loc == '/splash';

      if (!auth.isAuthed) {
        return atLogin ? null : '/login';
      }

      // Autenticado — si está en /splash o /login, llévalo al home de su rol.
      if (atLogin || atSplash) return _homeForRole(auth.role);
      return null;
    },
    routes: [
      GoRoute(path: '/splash', builder: (_, __) => const SplashScreen()),
      GoRoute(path: '/login', builder: (_, __) => const LoginScreen()),

      // -------- CLIENTE
      GoRoute(path: '/cliente', builder: (_, __) => const ClienteDashboard(), routes: [
        GoRoute(path: 'servicio', builder: (_, __) => const MiServicioScreen()),
        GoRoute(path: 'facturas', builder: (_, __) => const FacturasScreen()),
        GoRoute(path: 'facturas/:id', builder: (_, s) => FacturaDetailScreen(facturaId: int.parse(s.pathParameters['id']!))),
        GoRoute(path: 'pagos', builder: (_, __) => const PagosScreen()),
        GoRoute(path: 'tickets', builder: (_, __) => const TicketsScreen()),
        GoRoute(path: 'tickets/nuevo', builder: (_, __) => const NewTicketScreen()),
        GoRoute(path: 'parental', builder: (_, __) => const ControlParentalScreen()),
        GoRoute(path: 'parental/:id', builder: (_, s) => ChildDetailScreen(childId: int.parse(s.pathParameters['id']!))),
      ]),

      // -------- TECNICO
      GoRoute(path: '/tecnico', builder: (_, __) => const TecnicoDashboard(), routes: [
        GoRoute(path: 'ordenes', builder: (_, __) => const OrdenesScreen()),
        GoRoute(path: 'ordenes/:id', builder: (_, s) => OrdenDetailScreen(ordenId: int.parse(s.pathParameters['id']!))),
        GoRoute(path: 'ordenes/:id/workflow', builder: (_, s) => WorkFlowScreen(ordenId: int.parse(s.pathParameters['id']!))),
        GoRoute(path: 'ordenes/:id/completar', builder: (_, s) => CompletarOrdenScreen(ordenId: int.parse(s.pathParameters['id']!))),
      ]),

      // -------- HIJO
      GoRoute(path: '/hijo', builder: (_, __) => const HijoDashboard(), routes: [
        GoRoute(path: 'tareas', builder: (_, __) => const TareasScreen()),
        GoRoute(path: 'logros', builder: (_, __) => const LogrosScreen()),
        GoRoute(path: 'solicitar', builder: (_, __) => const SolicitarScreen()),
        GoRoute(path: 'blocked', builder: (_, __) => const BlockedScreen()),
      ]),

      // -------- ADMIN — redirect a web
      GoRoute(path: '/admin-redirect', builder: (_, __) => const _AdminRedirectScreen()),
    ],
  );
}

String _homeForRole(String role) {
  switch (role.toLowerCase()) {
    case 'tecnico':
    case 'técnico':
      return '/tecnico';
    case 'hijo':
    case 'nino':
    case 'preadolescente':
    case 'adolescente':
      return '/hijo';
    case 'administrador':
    case 'super-administrator':
    case 'super administrador':
    case 'desarrollador':
    case 'admin':
      return '/admin-redirect';
    case 'cliente':
    case 'padre':
    case 'client':
    default:
      return '/cliente';
  }
}

class _AdminRedirectScreen extends StatelessWidget {
  const _AdminRedirectScreen();
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.web, size: 64),
              const SizedBox(height: 12),
              const Text(
                'Las cuentas administrativas usan el panel web.',
                textAlign: TextAlign.center,
                style: TextStyle(fontSize: 16),
              ),
              const SizedBox(height: 16),
              FilledButton.icon(
                onPressed: () => context.read<AuthProvider>().logout(),
                icon: const Icon(Icons.logout),
                label: const Text('Cerrar sesión'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
