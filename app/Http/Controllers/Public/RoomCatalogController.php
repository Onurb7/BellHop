<?php

namespace App\Http\Controllers\Public;

use App\Enums\RoomStatus;
use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Services\RoomAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoomCatalogController extends Controller
{
    public function index(Request $request, RoomAvailabilityService $availability): Response
    {
        $checkIn = $request->filled('check_in') ? Carbon::parse($request->string('check_in')->value()) : null;
        $checkOut = $request->filled('check_out') ? Carbon::parse($request->string('check_out')->value()) : null;
        $guests = $request->filled('guests') ? $request->integer('guests') : null;

        $publishedRooms = Room::where('is_published', true)
            ->where('status', RoomStatus::Active->value)
            ->with(['roomType', 'amenities'])
            ->orderBy('number')
            ->get();

        if ($checkIn && $checkOut && $checkOut->gt($checkIn)) {
            $availableRoomIds = collect($availability->searchAvailableRooms($checkIn, $checkOut, $guests))
                ->pluck('room_id')
                ->all();

            $publishedRooms = $publishedRooms->whereIn('id', $availableRoomIds);
        }

        return Inertia::render('Public/Rooms/Index', [
            'check_in' => $checkIn?->toDateString(),
            'check_out' => $checkOut?->toDateString(),
            'guests' => $guests,
            'rooms' => $publishedRooms->values()->map(fn (Room $room) => $this->roomCard($room)),
        ]);
    }

    public function show(Room $room): Response
    {
        abort_unless($room->is_published && $room->status === RoomStatus::Active, 404);

        $room->load(['roomType', 'amenities']);

        return Inertia::render('Public/Rooms/Show', [
            'room' => [
                'id' => $room->id,
                'title' => $room->title,
                'description' => $room->description,
                'number' => $room->number,
                'floor' => $room->floor,
                'room_type_name' => $room->roomType->name,
                'base_rate_cents' => $room->roomType->base_rate_cents,
                'currency' => $room->roomType->currency,
                'max_occupancy' => $room->roomType->max_occupancy,
                'amenities' => $room->amenities->pluck('name'),
                'images' => $room->getMedia('images')->map(fn ($media) => [
                    'url' => $media->getUrl(),
                    'thumb_url' => $media->getUrl('thumb'),
                ])->values(),
            ],
        ]);
    }

    private function roomCard(Room $room): array
    {
        return [
            'id' => $room->id,
            'title' => $room->title,
            'number' => $room->number,
            'room_type_name' => $room->roomType->name,
            'base_rate_cents' => $room->roomType->base_rate_cents,
            'currency' => $room->roomType->currency,
            'max_occupancy' => $room->roomType->max_occupancy,
            'description' => $room->description,
            'amenities' => $room->amenities->pluck('name'),
            'thumb_url' => $room->getFirstMediaUrl('images', 'thumb') ?: null,
        ];
    }
}
