import 'dart:async';
import 'dart:convert';
import 'package:http/http.dart' as http;

import '../config.dart';
import '../models/models.dart';

/// Excepción que se lanza cuando la API devuelve 401 — el AuthProvider
/// la observa para forzar logout + redirect a /login.
class UnauthorizedException implements Exception {
  final String message;
  UnauthorizedException([this.message = 'Sesión expirada']);
  @override
  String toString() => message;
}

class ApiException implements Exception {
  final int status;
  final String message;
  ApiException(this.status, this.message);
  @override
  String toString() => 'ApiException($status): $message';
}

class ApiService {
  String? _token;
  final http.Client _client = http.Client();

  void setToken(String? token) {
    _token = token;
  }

  // ---------------------------------------------------------------- helpers

  Uri _u(String path) => Uri.parse('${AppConfig.apiBaseUrl}$path');

  Map<String, String> _headers({bool jsonBody = false}) {
    final h = <String, String>{'Accept': 'application/json'};
    if (jsonBody) h['Content-Type'] = 'application/json';
    if (_token != null && _token!.isNotEmpty) h['Authorization'] = 'Bearer $_token';
    return h;
  }

  Future<dynamic> _get(String path) async {
    try {
      final res = await _client.get(_u(path), headers: _headers()).timeout(const Duration(seconds: 15));
      return _decode(res);
    } on TimeoutException {
      throw ApiException(0, 'Tiempo de espera agotado');
    }
  }

  Future<dynamic> _post(String path, [Object? body]) async {
    try {
      final res = await _client
          .post(_u(path), headers: _headers(jsonBody: true), body: body != null ? jsonEncode(body) : null)
          .timeout(const Duration(seconds: 20));
      return _decode(res);
    } on TimeoutException {
      throw ApiException(0, 'Tiempo de espera agotado');
    }
  }

  Future<dynamic> _put(String path, [Object? body]) async {
    final res = await _client
        .put(_u(path), headers: _headers(jsonBody: true), body: body != null ? jsonEncode(body) : null)
        .timeout(const Duration(seconds: 20));
    return _decode(res);
  }

  dynamic _decode(http.Response res) {
    if (res.statusCode == 401) throw UnauthorizedException();
    if (res.statusCode >= 400) {
      throw ApiException(res.statusCode, _extractError(res.body));
    }
    if (res.body.isEmpty) return null;
    return jsonDecode(res.body);
  }

  String _extractError(String body) {
    try {
      final j = jsonDecode(body);
      if (j is Map && j['error'] != null) return j['error'].toString();
      if (j is Map && j['message'] != null) return j['message'].toString();
    } catch (_) {}
    return body.isEmpty ? 'Error de servidor' : body;
  }

  // ------------------------------------------------------------------ auth

  Future<UserSession> login(String email, String password) async {
    final data = await _post('/auth/login', {
      'email': email,
      'password': password,
      'device_name': 'mobile',
    }) as Map<String, dynamic>;
    final token = (data['token'] ?? '').toString();
    if (token.isEmpty) throw ApiException(0, 'El servidor no devolvió token');
    final session = UserSession.fromLoginResponse(data, token);
    _token = token;
    return session;
  }

  // ---------------------------------------------------------------- account

  Future<Map<String, dynamic>> getAccount() async {
    return await _get('/account') as Map<String, dynamic>;
  }

  /// Endpoint específico del servicio ISP (plan, velocidad, estado). NO existe
  /// aún en el backend Laravel — devuelve mock si AppConfig.useMockFallback.
  Future<ServicioInfo> getServicio() async {
    return await _tryEndpoint(
      () async {
        final j = await _get('/servicio') as Map<String, dynamic>;
        return ServicioInfo.fromJson(j);
      },
      fallback: () => MockData.servicio(),
    );
  }

  // -------------------------------------------------------------- facturas

  Future<List<Factura>> getFacturas() async {
    return await _tryEndpoint(
      () async {
        final r = await _get('/facturas') as List;
        return r.map((e) => Factura.fromJson(e as Map<String, dynamic>)).toList();
      },
      fallback: () => MockData.facturas(),
    );
  }

  Future<List<Pago>> getPagos() async {
    return await _tryEndpoint(
      () async {
        final r = await _get('/pagos') as List;
        return r.map((e) => Pago.fromJson(e as Map<String, dynamic>)).toList();
      },
      fallback: () => MockData.pagos(),
    );
  }

