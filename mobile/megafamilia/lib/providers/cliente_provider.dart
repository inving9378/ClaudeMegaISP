import 'package:flutter/foundation.dart';

import '../models/models.dart';
import '../services/api_service.dart';

class ClienteProvider extends ChangeNotifier {
  final ApiService api;
  ClienteProvider({required this.api});

  ServicioInfo? servicio;
  List<Factura> facturas = const [];
  List<Pago> pagos = const [];
  List<Ticket> tickets = const [];

  bool loadingServicio = false;
  bool loadingFacturas = false;
  bool loadingPagos = false;
  bool loadingTickets = false;
  String? error;

  Future<void> loadServicio() async {
    loadingServicio = true;
    notifyListeners();
    try {
      servicio = await api.getServicio();
      error = null;
    } catch (e) {
      error = e.toString();
    } finally {
      loadingServicio = false;
      notifyListeners();
    }
  }

  Future<void> loadFacturas() async {
    loadingFacturas = true;
    notifyListeners();
    try {
      facturas = await api.getFacturas();
    } catch (e) {
      error = e.toString();
    } finally {
      loadingFacturas = false;
      notifyListeners();
    }
  }

  Future<void> loadPagos() async {
    loadingPagos = true;
    notifyListeners();
    try {
      pagos = await api.getPagos();
    } catch (e) {
      error = e.toString();
    } finally {
      loadingPagos = false;
      notifyListeners();
    }
  }

  Future<void> loadTickets() async {
    loadingTickets = true;
    notifyListeners();
    try {
      tickets = await api.getTickets();
    } catch (e) {
      error = e.toString();
    } finally {
      loadingTickets = false;
      notifyListeners();
    }
  }

  Future<Ticket?> createTicket(Map<String, dynamic> data) async {
    try {
      final t = await api.createTicket(data);
      tickets = [t, ...tickets];
      notifyListeners();
      return t;
    } catch (e) {
      error = e.toString();
      notifyListeners();
      return null;
    }
  }
}
