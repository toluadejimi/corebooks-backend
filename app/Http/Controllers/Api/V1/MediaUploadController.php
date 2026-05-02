<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaUploadController extends Controller
{
    /**
     * Store a product or branding image on the public disk and return an absolute URL for the mobile app.
     */
    public function store(Request $request, Business $business): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpeg,png,webp,gif', 'max:5120'],
        ]);

        $file = $request->file('image');
        $path = $file->store("businesses/{$business->id}/images", 'public');

        $relative = Storage::disk('public')->url($path);
        $url = str_starts_with($relative, 'http')
            ? $relative
            : rtrim($request->root() ?: (string) config('app.url'), '/').'/'.ltrim($relative, '/');

        return response()->json([
            'url' => $url,
            'path' => $path,
        ], 201);
    }
}
