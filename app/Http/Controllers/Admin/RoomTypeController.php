<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoomTypeRequest;
use App\Models\RoomType;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RoomTypeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/RoomTypes/Index', [
            'roomTypes' => RoomType::withCount('rooms')
                ->orderBy('name')
                ->get()
                ->map(fn (RoomType $roomType) => [
                    'id' => $roomType->id,
                    'name' => $roomType->name,
                    'slug' => $roomType->slug,
                    'base_rate' => $roomType->base_rate_cents / 100,
                    'max_occupancy' => $roomType->max_occupancy,
                    'rooms_count' => $roomType->rooms_count,
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/RoomTypes/Form', [
            'roomType' => null,
        ]);
    }

    public function store(RoomTypeRequest $request): RedirectResponse
    {
        RoomType::create($request->validatedForModel());

        return redirect()->route('admin.room-types.index')->with('success', 'Room type created.');
    }

    public function edit(RoomType $roomType): Response
    {
        return Inertia::render('Admin/RoomTypes/Form', [
            'roomType' => [
                'id' => $roomType->id,
                'name' => $roomType->name,
                'slug' => $roomType->slug,
                'description' => $roomType->description,
                'base_rate' => $roomType->base_rate_cents / 100,
                'max_occupancy' => $roomType->max_occupancy,
            ],
        ]);
    }

    public function update(RoomTypeRequest $request, RoomType $roomType): RedirectResponse
    {
        $roomType->update($request->validatedForModel());

        return redirect()->route('admin.room-types.index')->with('success', 'Room type updated.');
    }

    public function destroy(RoomType $roomType): RedirectResponse
    {
        $roomType->delete();

        return redirect()->route('admin.room-types.index')->with('success', 'Room type deleted.');
    }
}
