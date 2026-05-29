<?php

namespace App\Modules\Addons\Marketing\Jobs;

use App\Models\Marketing\Attribution;
use App\Models\Marketing\Lead;
use App\Models\Marketing\LeadPipeline;
use App\Models\Marketing\LeadSource;
use App\Models\Marketing\Setting;
use App\Modules\Addons\Marketing\Services\MetaGraphApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ProcessMetaLeadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public array $backoff = [60, 300, 900, 1800, 3600];

    public function __construct(
        public string $leadgenId,
        public string $formIdMeta,
        public string $pageId,
        public ?string $adId,
        public ?string $adgroupId,
        public int $createdTime,
        public int $companyId = 1
    ) {}

    public function handle(MetaGraphApiService $graph): void
    {
        // Verify form is whitelisted
        $whitelistJson = Setting::get('meta_lead_ads_form_ids', $this->companyId) ?? '[]';
        $whitelist = json_decode($whitelistJson, true) ?? [];
        if (!empty($whitelist) && !in_array($this->formIdMeta, $whitelist)) {
            Log::info('[Meta] Form not whitelisted', ['form_id' => $this->formIdMeta]);
            return;
        }

        $data = $graph->fetchLeadDetails($this->leadgenId, $this->companyId);
        if (empty($data)) {
            $this->fail(new \RuntimeException('Empty response from Graph API'));
            return;
        }

        $fields = collect($data['field_data'] ?? []);
        $mapped = $this->mapFields($fields);

        $source = LeadSource::where('company_id', $this->companyId)
            ->where('code', 'meta_ads')->first();

        $lead = Lead::create(array_merge($mapped, [
            'company_id'  => $this->companyId,
            'source_id'   => $source?->id ?? 1,
            'status'      => 'new',
            'captured_at' => Carbon::createFromTimestamp($this->createdTime),
            'raw_payload' => $data,
            'notes'       => "Capturado desde Meta Ads, formulario {$this->formIdMeta}" . ($this->adId ? ", ad {$this->adId}" : ''),
        ]));

        Attribution::create([
            'lead_id'    => $lead->id,
            'touchpoint' => 'first_touch',
            'occurred_at'=> Carbon::createFromTimestamp($this->createdTime),
            'metadata'   => ['form_id' => $this->formIdMeta, 'ad_id' => $this->adId, 'adgroup_id' => $this->adgroupId],
        ]);

        ScoreLeadJob::dispatch($lead->id)->onQueue('default');
    }

    private function mapFields(\Illuminate\Support\Collection $fields): array
    {
        $map = [
            'full_name'      => ['full_name', 'name'],
            'email'          => ['email'],
            'phone'          => ['phone_number', 'phone', 'telefono'],
            'whatsapp'       => ['whatsapp'],
            'address'        => ['street_address', 'address', 'direccion'],
            'neighborhood'   => ['neighborhood', 'colonia'],
            'city'           => ['city', 'ciudad'],
            'state'          => ['state', 'estado'],
            'postal_code'    => ['post_code', 'zip_code', 'cp'],
        ];

        $result = [];
        foreach ($map as $column => $metaKeys) {
            foreach ($metaKeys as $key) {
                $field = $fields->firstWhere('name', $key);
                if ($field && !empty($field['values'][0])) {
                    $result[$column] = $field['values'][0];
                    break;
                }
            }
        }
        return $result;
    }
}
