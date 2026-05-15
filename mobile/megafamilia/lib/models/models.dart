/// Modelos de datos simples (DTOs) — sin codegen para mantener el proyecto
/// liviano en v0.1. Todos parsean desde Map<String, dynamic> y son tolerantes
/// a campos faltantes (devuelven defaults razonables).

T? _safe<T>(dynamic v) => v is T ? v : null;
String _str(dynamic v) => v?.toString() ?? '';
int _int(dynamic v) => v is int ? v : int.tryParse('$v') ?? 0;
double _dbl(dynamic v) => v is double ? v : double.tryParse('$v') ?? 0.0;
bool _bool(dynamic v) => v is bool ? v : (v?.toString() == 'true' || v == 1);
DateTime? _date(dynamic v) {
  if (v == null) return null;
  if (v is DateTime) return v;
  return DateTime.tryParse(v.toString());
}

class UserSession {
  final int id;
  final String name;
  final String email;
  final String role;
  final String token;

  UserSession({required this.id, required this.name, required this.email, required this.role, required this.token});

  factory UserSession.fromLoginResponse(Map<String, dynamic> json, String token) {
    final user = (json['user'] as Map?) ?? {};
    final role = (json['role'] ?? user['role'] ?? 'cliente').toString().toLowerCase();
    return UserSession(
      id: _int(user['id']),
      name: _str(user['name']),
      email: _str(user['email']),
      role: role,
      token: token,
    );
  }

  Map<String, dynamic> toJson() => {'id': id, 'name': name, 'email': email, 'role': role, 'token': token};

  factory UserSession.fromJson(Map<String, dynamic> j) => UserSession(
        id: _int(j['id']),
        name: _str(j['name']),
        email: _str(j['email']),
        role: _str(j['role']),
        token: _str(j['token']),
      );
}

class ServicioInfo {
  final String planName;
  final String speed;
  final String estado;
  final DateTime? nextPaymentDate;
  final double? consumoGb;
  final double? consumoLimite;
  final String contractNumber;
  final String address;

  ServicioInfo({
    required this.planName,
    required this.speed,
    required this.estado,
    this.nextPaymentDate,
    this.consumoGb,
    this.consumoLimite,
    this.contractNumber = '',
    this.address = '',
  });

  factory ServicioInfo.fromJson(Map<String, dynamic> j) => ServicioInfo(
        planName: _str(j['plan_name']),
        speed: _str(j['speed']),
        estado: _str(j['estado'].toString().isEmpty ? 'Activo' : j['estado']),
        nextPaymentDate: _date(j['next_payment_date']),
        consumoGb: j['consumo_gb'] != null ? _dbl(j['consumo_gb']) : null,
        consumoLimite: j['consumo_limite'] != null ? _dbl(j['consumo_limite']) : null,
        contractNumber: _str(j['contract_number']),
        address: _str(j['address']),
      );
}

class Factura {
  final int id;
  final String number;
  final DateTime? date;
  final double amount;
  final String status; // 'pagada' | 'pendiente' | 'vencida'

  Factura({required this.id, required this.number, this.date, required this.amount, required this.status});

  factory Factura.fromJson(Map<String, dynamic> j) => Factura(
        id: _int(j['id']),
        number: _str(j['number'].toString().isEmpty ? '#${j['id']}' : j['number']),
        date: _date(j['date']),
        amount: _dbl(j['amount']),
        status: _str(j['status'].toString().isEmpty ? 'pendiente' : j['status']),
      );
}

class Pago {
  final int id;
  final DateTime? date;
  final double amount;
  final String method;

  Pago({required this.id, this.date, required this.amount, required this.method});

  factory Pago.fromJson(Map<String, dynamic> j) =>
      Pago(id: _int(j['id']), date: _date(j['date']), amount: _dbl(j['amount']), method: _str(j['method']));
}

class Ticket {
  final int id;
  final String number;
  final String subject;
  final String status; // Nuevo / Trabajo en curso / Esperando al agente / Resuelto
  final DateTime? date;
  final String? category;

  Ticket({required this.id, required this.number, required this.subject, required this.status, this.date, this.category});

  factory Ticket.fromJson(Map<String, dynamic> j) => Ticket(
        id: _int(j['id']),
        number: _str(j['number'].toString().isEmpty ? '#${j['id']}' : j['number']),
        subject: _str(j['subject'].toString().isEmpty ? j['title'] : j['subject']),
        status: _str(j['status'].toString().isEmpty ? j['estado'] : j['status']),
        date: _date(j['date'] ?? j['created_at']),
        category: _safe<String>(j['category']),
      );
}

class ChildProfile {
  final int id;
  final String name;
  final int? age;
  final String? schoolLevel;
  final String? profileType;
  final int devicesCount;

  ChildProfile({required this.id, required this.name, this.age, this.schoolLevel, this.profileType, this.devicesCount = 0});

  factory ChildProfile.fromJson(Map<String, dynamic> j) => ChildProfile(
        id: _int(j['id']),
        name: _str(j['name']),
        age: j['age'] != null ? _int(j['age']) : null,
        schoolLevel: _safe<String>(j['school_level']),
        profileType: _safe<String>(j['profile_type']),
        devicesCount: _int(j['devices_count']),
      );

  String get initials {
    final parts = name.trim().split(RegExp(r'\s+')).where((s) => s.isNotEmpty).toList();
    if (parts.isEmpty) return '?';
    return parts.take(2).map((p) => p[0].toUpperCase()).join();
  }
}

class Orden {
  final int id;
  final String number;
  final String type; // Instalación / Mantenimiento / Reparación
  final String clientName;
  final String? clientPhone;
  final String address;
  final String status; // pendiente / en_proceso / completada
  final DateTime? scheduledAt;
  final String? notes;
  final String? planName;

  Orden({
    required this.id,
    required this.number,
    required this.type,
    required this.clientName,
    this.clientPhone,
    required this.address,
    required this.status,
    this.scheduledAt,
    this.notes,
    this.planName,
  });

  factory Orden.fromJson(Map<String, dynamic> j) => Orden(
        id: _int(j['id']),
        number: _str(j['number'].toString().isEmpty ? '#${j['id']}' : j['number']),
        type: _str(j['type'].toString().isEmpty ? 'Instalación' : j['type']),
        clientName: _str(j['client_name']),
        clientPhone: _safe<String>(j['client_phone']),
        address: _str(j['address']),
        status: _str(j['status'].toString().isEmpty ? 'pendiente' : j['status']),
        scheduledAt: _date(j['scheduled_at']),
        notes: _safe<String>(j['notes']),
        planName: _safe<String>(j['plan_name']),
      );
}

class Tarea {
  final int id;
  final String title;
  final String? description;
  final String rewardType; // points / time_extra / app_unlock / badge
  final int rewardValue;
  final String? rewardDetail;
  final int points;
  final String status; // pending / completed / approved / rejected

  Tarea({
    required this.id,
    required this.title,
    this.description,
    required this.rewardType,
    required this.rewardValue,
    this.rewardDetail,
    this.points = 0,
    required this.status,
  });

  factory Tarea.fromJson(Map<String, dynamic> j) => Tarea(
        id: _int(j['id']),
        title: _str(j['title']),
        description: _safe<String>(j['description']),
        rewardType: _str(j['reward_type'].toString().isEmpty ? 'points' : j['reward_type']),
        rewardValue: _int(j['reward_value']),
        rewardDetail: _safe<String>(j['reward_detail']),
        points: _int(j['points']),
        status: _str(j['status'].toString().isEmpty ? 'pending' : j['status']),
      );
}
