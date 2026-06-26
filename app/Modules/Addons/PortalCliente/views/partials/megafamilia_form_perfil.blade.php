{{-- Form de alta de perfil (G1). Espera $cuentas, $tipoLabels, $nivelLabels. --}}
<form method="POST" action="{{ route('portal.megafamilia.perfiles.store') }}">
    @csrf
    @if($cuentas->count() > 1)
        <div class="form-group">
            <label>Cuenta</label>
            <select name="account_id" class="form-control">
                @foreach($cuentas as $c)
                    <option value="{{ $c->id }}">Cuenta #{{ $c->id }}</option>
                @endforeach
            </select>
        </div>
    @else
        <input type="hidden" name="account_id" value="{{ $cuentas->first()->id }}">
    @endif
    <div class="kpi-grid" style="margin-bottom:1rem">
        <div class="form-group" style="margin-bottom:0">
            <label>Nombre <span style="color:var(--danger)">*</span></label>
            <input type="text" name="nombre" class="form-control" maxlength="100" required placeholder="Ej. Juan">
        </div>
        <div class="form-group" style="margin-bottom:0">
            <label>Edad</label>
            <input type="number" name="edad" class="form-control" min="1" max="17" placeholder="1 a 17">
        </div>
        <div class="form-group" style="margin-bottom:0">
            <label>Tipo de perfil</label>
            <select name="profile_type" class="form-control">
                <option value="">— Sin especificar —</option>
                @foreach($tipoLabels as $val => $lbl)<option value="{{ $val }}">{{ $lbl }}</option>@endforeach
            </select>
        </div>
        <div class="form-group" style="margin-bottom:0">
            <label>Nivel escolar</label>
            <select name="school_level" class="form-control">
                <option value="">— Sin especificar —</option>
                @foreach($nivelLabels as $val => $lbl)<option value="{{ $val }}">{{ $lbl }}</option>@endforeach
            </select>
        </div>
    </div>
    <button type="submit" class="btn btn-primary btn-sm">Guardar perfil</button>
</form>
