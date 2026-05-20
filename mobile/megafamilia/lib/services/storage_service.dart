import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';

import '../models/models.dart';

/// Wrapper sobre SharedPreferences para los pocos valores que persisten
/// entre lanzamientos: el token Sanctum y la sesión actual.
class StorageService {
  static const _kSession = 'session_v1';

  Future<void> saveSession(UserSession s) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_kSession, jsonEncode(s.toJson()));
  }

  Future<UserSession?> loadSession() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_kSession);
    if (raw == null || raw.isEmpty) return null;
    try {
      return UserSession.fromJson(jsonDecode(raw) as Map<String, dynamic>);
    } catch (_) {
      return null;
    }
  }

  Future<void> clearSession() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_kSession);
  }
}
