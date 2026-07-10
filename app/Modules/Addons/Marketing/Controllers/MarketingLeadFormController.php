<?php

namespace App\Modules\Addons\Marketing\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Marketing\LeadForm;
use Illuminate\Http\Request;

class MarketingLeadFormController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:marketing.forms.view')->only(['index', 'show']);
        $this->middleware('permission:marketing.forms.create')->only('store');
        $this->middleware('permission:marketing.forms.update')->only('update');
        $this->middleware('permission:marketing.forms.delete')->only('destroy');
    }

    public function index()
    {
        $forms = LeadForm::with('assignToSource')
            ->where('company_id', 1)
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json($forms);
    }

    public function show(int $id)
    {
        $form = LeadForm::with(['assignToSource', 'assignToUser', 'assignToPipeline'])
            ->where('company_id', 1)
            ->findOrFail($id);
        return response()->json($form);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'                 => 'required|string|max:100',
            'slug'                 => 'required|string|max:60|regex:/^[a-z0-9\-]+$/|unique:marketing_lead_forms,slug,NULL,id,company_id,1',
            'assign_to_source_id'  => 'required|exists:marketing_lead_sources,id',
            'fields_schema'        => 'required|array',
        ]);

        $form = LeadForm::create(array_merge(
            $request->only([
                'slug', 'name', 'description', 'fields_schema', 'styling',
                'thank_you_message', 'thank_you_redirect_url', 'assign_to_source_id',
                'assign_to_user_id', 'assign_to_pipeline_id', 'auto_scoring',
                'rate_limit_per_minute', 'rate_limit_per_hour',
                'recaptcha_enabled', 'recaptcha_site_key', 'active',
            ]),
            ['company_id' => 1]
        ));

        return response()->json($form, 201);
    }

    public function update(Request $request, int $id)
    {
        $form = LeadForm::where('company_id', 1)->findOrFail($id);

        $request->validate([
            'slug' => "nullable|string|max:60|regex:/^[a-z0-9\-]+$/|unique:marketing_lead_forms,slug,{$id},id,company_id,1",
        ]);

        $form->update($request->only([
            'slug', 'name', 'description', 'fields_schema', 'styling',
            'thank_you_message', 'thank_you_redirect_url', 'assign_to_source_id',
            'assign_to_user_id', 'assign_to_pipeline_id', 'auto_scoring',
            'rate_limit_per_minute', 'rate_limit_per_hour',
            'recaptcha_enabled', 'recaptcha_site_key', 'active',
        ]));

        return response()->json($form->fresh());
    }

    public function destroy(int $id)
    {
        $form = LeadForm::where('company_id', 1)->findOrFail($id);
        $form->delete();
        return response()->json(['success' => true]);
    }

    public function getEmbedCode(int $id)
    {
        $form    = LeadForm::where('company_id', 1)->findOrFail($id);
        $baseUrl = url('/');

        return response()->json([
            'iframe' => '<iframe src="' . $baseUrl . '/public/marketing/lead-form/' . $form->slug . '" style="width:100%;min-height:400px;border:none;" scrolling="no"></iframe>',
            'script' => '<div id="megaisp-lead-form" data-form-slug="' . $form->slug . '"></div>' . "\n" . '<script src="' . $baseUrl . '/public/marketing/embed.js" async></script>',
            'url'    => $baseUrl . '/public/marketing/lead-form/' . $form->slug,
        ]);
    }
}
