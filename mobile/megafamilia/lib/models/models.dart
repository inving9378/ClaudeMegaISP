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

/// CLABE virtual del cliente — endpoint GET /api/megafamilia/payments/clabe.
class ClabeInfo {
  final String clabe;          // 18 dígitos sin espacios
  final String clabeFormat;    // "646 180 123456789012" para display
  final bool isActive;
  final String beneficiario;
  final String banco;
  final String concepto;

  ClabeInfo({
    required this.clabe,
    required this.clabeFormat,
    required this.isActive,
    required this.beneficiario,
    required this.banco,
    required this.concepto,
  });

  factory ClabeInfo.fromJson(Map<String, dynamic> j) => ClabeInfo(
        clabe: _str(j['clabe']),
        clabeFormat: _str(j['clabe_format']).isNotEmpty ? _str(j['clabe_format']) : _str(j['clabe']),
        isActive: _bool(j['is_active']),
        beneficiario: _str(j['beneficiario']).isNotEmpty ? _str(j['beneficiario']) : 'Meganet Telecomunicaciones',
        banco: _str(j['banco']),
        concepto: _str(j['concepto']),
      );
}

/// Respuesta a POST /api/megafamilia/payments/notify-transfer.
class TransferReceipt {
  final int notificationId;
  final String message;
  final double declaredAmount;
  final DateTime? submittedAt;

  TransferReceipt({
    required this.notificationId,
    required this.message,
    required this.declaredAmount,
    this.submittedAt,
  });

  factory TransferReceipt.fromJson(Map<String, dynamic> j) => TransferReceipt(
        notificationId: _int(j['notification_id']),
        message: _str(j['message']),
        declaredAmount: _dbl(j['declared_amount']),
        submittedAt: _date(j['submitted_at']),
      );
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

// =============================================================================
// EMBAJADORES — Fase 4: CRM de prospectos
// =============================================================================

class Followup {
  final int id;
  final String action;
  final String notes;
  final DateTime? nextActionDate;
  final DateTime? createdAt;

  Followup({
    required this.id,
    required this.action,
    required this.notes,
    this.nextActionDate,
    this.createdAt,
  });

  factory Followup.fromJson(Map<String, dynamic> j) => Followup(
        id: _int(j['id']),
        action: _str(j['action']),
        notes: _str(j['notes']),
        nextActionDate: _date(j['next_action_date']),
        createdAt: _date(j['created_at']),
      );

  String get actionLabel {
    const m = {
      'call': 'Llamada',
      'whatsapp': 'WhatsApp',
      'visit': 'Visita',
      'sms': 'SMS',
      'email': 'Correo',
      'note': 'Nota',
    };
    return m[action] ?? action;
  }
}

class Prospect {
  final int id;
  final String name;
  final String phone;
  final String? email;
  final String? address;
  final String source;
  final String status;
  final String? notes;
  final DateTime? convertedAt;
  final DateTime? lastContactAt;
  final DateTime? createdAt;
  final int? followupsCount;
  final List<Followup> followups;

  Prospect({
    required this.id,
    required this.name,
    required this.phone,
    this.email,
    this.address,
    required this.source,
    required this.status,
    this.notes,
    this.convertedAt,
    this.lastContactAt,
    this.createdAt,
    this.followupsCount,
    this.followups = const [],
  });

  factory Prospect.fromJson(Map<String, dynamic> j) => Prospect(
        id: _int(j['id']),
        name: _str(j['name']),
        phone: _str(j['phone']),
        email: j['email']?.toString(),
        address: j['address']?.toString(),
        source: _str(j['source']),
        status: _str(j['status']),
        notes: j['notes']?.toString(),
        convertedAt: _date(j['converted_at']),
        lastContactAt: _date(j['last_contact_at']),
        createdAt: _date(j['created_at']),
        followupsCount: j['followups_count'] is int ? j['followups_count'] as int : null,
        followups: (j['followups'] as List? ?? [])
            .map((e) => Followup.fromJson(e as Map<String, dynamic>))
            .toList(),
      );

  String get statusLabel {
    const m = {
      'new': 'Nuevo',
      'contacted': 'Contactado',
      'interested': 'Interesado',
      'quoted': 'Cotizado',
      'converted': 'Convertido',
      'lost': 'Perdido',
    };
    return m[status] ?? status;
  }