  // ---------------------------------------------------------------- tickets

  Future<List<Ticket>> getTickets() async {
    return await _tryEndpoint(
      () async {
        final r = await _get('/tickets') as List;
        return r.map((e) => Ticket.fromJson(e as Map<String, dynamic>)).toList();
      },
      fallback: () => MockData.tickets(),
    );
  }

  Future<Ticket> createTicket(Map<String, dynamic> data) async {
    return await _tryEndpoint(
      () async {
        final j = await _post('/tickets', data) as Map<String, dynamic>;
        return Ticket.fromJson(j);
      },
      fallback: () => Ticket(
        id: 999,
        number: '#NEW',
        subject: data['subject']?.toString() ?? 'Nuevo ticket',
        status: 'Nuevo',
        date: DateTime.now(),
      ),
    );
  }

  // ---------------------------------------------------------- control parental

  Future<List<ChildProfile>> getPerfiles() async {
    return await _tryEndpoint(
      () async {
        final r = await _get('/profiles') as List;
        return r.map((e) => ChildProfile.fromJson(e as Map<String, dynamic>)).toList();
      },
      fallback: () => MockData.perfiles(),
    );
  }

  Future<Map<String, dynamic>> getChildDetail(int id) async {
    return await _tryEndpoint(
      () async => await _get('/profiles/$id') as Map<String, dynamic>,
      fallback: () => MockData.childDetail(id),
    );
  }

  // ---------------------------------------------------------------- tecnico

  Future<List<Orden>> getOrdenes() async {
    return await _tryEndpoint(
      () async {
        final r = await _get('/tecnico/ordenes') as List;
        return r.map((e) => Orden.fromJson(e as Map<String, dynamic>)).toList();
      },
      fallback: () => MockData.ordenes(),
    );
  }

  Future<void> updateOrden(int id, Map<String, dynamic> data) async {
    await _tryEndpoint(
      () async => await _put('/tecnico/ordenes/$id', data),
      fallback: () => null,
    );
  }

  // ------------------------------------------------------------------- hijo

  Future<List<Tarea>> getTareas() async {
    return await _tryEndpoint(
      () async {
        final r = await _get('/hijo/tareas') as List;
        return r.map((e) => Tarea.fromJson(e as Map<String, dynamic>)).toList();
      },
      fallback: () => MockData.tareas(),
    );
  }

  Future<void> completarTarea(int id) async {
    await _tryEndpoint(
      () async => await _post('/tasks/$id/complete'),
      fallback: () => null,
    );
  }

  Future<void> enviarSolicitud(Map<String, dynamic> data) async {
    await _tryEndpoint(
      () async => await _post('/requests', data),
      fallback: () => null,
    );
  }

  // ------------------------------------------------------- mock orchestration

  Future<T> _tryEndpoint<T>(Future<T> Function() real, {required T Function() fallback}) async {
    try {
      return await real();
    } on UnauthorizedException {
      rethrow;
    } catch (e) {
      if (AppConfig.useMockFallback) {
        // Silencioso para mantener la UX, pero útil para debugging:
        // print('ApiService fallback: $e');
        return fallback();
      }
      rethrow;
    }
  }
}

// =============================================================================
// MOCK DATA — usado cuando el endpoint no existe todavía en el backend
// =============================================================================

class MockData {
  static ServicioInfo servicio() => ServicioInfo(
        planName: 'Internet Hogar 200',
        speed: '200 Mbps',
        estado: 'Activo',
        nextPaymentDate: DateTime.now().add(const Duration(days: 8)),
        consumoGb: 187.5,
        consumoLimite: 500,
        contractNumber: 'MGI-2024-08742',
        address: 'Av. Reforma 123, Col. Centro, Acapulco',
      );

  static List<Factura> facturas() {
    final now = DateTime.now();
    return [
      Factura(id: 1, number: 'F-2026-00128', date: now.subtract(const Duration(days: 5)), amount: 450.00, status: 'pendiente'),
      Factura(id: 2, number: 'F-2026-00118', date: now.subtract(const Duration(days: 35)), amount: 450.00, status: 'pagada'),
      Factura(id: 3, number: 'F-2026-00109', date: now.subtract(const Duration(days: 65)), amount: 450.00, status: 'pagada'),
      Factura(id: 4, number: 'F-2026-00100', date: now.subtract(const Duration(days: 95)), amount: 450.00, status: 'pagada'),
    ];
  }

