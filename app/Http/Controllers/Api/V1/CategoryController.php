<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(Business $business): JsonResponse
    {
        $list = Category::query()
            ->where('business_id', $business->id)
            ->orderBy('name')
            ->get(['uuid', 'name', 'version'])
            ->map(fn (Category $c) => [
                'uuid' => $c->uuid,
                'name' => $c->name,
                'version' => (int) $c->version,
            ]);

        return response()->json(['data' => $list]);
    }

    public function store(Request $request, Business $business): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $cat = Category::query()->create([
            'business_id' => $business->id,
            'uuid' => (string) Str::uuid(),
            'name' => $data['name'],
            'version' => 1,
        ]);

        return response()->json([
            'data' => [
                'uuid' => $cat->uuid,
                'name' => $cat->name,
                'version' => (int) $cat->version,
            ],
        ], 201);
    }
}
