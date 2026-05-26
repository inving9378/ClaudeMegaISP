import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../../providers/cliente_provider.dart';
import '../../services/api_service.dart';
import '../../theme.dart';
import '../../utils/fechas.dart';
import '../../widgets/clabe_card.dart';
import '../../widgets/widgets.dart';

class PagosScreen extends StatefulWidget {
  const PagosScreen({super.key});
  @override
  State<PagosScreen> createState() => _PagosScreenState();
}

class _PagosScreenState extends State<PagosScreen> {
  @override
  void initState() {
    super.initState();
    Future.microtask(() {
      final c = context.read<ClienteProvider>();
      c.loadFacturas();
      c.loadPagos();
      c.loadClabe();
    });
  }

  @override
  Widget build(BuildContext context) {
    final c = context.watch<ClienteProvider>();
    final pending = c.facturas.where((f) => f.status.toLowerCase() != 'pagada').toList();
    final totalDue = pending.fold<double>(0, (a, b) => a + b.amount);
    final nextDue = pending.isNotEmpty ? pending.first : null;
    String money(double v) => '\$${v.toStringAsFixed(2)}';

    final overdue = nextDue?.date != null && nextDue!.date!.isBefore(DateTime.now());

    return Scaffold(
      appBar: AppBar(title: const Text('Pagos')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // ── Total a pagar ────────────────────────────────────────
          Card(
            color: overdue ? BrandColors.danger.withOpacity(0.08) : null,
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Total a pagar', style: TextStyle(color: BrandColors.textMuted)),
                  const SizedBox(height: 4),
                  Text(
                    money(totalDue),
                    style: TextStyle(
                      fontSize: 36,
                      fontWeight: FontWeight.bold,
                      color: overdue ? BrandColors.danger : BrandColors.primary,
                    ),
                  ),
                  if (nextDue != null) ...[
                    const SizedBox(height: 8),
                    Text('Ref: ${nextDue.number}', style: const TextStyle(color: BrandColors.textMuted)),
                    const SizedBox(height: 2),
                    Text(
                      'Límite: ${nextDue.date != null ? fechaLarga(nextDue.date!) : "—"}',
                      style: TextStyle(
                        color: overdue ? BrandColors.danger : BrandColors.textMuted,
                        fontWeight: overdue ? FontWeight.bold : FontWeight.normal,
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),

          // ── Métodos de pago ──────────────────────────────────────
          const Text('Métodos de pago', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
          const SizedBox(height: 8),

          // SPEI / CLABE virtual — sección expandida con widget reutilizable
          if (c.loadingClabe)
            const Card(
              child: Padding(
                padding: EdgeInsets.all(40),
                child: Center(child: CircularProgressIndicator()),
              ),
            )
          else if (c.clabe != null)
            ClabeCard(
              clabe: c.clabe!,
              onNotifyTransfer: () => _showTransferSheet(context, totalDue),
            )
          else
            Card(
              child: ListTile(
                leading: const Icon(Icons.account_balance, color: BrandColors.secondary),
                title: const Text('Transferencia bancaria (SPEI)'),
                subtitle: const Text('Cobros SPEI no habilitados todavía'),
                trailing: IconButton(
                  icon: const Icon(Icons.refresh),
                  onPressed: () => context.read<ClienteProvider>().loadClabe(),
                ),
              ),
            ),

          const SizedBox(height: 8),
          // Otros métodos — siguen como placeholder hasta que se integren
          _PaymentMethod(icon: Icons.credit_card, label: 'Tarjeta de crédito / débito', color: BrandColors.primary),
          _PaymentMethod(icon: Icons.store, label: 'OXXO', color: BrandColors.warning),

          const SizedBox(height: 24),

          // ── Historial ────────────────────────────────────────────
          const Text('Historial', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
          const SizedBox(height: 8),
          if (c.pagos.isEmpty)
            const CenteredState(message: 'Sin pagos previos.', icon: Icons.history)
          else
            ...c.pagos.map((p) => Card(
                  child: ListTile(
                    leading: const Icon(Icons.check_circle, color: BrandColors.success),
                    title: Text(money(p.amount), style: const TextStyle(fontWeight: FontWeight.bold)),
                    subtitle: Text(p.method),
                    trailing: Text(
                      p.date != null ? fechaLarga(p.date!) : '—',
                      style: const TextStyle(color: BrandColors.textMuted, fontSize: 12),
                    ),
                  ),
                )),
        ],
      ),
    );
  }

  /// Modal bottom sheet con formulario para notificar transferencia hecha.
  void _showTransferSheet(BuildContext context, double suggestedAmount) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (_) => _NotifyTransferSheet(suggestedAmount: suggestedAmount),
    );
  }
}

// =============================================================================
// Bottom sheet de notificación de transferencia
// =============================================================================

class _NotifyTransferSheet extends StatefulWidget {
  final double suggestedAmount;
  const _NotifyTransferSheet({required this.suggestedAmount});

  @override
  State<_NotifyTransferSheet> createState() => _NotifyTransferSheetState();
}

class _NotifyTransferSheetState extends State<_NotifyTransferSheet> {
  final _formKey = GlobalKey<FormState>();
  final _amountCtrl = TextEditingController();
  final _nameCtrl = TextEditingController();
  final _refCtrl = TextEditingController();
  File? _receipt;
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    if (widget.suggestedAmount > 0) {
      _amountCtrl.text = widget.suggestedAmount.toStringAsFixed(2);
    }
  }

  @override
  void dispose() {
    _amountCtrl.dispose();
    _nameCtrl.dispose();
    _refCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final viewInsets = MediaQuery.of(context).viewInsets;
    return Padding(
      padding: EdgeInsets.fromLTRB(20, 20, 20, 20 + viewInsets.bottom),
      child: Form(
        key: _formKey,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Drag handle
            Center(
              child: Container(
                width: 40,
                height: 4,
                margin: const EdgeInsets.only(bottom: 12),
                decoration: BoxDecoration(color: Colors.grey.shade400, borderRadius: BorderRadius.circular(2)),
              ),
            ),
            const Text('Notificar transferencia', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            const SizedBox(height: 4),
            const Text(
              'Sube el comprobante para que activemos tu servicio antes de que llegue la notificación bancaria automática.',
              style: TextStyle(fontSize: 12, color: BrandColors.textMuted),
            ),
            const SizedBox(height: 16),

            TextFormField(
              controller: _nameCtrl,
              decoration: const InputDecoration(
                labelText: 'Tu nombre (como depositaste) *',
                border: OutlineInputBorder(),
              ),
              textCapitalization: TextCapitalization.words,
              validator: (v) => (v == null || v.trim().isEmpty) ? 'Requerido' : null,
            ),
            const SizedBox(height: 12),

            TextFormField(
              controller: _amountCtrl,
              decoration: const InputDecoration(
                labelText: 'Monto transferido *',
                border: OutlineInputBorder(),
                prefixText: '\$ ',
              ),
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              validator: (v) {
                final n = double.tryParse(v ?? '');
                if (n == null || n <= 0) return 'Monto inválido';
                return null;
              },
            ),
            const SizedBox(height: 12),

            TextFormField(
              controller: _refCtrl,
              decoration: const InputDecoration(
                labelText: 'Referencia (opcional)',
                hintText: 'ID de operación de tu banco',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 16),

            // Selector de comprobante
            _ReceiptPicker(
              file: _receipt,
              onPick: (f) => setState(() => _receipt = f),
            ),
            const SizedBox(height: 20),

            // Submit
            SizedBox(
              height: 50,
              child: FilledButton.icon(
                onPressed: _submitting ? null : _submit,
                icon: _submitting
                    ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                    : const Icon(Icons.cloud_upload),
                label: Text(_submitting ? 'Enviando...' : 'Enviar comprobante'),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    if (_receipt == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Adjunta el comprobante de tu transferencia')),
      );
      return;
    }
    setState(() => _submitting = true);
    try {
      final api = context.read<ApiService>();
      final result = await api.notifyTransfer(
        declaredAmount: double.parse(_amountCtrl.text),
        depositorName: _nameCtrl.text.trim(),
        receiptFile: _receipt!,
        reference: _refCtrl.text.trim().isEmpty ? null : _refCtrl.text.trim(),
      );
      if (!mounted) return;
      Navigator.of(context).pop();
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(result.message),
          backgroundColor: BrandColors.success,
          duration: const Duration(seconds: 4),
        ),
      );
    } on UnauthorizedException {
      rethrow;
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error: $e'), backgroundColor: BrandColors.danger),
      );
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }
}

class _ReceiptPicker extends StatelessWidget {
  final File? file;
  final ValueChanged<File> onPick;
  const _ReceiptPicker({required this.file, required this.onPick});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        border: Border.all(color: Colors.grey.shade400, style: BorderStyle.solid),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Column(
        children: [
          if (file != null) ...[
            Padding(
              padding: const EdgeInsets.all(8),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(6),
                child: Image.file(file!, height: 140, fit: BoxFit.cover, width: double.infinity),
              ),
            ),
            const Divider(height: 1),
          ],
          Row(
            children: [
              Expanded(
                child: TextButton.icon(
                  icon: const Icon(Icons.photo_library),
                  label: const Text('Galería'),
                  onPressed: () => _pick(context, ImageSource.gallery),
                ),
              ),
              const VerticalDivider(width: 1),
              Expanded(
                child: TextButton.icon(
                  icon: const Icon(Icons.camera_alt),
                  label: const Text('Cámara'),
                  onPressed: () => _pick(context, ImageSource.camera),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Future<void> _pick(BuildContext context, ImageSource source) async {
    try {
      final picker = ImagePicker();
      final pick = await picker.pickImage(source: source, maxWidth: 2400, imageQuality: 80);
      if (pick == null) return;
      onPick(File(pick.path));
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('No se pudo seleccionar imagen: $e')),
      );
    }
  }
}

class _PaymentMethod extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  const _PaymentMethod({required this.icon, required this.label, required this.color});

  @override
  Widget build(BuildContext context) {
    return Card(
      child: ListTile(
        leading: CircleAvatar(backgroundColor: color.withOpacity(0.1), child: Icon(icon, color: color)),
        title: Text(label),
        subtitle: const Text('Próximamente', style: TextStyle(fontSize: 11)),
        trailing: const Icon(Icons.chevron_right),
        onTap: () => ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Este método de pago aún no está disponible')),
        ),
      ),
    );
  }
}
