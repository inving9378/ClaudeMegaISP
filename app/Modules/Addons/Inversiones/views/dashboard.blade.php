@extends('core-layout::master')

@section('title')
    Inversiones — Dashboard
@endsection

@section('content')
    <Breadcrumb :list='[{"title":"Inicio"},{"title":"Inversiones","active":"active"}]'></Breadcrumb>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">Inversiones — Fase 1 (paper trading)</h4>
                    <p class="text-muted">
                        Andamiaje del módulo (item de la Hoja de Ruta #596). Ingesta de datos,
                        motor de estrategias, backtesting y motor de riesgo se agregan en
                        sub-items siguientes — esta pantalla se irá reemplazando conforme
                        avancen (portafolios, posiciones, órdenes, backtests, bitácora de Thomas).
                    </p>
                    <table class="table table-sm w-auto">
                        <tbody>
                            <tr>
                                <th>Modo</th>
                                <td><span class="badge bg-info">{{ config('inversiones.modo') }}</span></td>
                            </tr>
                            <tr>
                                <th>Kill switch</th>
                                <td>
                                    @if(config('inversiones.kill_switch'))
                                        <span class="badge bg-danger">activo</span>
                                    @else
                                        <span class="badge bg-success">apagado</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Capital asignado (perímetro)</th>
                                <td>{{ number_format(config('inversiones.capital_asignado'), 2) }} {{ config('inversiones.moneda_base') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
