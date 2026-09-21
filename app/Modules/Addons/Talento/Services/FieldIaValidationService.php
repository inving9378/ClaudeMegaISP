<?php

namespace App\Modules\Addons\Talento\Services;

use App\Modules\Addons\Marketing\Services\ClaudeApiClient;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderIaValidation;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderMedia;
use App\Modules\Addons\Talento\Support\FieldFlowEntity;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FieldIaValidationService
{
    public function __construct(private ClaudeApiClient $claude) {}

    /**
     * Run IA validation on captured media for a work order.
     * Does NOT block — returns flags the technician must acknowledge.
     * If IA is unavailable, returns empty flags (graceful degradation).
     */
    public function validateOrder(int $workOrderId): TalentoWorkOrderIaValidation
    {
        $resolved = FieldFlowEntity::resolve($workOrderId);
        abort_if(! $resolved, 404, 'Orden de trabajo no encontrada.');
        ['origen' => $origen, 'model' => $order, 'fk' => $fkCol] = $resolved;
        $isTask = $origen === 'task';

        $modemSn  = $order->modem_sn;
        $clientId = $isTask ? FieldFlowEntity::clientIdForTask($order) : $order->client_id;
        $mediaQuery = fn() => TalentoWorkOrderMedia::where($fkCol, $workOrderId);

        $flags = [];

        // 1. Modem SN match
        if ($modemSn) {
            $snMedia = $mediaQuery()
                ->where('type', 'modem_sn')
                ->latest('created_at')
                ->first();

            if ($snMedia) {
                $ocrResult = $this->ocrImage($snMedia, "Extract the serial number or MAC address text exactly as it appears.");
                $extractedSn = trim($ocrResult['text'] ?? '');
                if ($extractedSn && stripos($extractedSn, $modemSn) === false) {
                    $flags[] = [
                        'field'    => 'modem_sn',
                        'issue'    => "SN capturado '{$extractedSn}' no coincide con '{$modemSn}'",
                        'severity' => 'error',
                    ];
                }
            } else {
                $flags[] = [
                    'field'    => 'modem_sn',
                    'issue'    => 'Falta fotografía del SN del módem',
                    'severity' => 'warning',
                ];
            }
        }

        // 2. INE OCR — check name consistency against client
        $ineMedia = $mediaQuery()->whereIn('type', ['ine_front'])->latest('created_at')->first();
        if ($ineMedia) {
            $client = $clientId ? \App\Models\Client::find($clientId) : null;
            $clientUser = $client ? \App\Models\User::where('client_id', $client->id)->first() : null;

            $ocrResult = $this->ocrImage(
                $ineMedia,
                "Extract the full name from this Mexican INE/voter ID. Return only the name text.",
                isSensitive: true
            );
            $ineText = strtolower(trim($ocrResult['text'] ?? ''));

            if ($clientUser && $ineText) {
                $clientName = strtolower(trim("{$clientUser->name} {$clientUser->father_last_name} {$clientUser->mother_last_name}"));
                similar_text($ineText, $clientName, $pct);
                if ($pct < 60) {
                    $flags[] = [
                        'field'    => 'ine_name',
                        'issue'    => "Nombre en INE ({$ineText}) difiere del cliente registrado",
                        'severity' => 'warning',
                    ];
                }
            }
        } else {
            $flags[] = [
                'field'    => 'ine_front',
                'issue'    => 'Falta fotografía del INE',
                'severity' => 'warning',
            ];
        }

        // 3. General media completeness check
        $mediaTypes = $mediaQuery()->pluck('type')->toArray();
        $required = ['presentation', 'completion'];
        foreach ($required as $req) {
            if (!in_array($req, $mediaTypes)) {
                $flags[] = [
                    'field'    => $req,
                    'issue'    => "Falta evidencia fotográfica de tipo '{$req}'",
                    'severity' => 'warning',
                ];
            }
        }

        // Persist result
        TalentoWorkOrderIaValidation::where($fkCol, $workOrderId)
            ->where('validation_type', 'field_flow')
            ->delete();

        return TalentoWorkOrderIaValidation::create([
            $fkCol             => $workOrderId,
            'validation_type' => 'field_flow',
            'flags'           => $flags,
            'raw_response'    => null,
            'overridden'      => false,
            'created_by'      => auth()->id(),
        ]);
    }

    /**
     * Override (acknowledge) an IA validation result with a reason.
     */
    public function override(int $validationId, string $reason): TalentoWorkOrderIaValidation
    {
        $val = TalentoWorkOrderIaValidation::findOrFail($validationId);
        $val->update([
            'overridden'      => true,
            'override_reason' => $reason,
            'overridden_by'   => auth()->id(),
            'overridden_at'   => now(),
        ]);
        return $val->fresh();
    }

    /**
     * Run OCR via Claude vision on a media file.
     * Sensitive files are decrypted before reading and never logged.
     */
    private function ocrImage(TalentoWorkOrderMedia $media, string $instruction, bool $isSensitive = false): array
    {
        try {
            $path = $media->isSensitive()
                ? Crypt::decryptString($media->file_path)
                : $media->file_path;

            if (!Storage::disk('local')->exists($path)) {
                return ['text' => '', 'error' => 'file_not_found'];
            }

            $contents = Storage::disk('local')->get($path);
            $base64   = base64_encode($contents);
            $mimeType = Storage::disk('local')->mimeType($path) ?: 'image/jpeg';

            $response = $this->claude->messages([
                'model'      => config('services.anthropic.model', 'claude-sonnet-4-6'),
                'max_tokens' => 256,
                'messages'   => [[
                    'role'    => 'user',
                    'content' => [
                        [
                            'type'  => 'image',
                            'source' => [
                                'type'       => 'base64',
                                'media_type' => $mimeType,
                                'data'       => $base64,
                            ],
                        ],
                        ['type' => 'text', 'text' => $instruction],
                    ],
                ]],
            ]);

            $text = $response['content'][0]['text'] ?? '';
            return ['text' => $text];
        } catch (\Throwable $e) {
            Log::warning('Talento IA validation OCR failed', [
                'media_id' => $media->id,
                'error'    => $e->getMessage(),
            ]);
            return ['text' => '', 'error' => $e->getMessage()];
        }
    }
}
