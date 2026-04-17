<?php

namespace App\Http\Controllers\Module\Setting\Table;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SettingTable;
use Illuminate\Support\Facades\Auth;

class SettingTableController extends Controller
{
    public function get($table_id)
    {
        $user = Auth::user();
        $setting = SettingTable::where('user_id', $user->id)
            ->where('table_id', $table_id)
            ->first();

        return response()->json($setting ? json_decode($setting->columns) : []);
    }

    public function store(Request $request, $table_id)
    {
        $user = Auth::user();
        $columns = $request->input('columnsData');

        $setting = SettingTable::updateOrCreate(
            ['user_id' => $user->id, 'table_id' => $table_id],
            ['columns' => json_encode($columns)]
        );

        return response()->json(['message' => 'Columnas guardadas correctamente']);
    }
}
