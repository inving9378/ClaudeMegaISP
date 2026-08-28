<?php

namespace App\Modules\Addons\Marketing\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Marketing\PilotCampaignSend;
use App\Modules\Addons\Marketing\Services\Pilot\PilotCampaignService;
use Illuminate\Http\Response;

/**
 * Endpoints PÚBLICOS (sin sesión) — los abre el cliente de correo del
 * destinatario del piloto, no un usuario logueado. Item roadmap #47.
 */
class PilotCampaignTrackingController extends Controller
{
    // GIF transparente 1x1, el mínimo válido.
    private const PIXEL = "GIF89a\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\xff\xff\xff!\xf9\x04\x01\x00\x00\x00\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00;";

    public function __construct(private PilotCampaignService $service)
    {
    }

    public function open(string $token)
    {
        $send = PilotCampaignSend::where('token', $token)->first();
        if ($send) {
            $this->service->markOpened($send);
        }

        return response(self::PIXEL, 200)->header('Content-Type', 'image/gif');
    }

    public function click(string $token)
    {
        $send = PilotCampaignSend::with('campaign')->where('token', $token)->first();
        if (!$send) {
            return response('Enlace inválido o expirado.', Response::HTTP_NOT_FOUND);
        }

        $this->service->markClicked($send);

        $url = $send->campaign?->variantCtaUrl($send->variant);

        return redirect()->away($url ?: url('/'));
    }
}
