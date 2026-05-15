import 'package:flutter/foundation.dart';

import '../models/models.dart';
import '../services/api_service.dart';

class ControlParentalProvider extends ChangeNotifier {
  final ApiService api;
  ControlParentalProvider({required this.api});

  List<ChildProfile> profiles = const [];
  Map<int, Map<String, dynamic>> details = {};
  bool loading = false;
  String? error;

  Future<void> loadProfiles() async {
    loading = true;
    notifyListeners();
    try {
      profiles = await api.getPerfiles();
      error = null;
    } catch (e) {
      error = e.toString();
    } finally {
      loading = false;
      notifyListeners();
    }
  }

  Future<Map<String, dynamic>?> loadDetail(int id) async {
    try {
      final d = await api.getChildDetail(id);
      details[id] = d;
      notifyListeners();
      return d;
    } catch (e) {
      error = e.toString();
      notifyListeners();
      return null;
    }
  }
}
