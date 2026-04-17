@extends('meganet.layout.master')

@section('content')
    <div class="container-fluid">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Consumo de Internet - {{ $mesActual }}
                    /{{ $anioActual }}</h6>

                <form action="{{ route('consumption.index') }}" method="GET" class="form-inline">
                    <select name="month" class="form-control form-control-sm mr-2">
                        @for ($i = 1; $i <= 12; $i++)
                            <option value="{{ $i }}" {{ $mesActual == $i ? 'selected' : '' }}>
                                {{ date("F", mktime(0, 0, 0, $i, 10)) }}
                            </option>
                        @endfor
                    </select>
                    <select name="year" class="form-control form-control-sm mr-2">
                        @for ($i = date('Y'); $i >= 2024; $i--)
                            <option value="{{ $i }}" {{ $anioActual == $i ? 'selected' : '' }}>{{ $i }}</option>
                        @endfor
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
                </form>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="consumptionTable" width="100%"
                           cellspacing="0">
                        <thead class="thead-dark">
                        <tr>
                            <th>Usuario</th>
                            <th>Nombre Real</th>
                            <th>IP Asignada</th> {{-- Nueva Columna --}}
                            <th>Descarga (Down)</th>
                            <th>Subida (Up)</th>
                            <th>Total Consumido</th>
                            <th>Acciones</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($data as $item)
                            <tr>
                                <td><strong>{{ $item['username'] }}</strong></td>
                                <td>{{ $item['real_name'] }}</td>
                                <td>
                                    <span class="badge badge-secondary">{{ $item['ip'] }}</span>
                                </td>
                                <td class="text-success"><i class="fas fa-arrow-down"></i> {{ $item['download'] }}</td>
                                <td class="text-info"><i class="fas fa-arrow-up"></i> {{ $item['upload'] }}</td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar bg-warning text-dark" role="progressbar"
                                             style="width: 100%">
                                            {{ $item['total_gb'] }} GB
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <a href="{{ route('consumption.show', $item['username']) }}"
                                       class="btn btn-info btn-sm">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
