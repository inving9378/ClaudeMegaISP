import 'package:flutter/foundation.dart';

import '../models/models.dart';
import '../services/api_service.dart';

class HijoProvider extends ChangeNotifier {
  final ApiService api;
  HijoProvider({required this.api});

  List<Tarea> tareas = const [];
  int minutesUsedToday = 84; // mock; en producción viene del backend del padre
  int minutesLimitToday = 180;
  int points = 240;
  int streakWeeks = 3;

  bool loading = false;
  String? error;

  Future<void> loadTareas() async {
    loading = true;
    notifyListeners();
    try {
      tareas = await api.getTareas();
      error = null;
    } catch (e) {
      error = e.toString();
    } finally {
      loading = false;
      notifyListeners();
    }
  }

  Future<void> completarTarea(int id) async {
    try {
      await api.completarTarea(id);
      tareas = tareas.map((t) {
        if (t.id != id) return t;
        return Tarea(
          id: t.id, title: t.title, description: t.description,
          rewardType: t.rewardType, rewardValue: t.rewardValue,
          rewardDetail: t.rewardDetail, points: t.points,
          status: 'completed',
        );
      }).toList();
      notifyListeners();
    } catch (e) {
      error = e.toString();
      notifyListeners();
    }
  }

  Future<bool> enviarSolicitud(Map<String, dynamic> data) async {
    try {
      await api.enviarSolicitud(data);
      return true;
    } catch (e) {
      error = e.toString();
      notifyListeners();
      return false;
    }
  }
}
