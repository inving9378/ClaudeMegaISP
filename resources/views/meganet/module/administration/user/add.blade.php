@extends('meganet.layout.master')

@section('title')
    @lang('translation.Dashboard')
@endsection

@section('content')
    <user-add :sucursals="{{ $sucursals }}"></user-add>
    {{-- <div>
        <h5 class="modal-title mb-3">Agregar Usuario</h5>
        <hr>
        <form method="POST" action="{{ route('user.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="mb-3">
                <label for="name" class="form-label">Nombre(s)</label>
                <input 
                    type="text" 
                    class="form-control" 
                    id="name" 
                    name="name"
                    placeholder="Nombre(s)" 
                    value="{{ old('name') }}"
                    required
                >
                @error('name') <div class="text-danger">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3">
                <label for="father_last_name" class="form-label">Apellido Paterno</label>
                <input 
                    type="text" 
                    class="form-control" 
                    id="father_last_name" 
                    name="father_last_name"
                    placeholder="Ingresa el apellido paterno" 
                    value="{{ old('father_last_name') }}"
                    required
                >
                @error('father_last_name') <div class="text-danger">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3">
                <label for="mother_last_name" class="form-label">Apellido Materno</label>
                <input 
                    type="text" 
                    class="form-control" 
                    id="mother_last_name" 
                    name="mother_last_name"
                    placeholder="Ingresa el apellido materno"
                    value="{{ old('mother_last_name') }}" 
                    required
                >
                @error('mother_last_name') <div class="text-danger">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input 
                    type="email" 
                    class="form-control" 
                    id="email" 
                    name="email"
                    placeholder="example@email.com" 
                    value="{{ old('email') }}"
                    required
                >
                @error('email') <div class="text-danger">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3">
                <label for="phone" class="form-label">Teléfono</label>
                <input 
                    type="number" 
                    class="form-control" 
                    id="phone" 
                    name="phone"
                    placeholder="000 - 0000 - 000" 
                    value="{{ old('phone') }}"
                    required
                >
                    @error('phone') <div class="text-danger">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3">
                <label for="location" class="form-label">Locación</label>
                <input 
                    type="text" 
                    class="form-control" 
                    id="location" 
                    name="location"
                    placeholder="Ingresa tu locación o ciudad"
                    value="{{ old('location')}}"
                    required
                >
                @error('location') <div class="text-danger">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3">
                <label for="login_user" class="form-label">Nombre de usuario</label>
                <input 
                    type="text" 
                    class="form-control" 
                    id="login_user" 
                    name="login_user"
                    placeholder="Ingresa el nombre de usuario" 
                    value="{{ old('login_user')}}"
                    required
                >
                @error('login_user') <div class="text-danger">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Contraseña</label>
                <div class="input-group">
                    <input 
                        type="password" 
                        class="form-control" 
                        id="password"
                        name="password" 
                        placeholder="Ingresa tu contraseña" 
                        value="{{ old('password')}}"
                    >
                    <button class="btn btn-outline-secondary" type="button" id="button-addon2" onclick="togglePasswordVisibility()">
                        <i class="fas fa-eye-slash" id="togglePasswordIcon"></i>
                    </button>
                </div>
                @error('password') <div class="text-danger">{{ $message }}</div> @enderror
            </div>
            
            <div class="mb-3">
                <label for="role" class="form-label">Rol:</label>
                <select class="form-select" name="role">
                    <option value="">Selecciona un rol</option>
                    @foreach ($roles as $role)
                       <option value="{{ $role->id }}">{{ $role->name }}</option>
                   @endforeach
                </select>
            </div>
            
            <div class="modal-footer justify-content-center gap-3">
                <a 
                    type="button" 
                    class="btn btn-secondary" 
                    href="{{ url('administracion/user')}}"
                >
                    Cancelar
                </a>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div> --}}
@endsection

{{-- <script>
    function togglePasswordVisibility() {
        var passwordInput = document.getElementById('password');
        var togglePasswordIcon = document.getElementById('togglePasswordIcon');

        if (passwordInput.type === "password") {
            passwordInput.type = "text";
            togglePasswordIcon.classList.remove('fa-eye-slash'); 
            togglePasswordIcon.classList.add('fa-eye'); 
        } else {
            passwordInput.type = "password";
            togglePasswordIcon.classList.remove('fa-eye'); 
            togglePasswordIcon.classList.add('fa-eye-slash'); 
        }
    }
</script> --}}
