<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Admin\Concerns\ResolvesWorkspace;
use App\Models\Business;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryWebController extends Controller
{
    use ResolvesWorkspace;

    public function store(Request $request, Business $business): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        Category::query()->create([
            'business_id' => $business->id,
            'uuid' => (string) Str::uuid(),
            'name' => $data['name'],
            'version' => 1,
        ]);

        return redirect()->back()->with('status', 'Category created.');
    }
}
