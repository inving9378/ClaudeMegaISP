import 'package:flutter/foundation.dart';

import '../models/models.dart';
import '../services/api_service.dart';

class EmbajadorProvider extends ChangeNotifier {
  final ApiService api;
  EmbajadorProvider({required this.api});

  // ---- prospectos ---------------------------------------------------------
  List<Prospect> prospectos = const [];
  bool loading = false;
  bool loadingMore = false;
  String? error;

  // ---- red multinivel --------------------------------------------------------
  List<RedNodo> redNodos = const [];
  int redTotal = 0;
  bool loadingRed = false;
  String? redError;

  // ---- recompensas -----------------------------------------------------------
  List<EmbajadorRecompensa> recompensas = const [];
  Map<String, int> recompensasSummary = const {};
  bool loadingRecompensas = false;
  String? recompensasError;

  // ---- comisiones historial --------------------------------------------------
  List<ComisionMes> comisionesHistorial = const [];
  double comisionesGrandTotal = 0;
  String comisionesPlanType = '';
  bool loadingComisiones = false;
  String? comisionesError;

  // ---- notificaciones log ----------------------------------------------------
  List<NotifLog> notifLogs = const [];
  bool loadingNotifLogs = false;
  bool loadingMoreNotifLogs = false;
  int notifLogsPage = 1;
  int notifLogsLastPage = 1;
  String? notifLogsError;

  bool get hasMoreNotifLogs => notifLogsPage < notifLogsLastPage;

  int _currentPage = 1;
  int _lastPage = 1;
  int total = 0;
  String? _activeSearch;
  String? _activeStatus;
  String? _activeSource;

  bool get hasMore => _currentPage < _lastPage;

  // ---------------------------------------------------------------- load/refresh

  Future<void> loadProspectos({
    String? search,
    String? status,
    String? source,
    bool reset = true,
  }) async {
    if (reset) {
      _currentPage = 1;
      _activeSearch = search;
      _activeStatus = status;
      _activeSource = source;
    }

    if (reset) {
      loading = true;
    } else {
      loadingMore = true;
    }
    error = null;
    notifyListeners();

    try {
      final result = await api.getProspectos(
        search: _activeSearch,
        status: _activeStatus,
        source: _activeSource,
        page: _currentPage,
      );

      final items = (result['data'] as List? ?? [])
          .map((e) => Prospect.fromJson(e as Map<String, dynamic>))
          .toList();

      _lastPage    = (result['last_page'] as num?)?.toInt() ?? 1;
      total        = (result['total'] as num?)?.toInt() ?? 0;

      if (reset) {
        prospectos = items;
      } else {
        prospectos = [...prospectos, ...items];
      }
    } catch (e) {
      error = e.toString();
    } finally {
      loading = false;
      loadingMore = false;
      notifyListeners();
    }
  }

  Future<void> loadNextPage() async {
    if (!hasMore || loadingMore) return;
    _currentPage++;
    await loadProspectos(reset: false);
  }

  // ---------------------------------------------------------------- CRUD

  Future<Prospect?> createProspecto(Map<String, dynamic> data) async {
    try {
      final p = await api.createProspecto(data);
      prospectos = [p, ...prospectos];
      total++;
      notifyListeners();
      return p;
    } catch (e) {
      error = e.toString();
      notifyListeners();
      return null;
    }
  }

  Future<Prospect?> updateProspecto(int id, Map<String, dynamic> data) async {
    try {
      final updated = await api.updateProspecto(id, data);
      prospectos = prospectos.map((p) => p.id == id ? updated : p).toList();
      notifyListeners();
      return updated;
    } catch (e) {
      error = e.toString();
      notifyListeners();
      return null;
    }
  }

  Future<bool> marcarLost(int id) async {
    try {
      await api.marcarProspectoLost(id);
      prospectos = prospectos
          .map((p) => p.id == id ? _withStatus(p, 'lost') : p)
          .toList();
      notifyListeners();
      return true;
    } catch (e) {
      error = e.toString();
      notifyListeners();
      return false;
    }
  }

  Future<Prospect?> getProspecto(int id) async {
    try {
      return await api.getProspecto(id);
    } catch (e) {
      error = e.toString();
      notifyListeners();
      return null;
    }
  }

  // ---------------------------------------------------------------- followups

  Future<List<Followup>> getFollowups(int prospectId) async {
    try {
      return await api.getFollowups(prospectId);
    } catch (e) {
      error = e.toString();
      notifyListeners();
      return [];
    }
  }

  Future<Followup?> addFollowup(int prospectId, Map<String, dynamic> data) async {
    try {
      return await api.addFollowup(prospectId, data);
    } catch (e) {
      error = e.toString();
      notifyListeners();
      return null;
    }
  }

