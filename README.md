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
  The booking domain design (in progress) leans on Postgres-specific
  features like exclusion constraints to make double-booking a database
  guarantee, not just an application-level check.
- **Queued, idempotent background work** via Horizon — PDF invoice
  generation, transactional email, and (planned) Stripe webhook handling
  are all designed around at-least-once delivery, not happy-path
  assumptions.

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

**Designed, not yet built** (see the full domain plan for detail — kept
outside this repo since it's working notes, not a deliverable)
- Room/rate inventory and an availability engine backed by a Postgres
  exclusion constraint
- A hand-rolled booking state machine (`pending_payment → confirmed →
  checked_in → checked_out`, with `cancelled`/`no_show` branches)
- Stripe PaymentIntent flow — full payment or a 30% deposit with an
  off-session balance charge — with idempotent webhook handling
- Queued PDF invoice generation and email delivery
- Scheduled automation (unpaid-hold expiry, no-show sweeps, balance
  auto-charge)

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

## License

MIT — see [LICENSE](LICENSE).
