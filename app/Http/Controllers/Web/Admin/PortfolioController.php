<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Services\BusinessCreator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortfolioController extends Controller
{
    public function __construct(
        private readonly BusinessCreator $businessCreator,
    ) {}

    public function index(Request $request): View
    {
        $businesses = $request->user()
            ->businesses()
            ->orderBy('name')
            ->get();

        return view('admin.portfolio', [
            'user' => $request->user(),
            'businesses' => $businesses,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $this->businessCreator->create($request->user(), $data['name']);

        return redirect()->route('dashboard')->with('status', 'Business created. You are the owner.');
    }
}
