import 'package:shared_preferences/shared_preferences.dart';

/// Manejo de estado "ya viste este tour" persistido en SharedPreferences.
/// Cada tour se identifica con un slug (home, control_familiar, update_v0_4_0…).
class TourService {
  static final TourService _instance = TourService._internal();
  factory TourService() => _instance;
  TourService._internal();

  static const String _prefix = 'mf_tour_';

  /// `true` si el tour aún no se ha mostrado para esta versión del app.
  Future<bool> shouldShowTour(String page) async {
    final prefs = await SharedPreferences.getInstance();
    return !(prefs.getBool('$_prefix$page') ?? false);
  }

  /// Marca el tour como visto.
  Future<void> markTourDone(String page) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool('$_prefix$page', true);
  }

  /// Borra TODOS los tours marcados. Lo invoca update_service tras una
  /// actualización para que los tours se muestren de nuevo con las novedades.
  Future<void> resetTours() async {
    final prefs = await SharedPreferences.getInstance();
    final keys = prefs.getKeys().where((k) => k.startsWith(_prefix)).toList();
    for (final k in keys) {
      await prefs.remove(k);
    }
  }

  /// Borra un tour específico (para forzar mostrarlo de nuevo en pruebas).
  Future<void> resetTour(String page) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('$_prefix$page');
  }
}
