import 'package:flutter/foundation.dart';

import '../models/models.dart';
import '../services/api_service.dart';

class TecnicoProvider extends ChangeNotifier {
  final ApiService api;
  TecnicoProvider({required this.api});

  List<Orden> ordenes = const [];
  bool loading = false;
  String? error;

  /// Estado del workflow en curso, indexado por id de orden.
  final Map<int, Map<String, dynamic>> workflowState = {};

  Future<void> loadOrdenes() async {
    loading = true;
    notifyListeners();
    try {
      ordenes = await api.getOrdenes();
      error = null;
    } catch (e) {
      error = e.toString();
    } finally {
      loading = false;
      notifyListeners();
    }
  }

  Future<void> updateOrden(int id, Map<String, dynamic> data) async {
    await api.updateOrden(id, data);
    // Reflejar localmente para feedback inmediato.
    ordenes = ordenes.map((o) {
      if (o.id != id) return o;
      return Orden(
        id: o.id,
        number: o.number,
        type: o.type,
        clientName: o.clientName,
        clientPhone: o.clientPhone,
        address: o.address,
        status: data['status']?.toString() ?? o.status,
        scheduledAt: o.scheduledAt,
        notes: o.notes,
        planName: o.planName,
      );
    }).toList();
    notifyListeners();
  }

  // ---------------- workflow helpers ----------------

  List<Map<String, dynamic>> stepsFor(String type) {
    switch (type.toLowerCase()) {
      case 'instalación':
      case 'instalacion':
        return [
          {'title': 'Verificar serial ONT', 'photo': false},
          {'title': 'Conectar fibra óptica', 'photo': true},
          {'title': 'Configurar equipo', 'photo': false},
          {'title': 'Probar velocidad', 'photo': false},
          {'title': 'Foto de instalación final', 'photo': true},
        ];
      case 'reparación':
      case 'reparacion':
        return [
          {'title': 'Identificar falla', 'photo': false},
          {'title': 'Probar conectividad', 'photo': false},
          {'title': 'Reparar o reemplazar', 'photo': true},
          {'title': 'Verificar funcionamiento', 'photo': false},
        ];
      case 'mantenimiento':
        return [
          {'title': 'Inspeccionar equipo', 'photo': false},
          {'title': 'Limpiar conectores', 'photo': false},
          {'title': 'Medir señal', 'photo': false},
          {'title': 'Foto de equipo', 'photo': true},
        ];
      default:
        return [
          {'title': 'Diagnóstico', 'photo': false},
          {'title': 'Trabajo realizado', 'photo': true},
        ];
    }
  }

  Map<String, dynamic> stateFor(int ordenId, String type) {
    return workflowState.putIfAbsent(ordenId, () {
      return {
        'steps': stepsFor(type).map((s) => {...s, 'done': false, 'notes': '', 'photo_path': null}).toList(),
        'currentIndex': 0,
        'clientRating': 0,
        'clientComment': '',
        'signaturePath': null,
      };
    });
  }
}
