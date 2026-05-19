<?php

namespace App\Modules\Addons\IA\Controllers;

use App\Http\Controllers\Controller;

class IAPromptsController extends Controller
{
    public function index()
    {
        $this->data['url'] = 'meganet.module.ia';
        $this->data['module'] = 'IA';
        $this->data['notifications'] = $this->userNotification();

        return view('addon-ia::prompts', $this->data);
    }
}
