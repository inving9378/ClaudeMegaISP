<?php

namespace App\Modules\Addons\Marketing\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Marketing\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MarketingBrandKitController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:configure-brand-kit')
             ->except(['serveLogo']);
    }

    public function show(): JsonResponse
    {
        $cid = 1;

        return response()->json([
            'logo' => [
                'path' => Setting::get('brand_logo_path', $cid),
                'url'  => $this->resolveLogoUrl(Setting::get('brand_logo_path', $cid)),
            ],
            'colors' => [
                'primary'   => Setting::get('brand_primary_color',   $cid) ?? '#1976D2',
                'secondary' => Setting::get('brand_secondary_color', $cid) ?? '#FFC107',
                'text'      => Setting::get('brand_text_color',      $cid) ?? '#FFFFFF',
            ],
            'company' => [
                'name'     => Setting::get('brand_company_name', $cid) ?? '',
                'phone'    => Setting::get('brand_phone',        $cid) ?? '',
                'whatsapp' => Setting::get('brand_whatsapp',     $cid) ?? '',
                'website'  => Setting::get('brand_website',      $cid) ?? '',
            ],
            'integrations' => [
                'openai_configured' => !empty(Setting::get('openai_api_key', $cid)),
                'pexels_configured' => !empty(Setting::get('pexels_api_key', $cid)),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $cid  = 1;
        $data = $request->validate([
            'colors.primary'   => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'colors.secondary' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'colors.text'      => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'company.name'     => ['nullable', 'string', 'max:80'],
            'company.phone'    => ['nullable', 'string', 'max:30'],
            'company.whatsapp' => ['nullable', 'string', 'max:30'],
            'company.website'  => ['nullable', 'string', 'max:200'],
        ]);

        $map = [
            'brand_primary_color'   => $data['colors']['primary']   ?? null,
            'brand_secondary_color' => $data['colors']['secondary'] ?? null,
            'brand_text_color'      => $data['colors']['text']      ?? null,
            'brand_company_name'    => $data['company']['name']     ?? null,
            'brand_phone'           => $data['company']['phone']    ?? null,
            'brand_whatsapp'        => $data['company']['whatsapp'] ?? null,
            'brand_website'         => $data['company']['website']  ?? null,
        ];

        foreach ($map as $key => $value) {
            if ($value === null) {
                continue;
            }
            Setting::updateOrCreate(
                ['key' => $key, 'company_id' => $cid],
                ['value' => $value, 'encrypted' => false, 'group' => 'brand']
            );
        }

        return $this->show();
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => ['required', 'file', 'mimes:png,jpg,jpeg,svg,webp', 'max:5120'],
        ]);

        $cid  = 1;
        $file = $request->file('logo');
        $ext  = strtolower($file->getClientOriginalExtension());

        $oldPath = Setting::get('brand_logo_path', $cid);
        if ($oldPath && Storage::disk('local')->exists($oldPath)) {
            Storage::disk('local')->delete($oldPath);
        }

        $relPath = "marketing/brand/{$cid}/logo.{$ext}";
        Storage::disk('local')->putFileAs("marketing/brand/{$cid}", $file, "logo.{$ext}");

        Setting::updateOrCreate(
            ['key' => 'brand_logo_path', 'company_id' => $cid],
            ['value' => $relPath, 'encrypted' => false, 'group' => 'brand']
        );

        return response()->json([
            'success' => true,
            'path'    => $relPath,
            'url'     => $this->resolveLogoUrl($relPath),
        ]);
    }

    public function deleteLogo(): JsonResponse
    {
        $cid     = 1;
        $oldPath = Setting::get('brand_logo_path', $cid);

        if ($oldPath && Storage::disk('local')->exists($oldPath)) {
            Storage::disk('local')->delete($oldPath);
        }

        Setting::updateOrCreate(
            ['key' => 'brand_logo_path', 'company_id' => $cid],
            ['value' => '', 'encrypted' => false, 'group' => 'brand']
        );

        return response()->json(['success' => true]);
    }

    public function updateIntegrations(Request $request): JsonResponse
    {
        $request->validate([
            'openai_api_key' => ['nullable', 'string', 'max:200'],
            'pexels_api_key' => ['nullable', 'string', 'max:200'],
        ]);

        $cid = 1;

        if ($request->filled('openai_api_key')) {
            Setting::updateOrCreate(
                ['key' => 'openai_api_key', 'company_id' => $cid],
                ['value' => Crypt::encryptString($request->input('openai_api_key')), 'encrypted' => true, 'group' => 'tts']
            );
        }

        if ($request->filled('pexels_api_key')) {
            Setting::updateOrCreate(
                ['key' => 'pexels_api_key', 'company_id' => $cid],
                ['value' => Crypt::encryptString($request->input('pexels_api_key')), 'encrypted' => true, 'group' => 'media']
            );
        }

        return response()->json([
            'openai_configured' => !empty(Setting::get('openai_api_key', $cid)),
            'pexels_configured' => !empty(Setting::get('pexels_api_key', $cid)),
        ]);
    }

    public function serveLogo(): mixed
    {
        $cid  = 1;
        $path = Setting::get('brand_logo_path', $cid);

        if (!$path || !Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return response()->file(storage_path('app/' . $path));
    }

    protected function resolveLogoUrl(?string $path): ?string
    {
        if (!$path || !Storage::disk('local')->exists($path)) {
            return null;
        }
        return url('/api/marketing/brand-kit/logo/serve?t=' . filemtime(storage_path('app/' . $path)));
    }
}
