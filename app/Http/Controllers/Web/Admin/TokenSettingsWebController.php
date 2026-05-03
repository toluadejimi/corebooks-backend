<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TokenSettingsWebController extends Controller
{
    public function edit(Request $request): View
    {
        return view('admin.platform.token-settings', [
            'user' => $request->user(),
            'proposalAiCost' => (string) PlatformSetting::getValue('token_proposal_ai_cost', '10'),
            'appSearchCost' => (string) PlatformSetting::getValue('token_app_search_cost', '1'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token_proposal_ai_cost' => ['required', 'integer', 'min:0', 'max:100000'],
            'token_app_search_cost' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);

        PlatformSetting::setValue('token_proposal_ai_cost', (string) $data['token_proposal_ai_cost']);
        PlatformSetting::setValue('token_app_search_cost', (string) $data['token_app_search_cost']);

        return redirect()->route('admin.platform.token-settings.edit')->with('status', 'Token prices saved.');
    }
}
