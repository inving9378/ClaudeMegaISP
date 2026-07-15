<?php

namespace Tests\Feature\Marketing;

use App\Modules\Addons\Marketing\Listeners\GatewayMessageListener;
use App\Modules\Addons\WhatsAppAgent\Events\WhatsAppMediaReceived;
use App\Modules\Addons\WhatsAppAgent\Events\WhatsAppTextReceived;
use Illuminate\Support\Facades\Log;

/**
 * Item roadmap #203, Fase A — el adaptador debe ser 100% inerte en modo
 * legacy (default) y solo loguear en modo shadow, nunca enviar/responder.
 * Payloads simulados (no golpea Evolution ni el webhook real).
 */
class GatewayMessageListenerTest extends MarketingTestCase
{
    private function textEvent(array $overrides = []): WhatsAppTextReceived
    {
        return new WhatsAppTextReceived(
            instanceId: 1,
            instanceSlug: 'meganet-ventas',
            conversationId: 999,
            messageId: 1,
            contactNumber: '5215512345678',
            type: 'text',
            text: 'Hola, quiero información',
            lineFunctions: $overrides['lineFunctions'] ?? ['ventas'],
        );
    }

    private function mediaEvent(array $overrides = []): WhatsAppMediaReceived
    {
        return new WhatsAppMediaReceived(
            instanceId: 1,
            instanceSlug: 'meganet-ventas',
            conversationId: 999,
            messageId: 2,
            contactNumber: '5215512345678',
            type: 'image',
            lineFunctions: $overrides['lineFunctions'] ?? ['ventas'],
            rawMessage: ['key' => ['id' => 'MSG2'], 'message' => []],
        );
    }

    public function test_legacy_mode_is_a_total_noop_for_text(): void
    {
        config(['marketing_gateway.mode' => 'legacy']);
        Log::spy();

        (new GatewayMessageListener())->handleText($this->textEvent());

        Log::shouldNotHaveReceived('channel');
    }

    public function test_legacy_mode_is_a_total_noop_for_media(): void
    {
        config(['marketing_gateway.mode' => 'legacy']);
        Log::spy();

        (new GatewayMessageListener())->handleMedia($this->mediaEvent());

        Log::shouldNotHaveReceived('channel');
    }

    public function test_shadow_mode_observes_text_without_channel_mismatch(): void
    {
        config(['marketing_gateway.mode' => 'shadow']);
        $spy = \Illuminate\Support\Facades\Log::partialMock();
        $spy->shouldReceive('channel')->with('evolution')->andReturnSelf();
        $spy->shouldReceive('info')->once()->withArgs(function ($message) {
            return str_contains($message, 'MarketingGateway[shadow]');
        });

        (new GatewayMessageListener())->handleText($this->textEvent());
    }

    public function test_shadow_mode_observes_media_without_channel_mismatch(): void
    {
        config(['marketing_gateway.mode' => 'shadow']);
        $spy = \Illuminate\Support\Facades\Log::partialMock();
        $spy->shouldReceive('channel')->with('evolution')->andReturnSelf();
        $spy->shouldReceive('info')->once()->withArgs(function ($message) {
            return str_contains($message, 'MarketingGateway[shadow]');
        });

        (new GatewayMessageListener())->handleMedia($this->mediaEvent());
    }

    public function test_ignores_lines_without_ventas_function(): void
    {
        config(['marketing_gateway.mode' => 'shadow']);
        Log::spy();

        (new GatewayMessageListener())->handleText($this->textEvent(['lineFunctions' => ['cobranza']]));

        Log::shouldNotHaveReceived('channel');
    }

    public function test_unified_mode_never_sends_anything_yet(): void
    {
        config(['marketing_gateway.mode' => 'unified']);
        $spy = \Illuminate\Support\Facades\Log::partialMock();
        $spy->shouldReceive('channel')->with('evolution')->andReturnSelf();
        $spy->shouldReceive('warning')->once()->withArgs(function ($message) {
            return str_contains($message, 'MarketingGateway[unified]');
        });

        (new GatewayMessageListener())->handleText($this->textEvent());
    }
}
