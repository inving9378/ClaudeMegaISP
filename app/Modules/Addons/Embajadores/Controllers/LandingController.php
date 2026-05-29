<?php

namespace App\Modules\Addons\Embajadores\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Referrals\ClientReferralProfile;
use Illuminate\Http\Request;

class LandingController extends Controller
{
    public function index(Request $request)
    {
        $code    = $request->query('ref');
        $profile = null;

        if ($code) {
            $profile = ClientReferralProfile::where('referral_code', $code)
                ->where('is_eligible', true)
                ->first();
        }

        return view('addon-embajadores::landing.index', compact('code', 'profile'));
    }
}
