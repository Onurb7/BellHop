<div align="center">

# 🛎️ Bellhop

**A single-tenant boutique hotel booking platform — built to demonstrate senior Laravel architecture, not to be a SaaS product.**

![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)
![Vue](https://img.shields.io/badge/Vue-3-4FC08D?logo=vuedotjs&logoColor=white)
![Inertia](https://img.shields.io/badge/Inertia.js-v2-9553E9?logo=inertia&logoColor=white)
![Tailwind](https://img.shields.io/badge/Tailwind_CSS-4-06B6D4?logo=tailwindcss&logoColor=white)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-4169E1?logo=postgresql&logoColor=white)
![Redis](https://img.shields.io/badge/Redis-7-DC382D?logo=redis&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-blue)

</div>

---

## What this is

Bellhop is a portfolio project: a hotel booking platform for a single
fictional boutique hotel, built to show how I approach a real Laravel
application end to end — domain modeling, authorization, background
processing, and self-managed deployment — rather than to be a generic,
multi-tenant SaaS. That scope decision is deliberate: another portfolio
project of mine, SPUD, already demonstrates multi-tenancy, so this one stays
focused on booking-domain problems and production-grade infrastructure
instead of repeating that pattern.

## Highlights

- **Role-based access control** via [Spatie Permission](https://spatie.be/docs/laravel-permission), with four roles
  (`super-admin`, `admin`, `staff`, `guest`) and a one-click **"log in as…"
  demo switcher** on the login page — so anyone reviewing this repo can
  explore every role's view without needing real credentials.
  `super-admin` is deliberately excluded from that switcher: the demo
  account whitelist is a config array that structurally doesn't contain it,
  not a runtime check that could be bypassed.
- **Docker Compose for local dev *and* production** — the same
  multi-stage `Dockerfile` and service topology (nginx, PHP-FPM, Horizon
  worker, scheduler, Postgres, Redis) runs locally and is what actually
  ships to a self-managed VPS. No dev/prod drift, no PaaS abstracting the
  infrastructure away.
- **Postgres over MySQL on its own merits** — chosen because the
  deployment target is self-managed, not because of a hosting constraint.
  The `bookings` table leans on a Postgres exclusion constraint
  (`EXCLUDE USING gist` over a generated `daterange` column) so a
  double-booking is a database-level guarantee, not just an
  application-level check — verified against real overlapping and
  back-to-back inserts.
- **Full admin content management** for the room/service catalog — room
  types, individual rooms (with image uploads via Spatie Media Library,
  toggleable feature badges, a publish/unpublish flag so unfinished rooms
  stay hidden from guests, and a "duplicate" action for cloning a room's
  feature set), and services priced either per-night or as a flat fee.
- **Staff/admin capacity calendar** — a tape-chart view (rooms × dates,
  split into AM/PM halves) for front-desk and housekeeping to see
  check-ins, check-outs, and occupancy at a glance, filterable by floor
  and viewable by day, week, or month.
- **Reservations management with an itemized charge ledger** — staff can
  verify payment, send reservation/payment reminder emails, cancel with a
  typed ("cancel") confirmation, and change a booking's dates or room. A
  date/room change is only ever allowed if the resulting total stays at
  or above what's already been paid — one rule that structurally rules
  out refunds, since a downgrade just shrinks the balance collected after
  the stay instead of triggering a payout. Every charge and payment is an
  append-only ledger row, not a mutable running total, so the numbers can
  never silently drift out of sync with the itemized history.
- **Walk-in reservation creation for the front desk** — search a date
  range, see genuinely available rooms, select one. That selection *is*
  the lock: it inserts a real booking row that leans on the same Postgres
  exclusion constraint above, so no separate concurrency mechanism was
  needed. Guest details are collected on the next step; an abandoned
  attempt (tab closed mid-flow) is swept lazily on the next search rather
  than needing a background job. The reservations list itself is
  paginated and searchable, and the date picker rejects invalid
  check-in/check-out ranges before a search is even attempted.
- **Staff/admin capacity dashboard** — hand-rolled SVG charts (no
  charting library) showing today's occupancy/check-in/check-out KPIs, an
  occupancy trend line toggleable by day/week/month, and average-occupancy
  breakdowns by weekday and day-of-month, all reading from the same
  booking data as the calendar and reservations views.
- **Guest self-service dashboard** — a signed-in guest sees their own
  active and past reservations (room, dates, status, balance due/paid)
  pulled from their linked booking history; a guest with no bookings yet
  gets an honest empty state instead of hotel-wide stats that aren't
  theirs to see.
- **Per-user preferences and profile management** — every account can set
  its own date format (ISO/US/EU, with a dotted-EU variant), time format,
  and week-start day, and can update its name, email, and password from a
  dedicated profile page. Guest accounts additionally manage phone and
  address there, which stays in sync with their linked `guests` record so
  front-desk staff and the self-service booking flow below see the same
  contact details.
- **Real Stripe payments, refunds, and PDF invoicing** — guests pay their
  own deposit/balance via an embedded Stripe Card Element on their own
  reservation page; a webhook handler backed by an idempotency ledger
  (`stripe_webhook_events`, per the domain plan's original design) is the
  single source of truth for confirming payment, never the client-side
  callback. Staff can issue a real Stripe refund on a cancelled
  reservation, which reverses both the payment *and* an equal-and-opposite
  ledger charge — the same signed-delta pattern already used for
  date/room changes — so the balance settles cleanly back to $0.00
  instead of drifting. A queued job renders a PDF invoice via dompdf,
  emails it, and regenerates it (reusing the same invoice number,
  without re-sending the email) whenever a later refund changes the
  numbers, so a downloaded invoice never goes stale.
- **Public self-service booking, with no account required up front** — an
  anonymous visitor browses a published room catalog (`/rooms`), picks
  dates, locks a room (the same exclusion-constraint-backed hold the
  walk-in flow uses, extracted into a shared `RoomAvailabilityService` so
  both flows stay behavior-identical), enters their details, and pays the
  deposit through the same embedded Stripe Card Element. Only once
  payment succeeds does an account get provisioned: a brand-new email
  gets an account created and the visitor is auto-logged straight into
  their reservation via a Laravel signed URL (never a guessable booking
  ID); an email that already belongs to an account is deliberately
  **never auto-linked** — the booking stays an unlinked guest record and
  the visitor is emailed to log in instead, so typing someone else's
  email can never attach a booking to their account. This also closed a
  real gap in the app: there was previously no password-reset flow at
  all, for any account — the new "set your password" email and a genuine
  "forgot password" recovery path share the same underlying broker.
- **A complete booking lifecycle, driven by real scheduled jobs** — the
  state machine now goes all the way to `checkIn()`/`checkOut()` (staff
  actions) and `markNoShow()` (a nightly sweep), and five real Artisan
  commands run on the `scheduler` service instead of sitting idle:
  abandoned public checkouts get cancelled automatically instead of
  blocking a room forever, past-due confirmed stays with no check-in
  become no-shows, and — for deposit-plan bookings — the remaining room
  balance is due 3 days before check-in. A booking made within 3 days of
  check-in skips the deposit split entirely and requires full payment up
  front, since there'd be no time left for any of this to run. A
  checked-out booking with a balance still owed (e.g. incidentals) gets a
  reminder email every morning until it's settled.
- **Real, opt-in consent for saving a card** — at deposit time, the guest
  sees an unchecked-by-default checkbox before anything is ever saved:
  check it, and the remaining balance is charged off-session automatically
  on the due date, using Stripe's opaque `payment_method`/`customer`
  tokens (the card itself is never stored in this app's database, and
  Stripe stays PCI-compliant on our behalf); leave it unchecked, and the
  booking still gets the deposit-plan discount, but the guest gets an
  automatic email reminder — warning of the 24-hour cancellation window
  below — instead and pays manually from their own dashboard. Either way,
  if the balance is still unpaid 24 hours after collection was attempted
  — a declined auto-charge or an ignored reminder — the booking is
  automatically cancelled and the room released for resale, rather than
  sitting blocked all the way through the stay.
- **Queued background work** via Horizon — PDF invoice generation and
  reminder emails run as queued jobs, not inline in the request, so a
  slow mail send or PDF render never blocks the response.
- **Automated test coverage for the crucial, bug-prone paths** —
  deliberately not exhaustive: 5 Pest feature tests covering
  availability/locking, the charge/payment ledger math, the Stripe
  webhook handler (idempotency, payment confirmation, refund-netting),
  and the guest-account auto-linking security rule, plus one Vitest test
  for the only frontend component with real payment-handling logic.
  Tests run against a real Postgres test database, not SQLite — SQLite
  can't create the `bookings` table's exclusion constraint at all.

## Tech stack

| Layer | Choice |
|---|---|
| Backend | Laravel 12, PHP 8.4 |
| Frontend | Vue 3 + Inertia.js v2 (SPA-like navigation, no separate API layer) |
| Styling | Tailwind CSS v4 |
| Database | PostgreSQL 16 |
| Cache / Queues | Redis 7 + Laravel Horizon |
| Auth / RBAC | Spatie Permission |
| Media | Spatie Media Library |
| PDF generation | barryvdh/laravel-dompdf |
| Payments | Stripe (`stripe-php`) |
| Transactional email | Resend |
| Local/prod parity | Docker Compose (nginx, PHP-FPM, Postgres, Redis, Horizon, scheduler, Mailpit for dev) |

## Current status

This project is under active development. What's actually shipped vs. what's
designed but not yet built:

**Shipped**
- Full Docker Compose stack (dev + prod-shaped), documented and reproducible
  from a clean clone
- Role-based auth: real email/password login plus the one-click demo
  switcher described above
- Design system and the public-facing login/landing pages
- Authenticated app shell (sidebar + topbar) shared across all roles, with
  navigation gated per role
- Admin CRUD for room types, rooms, services, and amenities — image
  uploads, feature badges, publish/unpublish, room duplication
- Booking domain model — `guests`/`bookings` schema with the exclusion
  constraint described above, seeded with realistic demo data (~76 bookings
  across past/current/future stays, some rooms deliberately left vacant)
- Staff/admin capacity calendar (tape chart) described above
- Reservations management and the charge/payment ledger described above
  (verify payment, reminders, typed-confirmation cancellation, date/room
  changes, pagination and search on the list view)
- Walk-in reservation creation for staff/admin, described above
- Staff/admin capacity dashboard (occupancy KPIs and charts) described above
- Guest self-service dashboard (active/past reservations) described above
- Per-user preferences and profile management described above
- Real Stripe payments, refunds, and PDF invoicing described above — a
  guest can pay off an existing reservation themselves and download the
  resulting invoice; staff can refund a cancelled one
- Public self-service booking flow described above — an anonymous
  visitor can browse rooms, book, pay the deposit, and get auto-logged
  into a provisioned account, with no staff involvement; a full
  password-reset/set-password flow shipped alongside it
- Automated test coverage described above (Pest backend, one Vitest
  frontend test)
- The complete booking state machine and scheduled automation described
  above — `checkIn()`/`checkOut()`/`markNoShow()`, expired-hold cleanup,
  no-show sweeps, and off-session balance auto-charging, all running on
  the `scheduler` service

**Designed, not yet built** (see the full domain plan for detail — kept
outside this repo since it's working notes, not a deliverable)
- Amenities/services attached to a booking (`booking_services`) — the
  `services` catalog exists and is admin-manageable, but nothing lets a
  guest or staff member actually add one to a stay yet, so there's
  nothing to bill at checkout beyond the room itself

I'd rather show a smaller surface area that's actually finished and
correct than a large one that only looks done.

## Getting started

The whole stack runs in Docker — there's no "install PHP locally" step.

```bash
git clone git@github.com:Onurb7/BellHop.git
cd BellHop

docker compose up -d --build
docker compose exec app composer install
docker run --rm -v "$(pwd)":/app alpine chown -R "$(id -u):$(id -g)" /app
chmod -R 777 storage bootstrap/cache
cp .env.example .env
docker compose exec app php artisan key:generate
docker compose up -d --force-recreate app worker scheduler nginx
docker compose exec app php artisan migrate --seed
```

Then visit:

- **App:** [localhost:8080](http://localhost:8080) — click "Log in", then
  try the "log in as…" buttons for Admin / Staff / Guest
- **Mailpit** (catches all outbound dev email): [localhost:8025](http://localhost:8025)
- **Vite dev server** (HMR, not a page to visit directly): `localhost:5173`

## Running tests

```bash
docker compose exec postgres createdb -U bellhop bellhop_testing   # one-time
docker compose exec app php artisan test        # backend (Pest)
docker compose exec vite npm run test:js        # frontend (Vitest)
```

## License

MIT — see [LICENSE](LICENSE).
