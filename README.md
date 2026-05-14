# Orange

B2B crash-game / RNG casino game provider platform built on Laravel 13.

Provably-fair RNG, real-time WebSocket gameplay, multi-operator REST API with HMAC signing, double-entry wallet ledger, and Filament-powered admin & operator self-service panels.

> **Status:** Pre-MVP scaffold (Week 1).
> See **[ARCHITECTURE.md](ARCHITECTURE.md)** for the full system design and 10-week roadmap.

---

## Stack

| Layer | Choice |
|---|---|
| Backend | Laravel 13 (PHP 8.3+) |
| Runtime | Octane (RoadRunner) in prod, `php artisan serve` in dev |
| Database | PostgreSQL 16 |
| Cache / Queue / Locks / Pub-Sub | Redis 7 |
| Queue dashboard | Laravel Horizon |
| WebSocket | Laravel Reverb |
| Admin panel | Filament v3 |
| Auth (API tokens) | Laravel Sanctum |
| Permissions | spatie/laravel-permission |
| DTOs | spatie/laravel-data |
| Testing | Pest 3 |
| Static analysis | Larastan (PHPStan max) |
| Linter | Laravel Pint |

---

## Prerequisites

- **PHP 8.3+** with extensions: `pdo_pgsql`, `redis` (or `predis`), `bcmath`, `mbstring`, `openssl`, `sodium`, `pcntl`
- **Composer 2.6+**
- **Node 20+ / npm**
- **Docker** + **Docker Compose** (for Postgres + Redis + Mailpit)
- **Git**

> The dev machine this was scaffolded on runs PHP 8.1 — Laravel 13 requires PHP 8.3. Upgrade PHP before running `composer install`.

### Windows PHP upgrade

Download PHP 8.3 NTS x64 from <https://windows.php.net/download/>, extract to `C:\php83`, update PATH so `php -v` shows 8.3, and enable required extensions in `php.ini`:

```ini
extension=pdo_pgsql
extension=pgsql
extension=redis        ; or use predis (pure PHP) via REDIS_CLIENT=predis
extension=bcmath
extension=mbstring
extension=openssl
extension=sodium
extension=fileinfo
extension=curl
```

If you cannot install the `redis` PHP extension on Windows, set `REDIS_CLIENT=predis` in `.env` and add `predis/predis` to composer.

---

## Local Setup

```powershell
# 1. Start infrastructure (Postgres, Redis, Mailpit)
docker compose up -d

# 2. Install PHP & JS dependencies
composer install
npm install

# 3. Environment
Copy-Item .env.example .env
php artisan key:generate

# 4. Database
php artisan migrate

# 5. Run everything in parallel (server, queue, logs, vite)
composer run dev
```

### Reverb (WebSocket) — separate terminal

```powershell
php artisan reverb:start --host=0.0.0.0 --port=8080
```

### Horizon (queue dashboard) — separate terminal

```powershell
php artisan horizon
```

---

## Folder Layout

```
orange/
├── app/
│   ├── Domain/                  # Domain-driven boundaries (App\Domain\*)
│   │   ├── Operator/            # Operator model, API key, callback client
│   │   ├── Game/                # Round, Bet, Cashout, GameEngine
│   │   ├── Wallet/              # Ledger, idempotency, operator HTTP
│   │   ├── ProvablyFair/        # Seed lifecycle, HMAC, verify
│   │   └── Risk/                # Exposure, anomaly, kill-switch
│   ├── Http/
│   │   ├── Controllers/Api/OperatorApi/
│   │   ├── Middleware/          # VerifyOperatorSignature, Idempotency
│   │   └── Requests/OperatorApi/
│   ├── Filament/
│   │   ├── Admin/               # Internal admin panel
│   │   └── Operator/            # Operator self-service panel
│   └── Support/
├── database/{migrations,factories,seeders}/
├── docker/{nginx,php,postgres}/
├── docs/
│   └── api/                     # OpenAPI 3.1 spec
├── routes/
├── tests/
│   ├── Unit/
│   ├── Feature/
│   └── Math/                    # RTP drift, distribution tests
├── ARCHITECTURE.md              # Full system design
├── docker-compose.yml
└── README.md
```

---

## Roadmap

10-week MVP plan — see [ARCHITECTURE.md §16](ARCHITECTURE.md#16-mvp-yol-haritası).

| Week | Deliverable |
|---|---|
| 1 | Repo + Docker infra + Laravel scaffold ← **you are here** |
| 2 | Operator + API key + HMAC + Idempotency middleware |
| 3 | ProvablyFair service + seed mgmt + verify endpoint |
| 4 | GameRound state machine + RunCrashRoundJob (DB-only) |
| 5 | Bet placement + WalletService (mock callback) |
| 6 | Reverb + tick broadcast + Vue scaffold |
| 7 | Cashout (manual + auto) + race condition tests |
| 8 | Webhook dispatcher + real operator HTTP callbacks |
| 9 | Filament resources (operators, rounds, RTP, seeds) |
| 10 | Load test, security review, OpenAPI, closed beta deploy |

---

## Critical reminders

See [ARCHITECTURE.md §17](ARCHITECTURE.md#17-kritik-uyarılar).

- **No live real-money gameplay without license + RNG certification.**
- **Multiplier is server-side only.** Never trust the client.
- **Decimal(18,8) + bcmath** for all money — no floats.
- **Atomic UPDATE WHERE + lockForUpdate + UNIQUE constraint** for all financial operations.
- **Append-only audit_logs** with hash-chained tampering detection.
