# Seed image manifest

Source stock photos for `DemoHotelSeeder`, attached to demo rooms/services
via Spatie Media Library. Recommended source resolution ~1600×1200 (4:3, to
match the app's existing 400×300 `thumb` conversion). Format: `.webp`.

Missing files are skipped silently — the seeder runs fine with none of these
present, and picks up whatever exists the next time it runs
(`php artisan migrate:fresh --seed` or a fresh `db:seed` on an empty DB).

## Rooms (9 files)

```
rooms/classic-room/bedroom.webp
rooms/classic-room/bathroom.webp

rooms/deluxe-room/bedroom.webp
rooms/deluxe-room/bathroom.webp
rooms/deluxe-room/seating-area.webp

rooms/executive-suite/bedroom.webp
rooms/executive-suite/bathroom.webp
rooms/executive-suite/living-room.webp
rooms/executive-suite/balcony-view.webp
```

## Services (9 files)

```
services/parking.webp
services/breakfast.webp
services/late-checkout.webp
services/airport-shuttle.webp
services/spa-treatment.webp
services/minibar-restock.webp
services/in-room-dining.webp
services/pet-fee.webp
services/bike-rental.webp
```
