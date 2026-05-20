import 'package:flutter/foundation.dart';

import '../models/models.dart';
import '../services/api_service.dart';
import '../services/storage_service.dart';

enum AuthState { unknown, unauthenticated, authenticated }

class AuthProvider extends ChangeNotifier {
  final ApiService api;
  final StorageService storage;

  AuthState _state = AuthState.unknown;
  UserSession? _session;
  String? _errorMsg;
  bool _busy = false;

  AuthProvider({required this.api, required this.storage});

  AuthState get state => _state;
  UserSession? get session => _session;
  String? get errorMsg => _errorMsg;
  bool get busy => _busy;
  String get role => _session?.role ?? '';
  bool get isAuthed => _state == AuthState.authenticated;

  /// Llamado por main() al arrancar. Restaura sesión persistida si existe.
  Future<void> bootstrap() async {
    final s = await storage.loadSession();
    if (s != null && s.token.isNotEmpty) {
      _session = s;
      api.setToken(s.token);
      _state = AuthState.authenticated;
    } else {
      _state = AuthState.unauthenticated;
    }
    notifyListeners();
  }

  Future<bool> login(String email, String password) async {
    _busy = true;
    _errorMsg = null;
    notifyListeners();
    try {
      final s = await api.login(email, password);
      _session = s;
      _state = AuthState.authenticated;
      await storage.saveSession(s);
      return true;
    } on ApiException catch (e) {
      _errorMsg = e.message;
      return false;
    } catch (e) {
      _errorMsg = 'Error de conexión: $e';
      return false;
    } finally {
      _busy = false;
      notifyListeners();
    }
  }

  Future<void> logout() async {
    _session = null;
    _state = AuthState.unauthenticated;
    api.setToken(null);
    await storage.clearSession();
    notifyListeners();
  }

  /// Helper para llamar tras una `UnauthorizedException` desde cualquier
  /// pantalla — limpia sesión y redirige.
  Future<void> sessionExpired() async {
    _errorMsg = 'Tu sesión expiró. Por favor inicia sesión de nuevo.';
    await logout();
  }
}
