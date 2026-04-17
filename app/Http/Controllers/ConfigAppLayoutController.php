<?php

namespace App\Http\Controllers;

use App\Models\AppLayoutConfiguration;
use Illuminate\Http\Request;

class ConfigAppLayoutController extends Controller
{
    public function saveAppConfigLayout(Request $request)
    {
        $config = AppLayoutConfiguration::where('user_id', auth()->id())->first();
        if ($config) {
            $config->color_mode = $request->color_mode;
            $config->user_id = auth()->id();
            $config->save();
        } else {
            $config = new AppLayoutConfiguration();
            $config->user_id = auth()->id();
            $config->color_mode = $request->color_mode;
            $config->save();
        }
        return $config;
    }

    public function getConfigTabs(Request $request)
    {
        $config = AppLayoutConfiguration::where('user_id', auth()->id())->first();
        return $config->tabs_json[$request->tabPanel] ?? null;
    }

    public function setConfigTabs(Request $request)
    {
        $config = AppLayoutConfiguration::where('user_id', auth()->id())->first();
        $tabs = null;
        if ($config) {
            $tabs = $config->tabs_json;
            $tabs[$request->tabs] = $request->active;
        } else {
            $config = new AppLayoutConfiguration();
            $config->user_id = auth()->user()->id;
            $config->color_mode = 'light';
            $tabs = [$request->tabs => $request->active];
        }
        $config->tabs_json = $tabs;
        $config->save();
        return $config->tabs_json;
    }
}
