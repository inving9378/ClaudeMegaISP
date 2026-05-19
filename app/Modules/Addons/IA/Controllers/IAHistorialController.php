<?php

namespace App\Modules\Addons\IA\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\IA\Models\IAConversacion;
use App\Modules\Addons\IA\Models\IAProyecto;

class IAHistorialController extends Controller
{
    public function index()
    {
        $this->data['url'] = 'meganet.module.ia';
        $this->data['module'] = 'IA';
        $this->data['notifications'] = $this->userNotification();

        $this->data['proyectos'] = IAProyecto::query()
            ->where(fn ($q) => $q->where('user_id', auth()->id())->orWhere('es_default', true))
            ->orderByDesc('es_default')
            ->orderBy('nombre')
            ->get();

        return view('addon-ia::historial', $this->data);
    }

    public function tabla()
    {
        $conversaciones = IAConversacion::query()
            ->with(['proveedor:id,nombre,driver', 'proyecto:id,nombre,color'])
            ->withCount('mensajes')
            ->where('user_id', auth()->id())
            ->orderByDesc('ultimo_mensaje_at')
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        return response()->json(['success' => true, 'data' => $conversaciones]);
    }
}