  // ---------------------------------------------------------------- import

  Future<Map<String, dynamic>?> importarContactos(List<Map<String, dynamic>> contacts) async {
    try {
      final result = await api.importarProspectos(contacts);
      // Recarga la lista para que aparezcan los nuevos
      await loadProspectos();
      return result;
    } catch (e) {
      error = e.toString();
      notifyListeners();
      return null;
    }
  }

  // ---------------------------------------------------------------- red multinivel

  Future<void> loadRed() async {
    loadingRed = true;
    redError = null;
    notifyListeners();
    try {
      final j = await api.getRed();
      redNodos = (j['nodes'] as List? ?? [])
          .map((e) => RedNodo.fromJson(e as Map<String, dynamic>))
          .toList();
      redTotal = (j['total'] as num?)?.toInt() ?? redNodos.length;
    } catch (e) {
      redError = e.toString();
    } finally {
      loadingRed = false;
      notifyListeners();
    }
  }

  // ---------------------------------------------------------------- recompensas

  Future<void> loadRecompensas() async {
    loadingRecompensas = true;
    recompensasError = null;
    notifyListeners();
    try {
      final j = await api.getRecompensas();
      recompensas = (j['data'] as List? ?? [])
          .map((e) => EmbajadorRecompensa.fromJson(e as Map<String, dynamic>))
          .toList();
      recompensasSummary = (j['summary'] as Map? ?? {})
          .map((k, v) => MapEntry(k.toString(), (v as num?)?.toInt() ?? 0));
    } catch (e) {
      recompensasError = e.toString();
    } finally {
      loadingRecompensas = false;
      notifyListeners();
    }
  }

  Future<bool> aplicarRecompensa(int id) async {
    try {
      await api.aplicarRecompensa(id);
      recompensas = recompensas
          .map((r) => r.id == id
              ? EmbajadorRecompensa(
                  id: r.id,
                  status: 'applied',
                  planValue: r.planValue,
                  availableAt: r.availableAt,
                  appliedAt: DateTime.now(),
                  expiresAt: r.expiresAt,
                  referidoName: r.referidoName,
                )
              : r)
          .toList();
      notifyListeners();
      return true;
    } catch (e) {
      recompensasError = e.toString();
      notifyListeners();
      return false;
    }
  }

  // ---------------------------------------------------------------- comisiones historial

  Future<void> loadComisionesHistorial() async {
    loadingComisiones = true;
    comisionesError = null;
    notifyListeners();
    try {
      final j = await api.getComisionesHistorial();
      comisionesHistorial = (j['data'] as List? ?? [])
          .map((e) => ComisionMes.fromJson(e as Map<String, dynamic>))
          .toList();
      comisionesGrandTotal = (j['grand_total'] as num?)?.toDouble() ?? 0;
      comisionesPlanType = (j['plan_type'] as String?) ?? '';
    } catch (e) {
      comisionesError = e.toString();
    } finally {
      loadingComisiones = false;
      notifyListeners();
    }
  }

  // ---------------------------------------------------------------- notifLogs

  Future<void> loadNotifLogs({bool reset = true}) async {
    if (reset) {
      notifLogsPage = 1;
      loadingNotifLogs = true;
    } else {
      loadingMoreNotifLogs = true;
    }
    notifLogsError = null;
    notifyListeners();
    try {
      final j = await api.getNotificationsLog(page: notifLogsPage);
      final items = (j['data'] as List? ?? [])
          .map((e) => NotifLog.fromJson(e as Map<String, dynamic>))
          .toList();
      notifLogsLastPage = (j['last_page'] as num?)?.toInt() ?? 1;
      if (reset) {
        notifLogs = items;
      } else {
        notifLogs = [...notifLogs, ...items];
      }
    } catch (e) {
      notifLogsError = e.toString();
    } finally {
      loadingNotifLogs = false;
      loadingMoreNotifLogs = false;
      notifyListeners();
    }
  }

  Future<void> loadMoreNotifLogs() async {
    if (!hasMoreNotifLogs || loadingMoreNotifLogs) return;
    notifLogsPage++;
    await loadNotifLogs(reset: false);
  }

  // ---------------------------------------------------------------- helpers

  void clearError() {
    error = null;
    notifyListeners();
  }

  Prospect _withStatus(Prospect p, String status) {
    return Prospect(
      id: p.id,
      name: p.name,
      phone: p.phone,
      email: p.email,
      address: p.address,
      source: p.source,
      status: status,
      notes: p.notes,
      convertedAt: p.convertedAt,
      lastContactAt: p.lastContactAt,
      createdAt: p.createdAt,
      followupsCount: p.followupsCount,
      followups: p.followups,
    );
  }
}
