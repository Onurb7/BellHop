<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoomRequest;
use App\Models\Amenity;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RoomController extends Controller
{
    public function index(): Response
    {
        $rooms = Room::with('roomType')
            ->withCount('amenities')
            ->orderBy('number')
            ->get()
            ->map(fn (Room $room) => [
                'id' => $room->id,
                'title' => $room->title,
                'number' => $room->number,
                'floor' => $room->floor,
                'status' => $room->status->value,
                'is_published' => $room->is_published,
                'room_type' => $room->roomType->name,
                'amenities_count' => $room->amenities_count,
                'thumb_url' => $room->getFirstMediaUrl('images', 'thumb') ?: null,
            ]);

        return Inertia::render('Admin/Rooms/Index', [
            'rooms' => $rooms,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Rooms/Form', [
            'room' => null,
            'roomTypes' => $this->roomTypeOptions(),
            'amenities' => Amenity::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(RoomRequest $request): RedirectResponse
    {
        $room = Room::create($this->roomAttributes($request));

        $room->amenities()->sync($request->validated('amenities', []));
        $this->attachImages($room, $request);

        return redirect()->route('admin.rooms.edit', $room)->with('success', 'Room created.');
    }

    public function edit(Room $room): Response
    {
        $room->load('amenities');

        return Inertia::render('Admin/Rooms/Form', [
            'room' => $this->presentRoom($room),
            'roomTypes' => $this->roomTypeOptions(),
            'amenities' => Amenity::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(RoomRequest $request, Room $room): RedirectResponse
    {
        $room->update($this->roomAttributes($request));

        $room->amenities()->sync($request->validated('amenities', []));
        $this->attachImages($room, $request);

        foreach ($request->validated('remove_images', []) as $mediaId) {
            $room->media()->where('id', $mediaId)->first()?->delete();
        }

        return redirect()->route('admin.rooms.edit', $room)->with('success', 'Room updated.');
    }

    public function destroy(Room $room): RedirectResponse
    {
        $room->delete();

        return redirect()->route('admin.rooms.index')->with('success', 'Room deleted.');
    }

    public function duplicate(Room $room): RedirectResponse
    {
        $room->loadMissing('amenities');

        $copy = Room::create([
            'room_type_id' => $room->room_type_id,
            'title' => $room->title,
            'description' => $room->description,
            'number' => $this->uniqueRoomNumber($room->number),
            'floor' => $room->floor,
            'status' => $room->status,
            'is_published' => false,
        ]);

        $copy->amenities()->sync($room->amenities->pluck('id'));

        foreach ($room->getMedia('images') as $media) {
            $media->copy($copy, 'images');
        }

        return redirect()->route('admin.rooms.edit', $copy)
            ->with('success', 'Room duplicated — review the details before publishing.');
    }

    private function roomAttributes(RoomRequest $request): array
    {
        return [
            'room_type_id' => $request->validated('room_type_id'),
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'number' => $request->validated('number'),
            'floor' => $request->validated('floor'),
            'status' => $request->validated('status'),
            'is_published' => $request->boolean('is_published'),
        ];
    }

    private function attachImages(Room $room, RoomRequest $request): void
    {
        foreach ($request->file('images', []) as $file) {
            $room->addMedia($file)->toMediaCollection('images');
        }
    }

    private function uniqueRoomNumber(string $number): string
    {
        $candidate = $number.'-copy';

        while (Room::where('number', $candidate)->exists()) {
            $candidate = $number.'-copy-'.Str::lower(Str::random(4));
        }

        return $candidate;
    }

    private function roomTypeOptions()
    {
        return RoomType::orderBy('name')->get(['id', 'name']);
    }

    private function presentRoom(Room $room): array
    {
        return [
            'id' => $room->id,
            'room_type_id' => $room->room_type_id,
            'title' => $room->title,
            'description' => $room->description,
            'number' => $room->number,
            'floor' => $room->floor,
            'status' => $room->status->value,
            'is_published' => $room->is_published,
            'amenity_ids' => $room->amenities->pluck('id'),
            'images' => $room->getMedia('images')->map(fn ($media) => [
                'id' => $media->id,
                'url' => $media->getUrl(),
                'thumb_url' => $media->getUrl('thumb'),
            ]),
        ];
    }
}
