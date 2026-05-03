<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminPasskeySetupController extends Controller
{
    public function show(Request $request): View
    {
        return view('admin.passkey-setup', [
            'user' => $request->user(),
        ]);
    }
}