  static List<Pago> pagos() {
    final now = DateTime.now();
    return [
      Pago(id: 1, date: now.subtract(const Duration(days: 35)), amount: 450.00, method: 'Transferencia bancaria'),
      Pago(id: 2, date: now.subtract(const Duration(days: 65)), amount: 450.00, method: 'OXXO'),
      Pago(id: 3, date: now.subtract(const Duration(days: 95)), amount: 450.00, method: 'Tarjeta de crédito'),
    ];
  }

  static List<Ticket> tickets() {
    final now = DateTime.now();
    return [
      Ticket(id: 1, number: 'T-2026-0432', subject: 'Internet lento en las noches', status: 'Trabajo en curso', date: now.subtract(const Duration(days: 2)), category: 'Internet lento'),
      Ticket(id: 2, number: 'T-2026-0418', subject: 'Cambio de plan a 500 Mbps', status: 'Resuelto', date: now.subtract(const Duration(days: 12)), category: 'Cambio de plan'),
      Ticket(id: 3, number: 'T-2026-0405', subject: 'Factura no recibida', status: 'Esperando al agente', date: now.subtract(const Duration(days: 18)), category: 'Facturación'),
    ];
  }

  static List<ChildProfile> perfiles() => [
        ChildProfile(id: 1, name: 'Sofía García', age: 10, schoolLevel: 'primaria', profileType: 'nino', devicesCount: 2),
        ChildProfile(id: 2, name: 'Mateo García', age: 14, schoolLevel: 'secundaria', profileType: 'preadolescente', devicesCount: 1),
        ChildProfile(id: 3, name: 'Lucía García', age: 17, schoolLevel: 'preparatoria', profileType: 'adolescente', devicesCount: 3),
      ];

  static Map<String, dynamic> childDetail(int id) => {
        'id': id,
        'name': 'Sofía García',
        'time_minutes_today': 84,
        'time_limit_minutes': 180,
        'apps': [
          {'name': 'YouTube Kids', 'icon': 'youtube', 'minutes': 35},
          {'name': 'Minecraft', 'icon': 'gamepad', 'minutes': 24},
          {'name': 'WhatsApp', 'icon': 'comment', 'minutes': 15},
          {'name': 'TikTok', 'icon': 'music', 'minutes': 10},
        ],
        'internet_paused': false,
      };

  static List<Orden> ordenes() {
    final now = DateTime.now();
    return [
      Orden(id: 1, number: 'OT-1042', type: 'Instalación', clientName: 'Diego Hernández', clientPhone: '744-321-9876', address: 'Av. Costera 412', status: 'pendiente', scheduledAt: now.add(const Duration(hours: 2)), planName: 'Internet Hogar 200', notes: 'Cliente prefiere instalación matutina.'),
      Orden(id: 2, number: 'OT-1041', type: 'Reparación', clientName: 'Carla García', clientPhone: '744-555-2200', address: 'Calle Niños Héroes 88', status: 'en_proceso', scheduledAt: now.add(const Duration(hours: 4)), planName: 'Internet Hogar 100'),
      Orden(id: 3, number: 'OT-1038', type: 'Mantenimiento', clientName: 'Mario Ramírez', clientPhone: '744-110-4422', address: 'Fracc. Las Palmas, Casa 12', status: 'completada', scheduledAt: now.subtract(const Duration(hours: 3))),
    ];
  }

  static List<Tarea> tareas() => [
        Tarea(id: 1, title: 'Ordenar tu cuarto', description: 'Toma una foto cuando termines', rewardType: 'time_extra', rewardValue: 30, rewardDetail: '+30 min de pantalla', points: 50, status: 'pending'),
        Tarea(id: 2, title: 'Tarea de matemáticas', description: 'Página 45 del libro', rewardType: 'points', rewardValue: 100, rewardDetail: '100 puntos', points: 100, status: 'pending'),
        Tarea(id: 3, title: 'Sacar al perro', description: 'Mañana y tarde', rewardType: 'app_unlock', rewardValue: 1, rewardDetail: 'Desbloqueo TikTok 30 min', points: 30, status: 'completed'),
        Tarea(id: 4, title: 'Practicar piano 20 min', description: '', rewardType: 'badge', rewardValue: 1, rewardDetail: 'Insignia Pianista', points: 20, status: 'approved'),
      ];
}
