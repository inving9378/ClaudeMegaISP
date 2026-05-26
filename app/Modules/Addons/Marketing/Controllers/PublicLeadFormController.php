<?php

namespace App\Modules\Addons\Marketing\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Marketing\Lead;
use App\Models\Marketing\LeadForm;
use App\Models\Marketing\LeadPipeline;
use App\Modules\Addons\Marketing\Jobs\ScoreLeadJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PublicLeadFormController extends Controller
{
    public function show(string $slug)
    {
        $form = LeadForm::where('slug', $slug)->where('active', true)
            ->with(['assignToSource'])
            ->firstOrFail();

        return response()
            ->view('addon-marketing::public-form', compact('form'))
            ->header('X-Frame-Options', 'ALLOWALL')
            ->header('Content-Security-Policy', "frame-ancestors *");
    }

    public function submit(string $slug, Request $request)
    {
        $form = LeadForm::where('slug', $slug)->where('active', true)->firstOrFail();

        // Rate limiting por IP
        $ipKey     = 'lead_form_' . $slug . '_' . sha1($request->ip());
        $perMinute = $form->rate_limit_per_minute;
        $perHour   = $form->rate_limit_per_hour;

        if (RateLimiter::tooManyAttempts($ipKey . '_min', $perMinute)) {
            return response()->json(['success' => false, 'errors' => ['_' => 'Demasiados intentos. Espera un momento.']], 429);
        }
        if (RateLimiter::tooManyAttempts($ipKey . '_hr', $perHour)) {
            return response()->json(['success' => false, 'errors' => ['_' => 'Límite por hora alcanzado.']], 429);
        }

        RateLimiter::hit($ipKey . '_min', 60);
        RateLimiter::hit($ipKey . '_hr', 3600);

        // Validación dinámica basada en fields_schema
        $rules = $this->buildValidationRules($form->fields_schema ?? []);
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Mapear campos al modelo Lead
        $fieldMap = [
            'full_name'   => 'full_name',
            'email'       => 'email',
            'phone'       => 'phone',
            'whatsapp'    => 'whatsapp',
            'address'     => 'address',
            'neighborhood'=> 'neighborhood',
            'city'        => 'city',
            'state'       => 'state',
            'postal_code' => 'postal_code',
            'notes'       => 'notes',
        ];

        $leadData  = [];
        $extraData = [];
        foreach ($request->except(['_token']) as $key => $value) {
            if (isset($fieldMap[$key])) {
                $leadData[$fieldMap[$key]] = $value;
            } else {
                $extraData[$key] = $value;
            }
        }

        $lead = Lead::create(array_merge($leadData, [
            'company_id'       => $form->company_id,
            'source_id'        => $form->assign_to_source_id,
            'lead_form_id'     => $form->id,
            'assigned_user_id' => $form->assign_to_user_id,
            'status'           => 'new',
            'captured_at'      => now(),
            'raw_payload'      => empty($extraData) ? null : $extraData,
        ]));

        // Incrementar contador atómico
        $form->increment('submissions_count');

        // Asignar al pipeline si está configurado
        if ($form->assign_to_pipeline_id) {
            $firstStage = $form->assignToPipeline?->stages()->orderBy('order')->first();
            if ($firstStage) {
                LeadPipeline::create([
                    'lead_id'         => $lead->id,
                    'pipeline_id'     => $form->assign_to_pipeline_id,
                    'stage_id'        => $firstStage->id,
                    'position'        => 0,
                    'entered_stage_at'=> now(),
                ]);
            }
        }

        // Scoring asíncrono
        if ($form->auto_scoring) {
            ScoreLeadJob::dispatch($lead->id)->onQueue('default');
        }

        return response()->json([
            'success'          => true,
            'lead_id'          => $lead->id,
            'thank_you_message'=> $form->thank_you_message ?? '¡Gracias! Nos pondremos en contacto pronto.',
            'redirect_url'     => $form->thank_you_redirect_url,
        ], 201);
    }

    public function embedScript(Request $request)
    {
        $baseUrl = url('/');
        $js = <<<JS
(function() {
    var divs = document.querySelectorAll('[id="megaisp-lead-form"][data-form-slug]');
    divs.forEach(function(div) {
        var slug = div.getAttribute('data-form-slug');
        var iframe = document.createElement('iframe');
        iframe.src = '{$baseUrl}/public/marketing/lead-form/' + slug + '?embed=1';
        iframe.style.cssText = 'width:100%;border:none;min-height:400px;';
        iframe.scrolling = 'no';
        iframe.id = 'megaisp-iframe-' + slug;
        div.parentNode.replaceChild(iframe, div);
    });
    window.addEventListener('message', function(e) {
        if (e.data && e.data.type === 'megaisp-resize') {
            var iframe = document.getElementById('megaisp-iframe-' + e.data.slug);
            if (iframe) iframe.style.height = e.data.height + 'px';
        }
    });
})();
JS;

        return response($js, 200)->header('Content-Type', 'application/javascript');
    }

    private function buildValidationRules(array $schema): array
    {
        $rules = [];
        foreach ($schema as $field) {
            $key = $field['key'] ?? null;
            if (!$key) continue;

            $fieldRules = [];
            if ($field['required'] ?? false) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            if (($field['type'] ?? '') === 'email') {
                $fieldRules[] = 'email';
            }
            if (isset($field['min'])) {
                $fieldRules[] = 'min:' . $field['min'];
            }
            if (isset($field['max'])) {
                $fieldRules[] = 'max:' . $field['max'];
            }

            $rules[$key] = implode('|', $fieldRules);
        }
        return $rules;
    }
}
