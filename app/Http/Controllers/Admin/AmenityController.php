<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Amenity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AmenityController extends Controller
{
    /**
     * Called from the room form's "add feature" modal via a plain JSON
     * request — not a full Inertia visit, so it doesn't discard whatever
     * else the admin has unsaved in the room form.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:amenities,name'],
        ]);

        $amenity = Amenity::create($data);

        return response()->json($amenity, 201);
    }
}