  // Color semáforo para chips de status
  int get statusColorValue {
    const colors = {
      'new': 0xFF2196F3,        // azul
      'contacted': 0xFFFF9800,  // naranja
      'interested': 0xFF8BC34A, // verde claro
      'quoted': 0xFF9C27B0,     // morado
      'converted': 0xFF4CAF50,  // verde
      'lost': 0xFF9E9E9E,       // gris
    };
    return colors[status] ?? 0xFF9E9E9E;
  }
}

// =============================================================================
// EMBAJADORES — Fase 7: Pantallas avanzadas
// =============================================================================

/// Nodo de la red multinivel (lista plana con parent_id para construir árbol).
class RedNodo {
  final int clientId;
  final int parentId;
  final String name;
  final int depth;
  final String status;

  RedNodo({
    required this.clientId,
    required this.parentId,
    required this.name,
    required this.depth,
    required this.status,
  });

  factory RedNodo.fromJson(Map<String, dynamic> j) => RedNodo(
        clientId: _int(j['client_id']),
        parentId: _int(j['parent_id']),
        name: _str(j['name']),
        depth: _int(j['depth']),
        status: _str(j['status']),
      );

  String get statusLabel {
    const m = {
      'pending_threshold': 'Pendiente',
      'active': 'Activo',
      'cancelled': 'Cancelado',
    };
    return m[status] ?? status;
  }

  int get statusColor {
    const colors = {
      'active': 0xFF4CAF50,
      'pending_threshold': 0xFFFF9800,
      'cancelled': 0xFF9E9E9E,
    };
    return colors[status] ?? 0xFF9E9E9E;
  }
}

/// Recompensa de mensualidad gratis (plan single_reward).
class EmbajadorRecompensa {
  final int id;
  final String status;
  final double planValue;
  final DateTime? availableAt;
  final DateTime? appliedAt;
  final DateTime? expiresAt;
  final String referidoName;

  EmbajadorRecompensa({
    required this.id,
    required this.status,
    required this.planValue,
    this.availableAt,
    this.appliedAt,
    this.expiresAt,
    required this.referidoName,
  });

  factory EmbajadorRecompensa.fromJson(Map<String, dynamic> j) => EmbajadorRecompensa(
        id: _int(j['id']),
        status: _str(j['status']),
        planValue: _dbl(j['plan_value']),
        availableAt: _date(j['available_at']),
        appliedAt: _date(j['applied_at']),
        expiresAt: _date(j['expires_at']),
        referidoName: _str(j['referido_name']),
      );

  String get statusLabel {
    const m = {
      'pending': 'Pendiente',
      'available': 'Disponible',
      'applied': 'Aplicada',
      'expired': 'Vencida',
    };
    return m[status] ?? status;
  }

  int get statusColor {
    const colors = {
      'pending': 0xFFFF9800,
      'available': 0xFF4CAF50,
      'applied': 0xFF2196F3,
      'expired': 0xFF9E9E9E,
    };
    return colors[status] ?? 0xFF9E9E9E;
  }

  bool get isAvailable => status == 'available';
}

/// Resumen de comisiones por mes.
class ComisionMes {
  final int periodYear;
  final int periodMonth;
  final double total;
  final int count;
  final Map<String, double> byStatus;

  ComisionMes({
    required this.periodYear,
    required this.periodMonth,
    required this.total,
    required this.count,
    required this.byStatus,
  });

  factory ComisionMes.fromJson(Map<String, dynamic> j) => ComisionMes(
        periodYear: _int(j['period_year']),
        periodMonth: _int(j['period_month']),
        total: _dbl(j['total']),
        count: _int(j['count']),
        byStatus: (j['by_status'] as Map? ?? {})
            .map((k, v) => MapEntry(k.toString(), _dbl(v))),
      );

  String get periodLabel {
    const meses = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun',
                        'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    final m = periodMonth >= 1 && periodMonth <= 12 ? meses[periodMonth] : '$periodMonth';
    return '$m $periodYear';
  }
}

/// Entrada de historial de notificaciones WhatsApp.
class NotifLog {
  final int id;
  final String eventType;
  final String? bodySent;
  final DateTime? sentAt;
  final bool success;
  final String? errorMessage;

  NotifLog({
    required this.id,
    required this.eventType,
    this.bodySent,
    this.sentAt,
    required this.success,
    this.errorMessage,
  });

  factory NotifLog.fromJson(Map<String, dynamic> j) => NotifLog(
        id: _int(j['id']),
        eventType: _str(j['event_type']),
        bodySent: j['body_sent']?.toString(),
        sentAt: _date(j['sent_at']),
        success: _bool(j['success']),
        errorMessage: j['error_message']?.toString(),
      );

  String get eventLabel {
    const m = {
      'prospect_converted': 'Prospecto convertido',
      'threshold_covered': 'Umbral alcanzado',
      'commission_generated': 'Comisión generada',
      'commissions_applied': 'Comisiones acreditadas',
      'reward_expiring_soon': 'Recompensa por vencer',
    };
    return m[eventType] ?? eventType;
  }
}
