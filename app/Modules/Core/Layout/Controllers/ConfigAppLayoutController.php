<?php

namespace App\Modules\Core\Layout\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Layout\Models\AppLayoutConfiguration;
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

    /**
     * Persiste POR USUARIO el estilo de fila por estado de las tablas.
     * Mismo mecanismo que saveAppConfigLayout (color_mode). Aditivo, no toca color_mode.
     * Valores válidos: 'underline' | 'filled'.
     */
    public function saveRowStatusStyle(Request $request)
    {
        $style = $request->row_status_style === 'filled' ? 'filled' : 'underline';

        $config = AppLayoutConfiguration::where('user_id', auth()->id())->first();
        if ($config) {
            $config->row_status_style = $style;
            $config->save();
        } else {
            $config = new AppLayoutConfiguration();
            $config->user_id = auth()->id();
            $config->color_mode = 'light';
            $config->row_status_style = $style;
            $config->save();
        }
        return response()->json(['row_status_style' => $config->row_status_style]);
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
