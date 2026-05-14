# Orange — B2B Crash Game Provider Platform

> Laravel 12 tabanlı, provably-fair RNG destekli, B2B operator entegrasyonlu casino oyun sağlayıcısı.

**Status:** Pre-MVP / Hafta 1 — scaffolding
**Lisans hedefi:** TBD (Curaçao / Anjouan / MGA)
**RNG sertifikasyon hedefi:** iTech Labs / GLI

---

## İçindekiler

1. [Ürün Tanımı](#1-ürün-tanımı)
2. [Hukuki ve Lisans Gereksinimleri](#2-hukuki-ve-lisans-gereksinimleri)
3. [Teknik Mimari](#3-teknik-mimari)
4. [Modüler Sistem Tasarımı](#4-modüler-sistem-tasarımı)
5. [Veritabanı Tasarımı](#5-veritabanı-tasarımı)
6. [Provably Fair Algoritması](#6-provably-fair-algoritması)
7. [Realtime Oyun Akışı](#7-realtime-oyun-akışı)
8. [API Tasarımı](#8-api-tasarımı)
9. [Güvenlik](#9-güvenlik)
10. [Finansal Doğruluk](#10-finansal-doğruluk)
11. [Ölçeklenebilirlik](#11-ölçeklenebilirlik)
12. [Admin Panel](#12-admin-panel)
13. [Frontend](#13-frontend)
14. [Test Stratejisi](#14-test-stratejisi)
15. [Deployment](#15-deployment)
16. [MVP Yol Haritası](#16-mvp-yol-haritası)
17. [Kritik Uyarılar](#17-kritik-uyarılar)

---

## 1. Ürün Tanımı

### Crash Game Mantığı

Tek round döngüsü:

```
[WAITING ~5s] → [BETTING ~10s] → [RUNNING] → [CRASHED] → [SETTLEMENT] → loop
```

- **Round:** Tek crash döngüsü. Round başlamadan `crash_point` belirlenir, hash'i yayınlanır.
- **Multiplier:** `multiplier(t) = e^(growth_rate * t)`, başlangıç `growth_rate = 0.06`.
- **Cashout:** Player istek anındaki **server-side** multiplier'a göre settle olur. Client multiplier'ı asla güvenilmez.
- **Crash point:** Round başlamadan önce `server_seed + client_seed + nonce` ile deterministik hesaplanır.

### RNG ve Provably-Fair

İki katmanlı RNG:

1. **CSPRNG seed üretimi:** PHP `random_bytes(32)` (libsodium tabanlı). Sertifikasyon labı bu kaynağı denetler.
2. **Provably-fair katman:** `server_seed_hash` round öncesi yayınlanır. Round bitince `server_seed` açıklanır → oyuncu HMAC ile bağımsız doğrular.

```
server_seed (32 byte random) → SHA256 → server_seed_hash (publish)
client_seed                                                (player or house)
nonce                                                       (per-round counter)
crash_point = f(HMAC_SHA256(server_seed, client_seed + ":" + nonce))
```

### RTP, House Edge, Risk

- **House edge:** Crash oyunlarında tipik %1-%5. "Instant crash" probability ile uygulanır (`h % 33 == 0 → 1.00x` ≈ %3.03 edge).
- **RTP:** `1 - house_edge`. Aktif olarak izlenir, hedef ±0.2% bandı.
- **Risk kontrolü:**
  - Round başına `max_exposure` (toplam aktif bahis × hipotetik max multiplier)
  - Operator başına `max_bet`, `max_win`
  - Player anomaly detection (IP, cashout pattern, multi-account)

---

## 2. Hukuki ve Lisans Gereksinimleri

### Lisans seçenekleri

| Yargı | Başlangıç maliyeti | Süreç |
|---|---|---|
| Curaçao | ~$15-30k/yıl | 2-4 ay |
| Anjouan | ~$8-15k/yıl | 1-2 ay |
| Tobique | ~$20k/yıl | 2-3 ay |
| Isle of Man | yüksek | 6-9 ay |
| MGA (Malta) | €25k + €2.5k/ay | 6-12 ay |

### Sertifikasyonlar

| Konu | Sağlayıcı | Süre |
|---|---|---|
| **RNG certification** | iTech Labs, GLI, BMM, eCOGRA | 4-8 hafta |
| **Game math report** | aynı laboratuvarlar | 2-4 hafta |
| **Provably-fair audit** | opsiyonel ama operator talebi var | 1-2 hafta |

### Operasyonel zorunluluklar

- **KYC/AML:** Operator tarafında zorunlu; provider ToS'da netleştirilir
- **Sorumlu oyun:** Self-exclusion, deposit/loss/session limit hookları operator API'ye expose edilir
- **Veri saklama:** Min. 5 yıl finansal log (yargıya göre değişir)
- **GDPR:** Player PII operator'da; provider sadece pseudonymous external_player_id tutar

### Mutlaka danışman gereken konular

- Lisans yargı seçimi + şirket yapısı (genelde Malta/Curaçao holding + tech subsidiary)
- Operator sözleşmeleri (rev-share, payment terms, liability cap, audit rights)
- RNG laboratuvarı seçimi + pre-audit hazırlık

> **Kritik:** Lisanssız gerçek-para canlı yayını → cezai sorumluluk. Closed beta yalnızca sanal para modunda.

---

## 3. Teknik Mimari

```
┌─────────────────────────────────────────────────────────────┐
│  Operator Sites (B2B müşteri casino siteleri)               │
└──────────┬──────────────────────────┬───────────────────────┘
           │ REST (HMAC signed)       │ iframe (game client)
           ▼                          ▼
┌──────────────────────┐    ┌─────────────────────────┐
│  API Gateway (Nginx) │    │  Game Frontend (Vue3)   │
│  + rate limit + WAF  │    │  PixiJS Canvas          │
└──────────┬───────────┘    └────────────┬────────────┘
           │                             │ WSS
           ▼                             ▼
┌──────────────────────────────────────────────────────────────┐
│                    Laravel 12 Application                    │
│  ┌────────────┬──────────────┬──────────────┬─────────────┐ │
│  │ Operator   │ Game Engine  │ Wallet       │ Provably    │ │
│  │ API        │ Service      │ Service      │ Fair Svc    │ │
│  └────────────┴──────────────┴──────────────┴─────────────┘ │
│  ┌─────────────────────────────────────────────────────────┐│
│  │      Filament Admin    |    Laravel Reverb (WS)         ││
│  └─────────────────────────────────────────────────────────┘│
└────┬────────────┬──────────────┬──────────────┬─────────────┘
     │            │              │              │
     ▼            ▼              ▼              ▼
┌─────────┐  ┌────────┐    ┌──────────┐   ┌──────────┐
│Postgres │  │ Redis  │    │ Horizon  │   │ Reverb   │
│ primary │  │cluster │    │ workers  │   │ WS       │
│+replica │  │        │    │          │   │          │
└─────────┘  └────────┘    └──────────┘   └──────────┘
     │
     ▼
┌──────────────────────────────────────────┐
│ Monitoring: Prometheus + Grafana + Loki  │
│ APM: Sentry / OpenTelemetry              │
└──────────────────────────────────────────┘
```

### Servis Sınırları (monorepo, ileride microservice)

| Servis | Sorumluluk |
|---|---|
| `OperatorApi` | HMAC auth, idempotency, REST endpoint'leri |
| `GameEngine` | Round lifecycle, tick, crash determination |
| `Wallet` | Provider ledger + operator callback orchestration |
| `ProvablyFair` | Seed lifecycle, HMAC, verification |
| `RiskMonitor` | Exposure, anomaly, kill-switch |
| `WebhookDispatcher` | Operator'a asenkron event delivery |

### Stack

- **PHP 8.3+ / Laravel 12**
- **Octane (RoadRunner)** production runtime
- **PostgreSQL 16+** (decimal precision, partitioning)
- **Redis 7+** (cache + queue + pub/sub + locks)
- **Laravel Reverb** (WebSocket, Pusher protokolü)
- **Laravel Horizon** (queue dashboard)
- **Filament v3** (admin panel)
- **Vue 3 + Vite + PixiJS** (game client)

---

## 4. Modüler Sistem Tasarımı

| Modül | Sorumluluk |
|---|---|
| **User/Session** | Operator launch_token → server-side session, kısa ömürlü JWT |
| **Operator Management** | CRUD, API key rotation, IP whitelist, rev-share, callback config |
| **Game Round** | State machine: `waiting → betting → running → crashed → settled` |
| **Bet Placement** | Idempotent, atomic, debit operator wallet |
| **Cashout** | Server-side multiplier, race-safe (DB lock + atomic UPDATE) |
| **Wallet Callbacks** | Provider → operator HTTP (debit/credit/rollback), retry + DLQ |
| **Provably Fair Verification** | Public endpoint: seed + client_seed + nonce → multiplier verify |
| **RNG Seed Mgmt** | Server seed lifecycle, rotation policy, reveal on demand |
| **RTP Reporting** | Periyodik aggregate, drift alert |
| **Admin Dashboard** | Filament panels (internal + operator self-service) |
| **Financial Reporting** | Operator bazlı mutabakat, COB raporları |
| **Audit Logs** | Append-only, hash-chained |
| **Webhook Dispatcher** | `bet.placed`, `cashout.done`, `round.crashed`, signed delivery + retry |
| **Operator API Docs** | OpenAPI 3.1 auto-generated (Scramble) |

---

## 5. Veritabanı Tasarımı

> Tüm tablolar `timestamptz` kullanır. Para alanları `decimal(18,8)`. UUID kullanılan yerler `uuid` tipi (Postgres native).

### `operators`
```
id uuid pk, name, slug unique, status enum, callback_base_url,
secret_key (encrypted), ip_whitelist jsonb, config jsonb,
created_at, updated_at
```
Index: `slug`, `status`

### `operator_api_keys`
```
id, operator_id fk, key_id unique, secret_hash, scopes jsonb,
last_used_at, expires_at, revoked_at
```
Index: `key_id`, `(operator_id, revoked_at)`

### `players`
```
id uuid, operator_id fk, external_player_id,
currency, country, status, risk_score,
created_at
unique (operator_id, external_player_id)
```
Index: composite unique, `created_at`

### `games`
```
id, code unique, name, version, rtp_target, house_edge,
config jsonb, status
```

### `game_rounds`
```
id bigint, game_id fk, round_uuid unique,
state enum, server_seed_id fk,
client_seed varchar, nonce bigint,
crash_point decimal(10,2),
started_at, crashed_at, settled_at,
total_bet_amount, total_payout_amount,
created_at
```
Index: `round_uuid`, `state`, `(game_id, created_at)`. **Partition** by `created_at` monthly.

### `bets`
```
id bigint, round_id fk, player_id fk, operator_id fk,
amount decimal(18,8), currency,
status enum (placed, won, lost, refunded, rolled_back),
auto_cashout_at decimal(10,2) nullable,
placed_at, idempotency_key,
external_tx_id
unique (operator_id, idempotency_key)
```
Index: above unique, `round_id`, `player_id`, `placed_at`

### `cashouts`
```
id, bet_id unique fk, multiplier decimal(10,2),
payout_amount decimal(18,8), cashed_out_at, is_auto bool
```

### `wallet_transactions`
```
id bigint, player_id fk, operator_id fk,
bet_id nullable, cashout_id nullable,
type enum (debit, credit, rollback),
amount decimal(18,8), currency,
operator_callback_status enum, operator_response jsonb,
idempotency_key, created_at
unique (operator_id, idempotency_key)
```
Index: above, `bet_id`, `created_at`. **Partition** monthly.

### `rng_seeds`
```
id, server_seed (encrypted), server_seed_hash sha256,
revealed_at nullable, rotated_at, used_round_count
```

### `webhooks`
```
id, operator_id fk, event, payload jsonb, status,
attempts, next_retry_at, last_response jsonb, created_at
```
Index: `(status, next_retry_at)`, `operator_id`

### `audit_logs`
```
id, actor_type enum, actor_id, action,
target_type, target_id,
before jsonb, after jsonb,
ip, user_agent, created_at,
prev_hash, hash       -- tampering detection
```
**Append-only.** Partition monthly. Index `created_at`.

### `rtp_reports`
```
id, game_id, operator_id nullable, period_start, period_end,
total_bet, total_payout, rtp_actual, edge_actual, round_count
```

### `risk_events`
```
id, severity enum, type, player_id nullable, operator_id nullable,
payload jsonb, resolved_at nullable
```

---

## 6. Provably Fair Algoritması

Bustabit-style, %3 house edge:

```
message  = client_seed + ":" + nonce
hmac     = HMAC_SHA256(server_seed, message)        // 64 hex char

# %3 instant crash
h_short  = int(hmac[0:8], 16)
if h_short % 33 == 0: return 1.00

# Crash point
h        = int(hmac[0:13], 16)                       // 52-bit safe int
e        = 2^52
crash    = floor((100*e - h) / (e - h)) / 100
return max(1.00, round(crash, 2))
```

Service: `app/Domain/ProvablyFair/Services/ProvablyFairService.php`

Public verify endpoint: `GET /v1/rounds/{round_uuid}/fairness`

---

## 7. Realtime Oyun Akışı

### Event flow

```
[server] round.waiting         → 5s countdown
[server] round.betting_open    → 10s bet window
[server] round.started         → tick loop start
[server] multiplier.tick       → 100ms interval (10 fps)
[client] player.cashout        → server settles at server-time multiplier
[server] cashout.confirmed     → bet_id, multiplier, payout
[server] round.crashed         → crash point reveal
[server] round.result          → seed reveal + full round stats
```

### Tick stratejisi

- Multiplier round süresince **bellekte** (Redis), DB'ye tick yazılmaz
- Round bitiminde tek transaction'da persist
- Broadcast: Reverb + Redis pub/sub
- Production'da long-running worker (Octane veya dedicated PHP CLI)

### WebSocket karşılaştırma

| Seçenek | Karar |
|---|---|
| **Laravel Reverb** | ✅ MVP + closed beta |
| Soketi | Stabil ama maintenance yavaş |
| Ratchet | Pure PHP, scale zayıf |
| Node.js gateway | 50k+ concurrent gerekirse migrate edilecek (event sözleşmesi sabit) |

---

## 8. API Tasarımı

### Auth (her endpoint)

```
X-Operator-Id: <uuid>
X-Timestamp:   <unix-seconds>
X-Signature:   HMAC-SHA256(secret, METHOD + PATH + BODY + TIMESTAMP)
Idempotency-Key: <uuid>   # mutasyon endpoint'lerinde zorunlu
```

`X-Timestamp` window: ±30s. Replay koruması: timestamp + Redis nonce cache.

### Endpoint'ler

#### `POST /v1/sessions` — Player session yarat
```json
{
  "player": { "external_id": "...", "currency": "EUR", "country": "DE" },
  "game_code": "crash",
  "return_url": "..."
}
// → 201 { session_token, launch_url, expires_at }
```

#### `POST /v1/bets` — Bet yerleştir
```json
{ "session_token": "...", "round_uuid": "...", "amount": "1.50",
  "currency": "EUR", "auto_cashout_at": 2.00 }
// → 201 { bet_id, status, round_uuid, wallet_tx_id }
// Errors: 409 betting_closed, 402 insufficient_funds, 422 limit_exceeded
```

#### `POST /v1/bets/{bet_id}/cashout` — Cashout
```json
{ "session_token": "..." }   // multiplier YOK; server belirler
// → 200 { bet_id, multiplier, payout_amount, cashed_out_at, wallet_tx_id }
// Errors: 409 round_crashed, 409 already_cashed_out, 410 round_not_running
```

#### `POST {operator.callback_url}/wallet/debit` — Provider → Operator
```json
{ "player_external_id": "...", "amount": "1.50", "currency": "EUR",
  "reference": { "type": "bet", "id": "...", "round_uuid": "..." },
  "tx_id": "..." }
// ← { balance_after, operator_tx_id }
```

Aynı sözleşme: `/wallet/credit`, `/wallet/rollback`.

#### `GET /v1/rounds/{round_uuid}/fairness` — Public verify
```json
// → { server_seed_hash, server_seed, client_seed, nonce, crash_point, algorithm }
```

### Idempotency

`(operator_id, idempotency_key)` unique. Aynı key + aynı endpoint → cached response (24h). Aynı key + farklı payload → `409 idempotency_conflict`.

---

## 9. Güvenlik

| Risk | Önlem |
|---|---|
| API key sızıntısı | DB'de hash; plaintext bir kere gösterilir; rotation |
| Replay attack | timestamp ±30s + Redis nonce cache 60s |
| Rate limit | Operator + endpoint + IP üçlüsü; Nginx + Laravel |
| Idempotency | `(operator_id, idempotency_key)` unique + 24h cache |
| Transaction locking | `SELECT ... FOR UPDATE` + Redis distributed locks |
| Cashout race | Atomic `UPDATE bets SET status=... WHERE status='placed'` + affected rows |
| Wallet manipulation | Provider double-entry ledger + günlük operator mutabakat |
| Audit trail | `audit_logs` append-only, hash-chained |
| Admin auth | Spatie permissions + zorunlu 2FA |
| DDoS | Cloudflare + Nginx limit_req + WS connect throttle |
| SQL injection | Eloquent + prepared statement; raw query yasak (CI grep) |
| JWT session | RS256, 15dk TTL, refresh yok (launch_url'den yenile) |

---

## 10. Finansal Doğruluk

### Bet atomic flow

```
DB::transaction:
  1. Idempotency check (UNIQUE constraint katmanlı)
  2. Round lockForUpdate, state=='betting' kontrol
  3. Wallet debit (operator callback HTTP, idempotent)
  4. Bet insert
  5. AuditLog write
```

### Cashout race-safe

```
DB::transaction:
  1. current_multiplier = engine.currentMultiplier(round)  // server-side
  2. affected = UPDATE bets SET status='won' WHERE id=? AND status='placed'
  3. abort_if affected==0 → 409 already_settled
  4. Round still running? (crashed_at IS NULL)
  5. payout = amount * current_multiplier (bcmath)
  6. Cashout insert
  7. Wallet credit (operator callback)
```

### Operator callback failure

- HTTP 5s timeout
- Exponential backoff retry (3 deneme)
- Fail → `wallet_transactions.status='failed'`, bet `pending_settlement`
- DLQ + alert
- Admin'de manual reconciliation: `force_settle` veya `rollback`

### Rollback

Operator-initiated `POST /v1/transactions/{tx_id}/rollback`. Idempotent. Provider ters çift kayıt atar, bet `rolled_back`.

---

## 11. Ölçeklenebilirlik

| Faz | Concurrent | Mimari |
|---|---|---|
| **MVP** | ~100 | Tek host: Laravel + Postgres + Redis + Reverb |
| **Closed Beta** | ~1k | Web (2), DB, Redis, Reverb ayrı host |
| **Sertifikasyon trafiği** | ~5k | DB read replica, Horizon worker pool, Reverb 2 node |
| **Production** | ~20k | LB + auto-scale web + DB primary/replica + Redis cluster + Reverb cluster |
| **B2B scale** | ~100k+ | Region replication + Node.js WS gateway + Kafka + per-game sharding |

**Darboğaz sırası:**
1. Tick broadcast → Reverb + Redis pub/sub cluster
2. DB writes → partition + async writer queue
3. Operator callback latency → circuit breaker + parallel HTTP pool
4. Round state → Redis primary, DB eventually consistent

---

## 12. Admin Panel

İki Filament panel: `admin` (internal) + `operator` (self-service).

### Internal admin

| Resource | İçerik |
|---|---|
| Operators | CRUD, API key issue/revoke, IP whitelist, suspend, rev-share, callback test |
| Games | Config (growth_rate, max_bet, edge), enable/disable |
| Live Rounds | Real-time tablo, multiplier, exposure, bet count |
| Bets | Filter operator/player/round/status, CSV export |
| Cashouts | Anomaly highlight |
| RTP Reports | Hourly/daily chart, hedef vs gerçekleşen |
| Player Risk | Risk score, recent activity, kill-switch |
| Webhooks | Delivery log, retry, replay |
| API Logs | Operator request/response, latency |
| Financial Reconciliation | Operator bazlı ledger karşılaştırma |
| Seed Management | Aktif seed, rotate, reveal history |
| Audit Logs | Filtreli arama, immutable |

### Operator self-service

- Stats (bet count, GGR, RTP per period)
- API key management (rotation, revoke)
- Webhook config + delivery logs
- Integration sandbox (test calls)

---

## 13. Frontend

**Stack:** Vue 3 + Vite + Pinia + PixiJS + Laravel Echo

| Bileşen | İçerik |
|---|---|
| Game canvas | PixiJS scene: animation + multiplier text + crash effect |
| Bet panel | Amount, currency, auto-cashout slider, place/cancel |
| Live bet list | Anonim nickname + bet/cashout stream |
| My bets / history | Last N round, P&L |
| Previous rounds | Son 50 round chip (renk: <2x red, ≥2x green, ≥10x purple) |
| Provably fair modal | Hash + seed input + client-side verify |
| Auto cashout | UI hint; server-side enforce |

Iframe içinde operator sitesinde çalışır. `postMessage` ile balance refresh.

---

## 14. Test Stratejisi

| Tür | Araç | Hedef |
|---|---|---|
| Unit | Pest | Service'ler |
| Feature | Pest + Laravel HTTP test | API endpoint, HMAC, idempotency |
| Load | k6 | 10k concurrent bet/cashout, p95 < 100ms |
| RNG distribution | Custom script | 10M round, chi-square, RTP ±0.1% |
| Provably fair verify | Pest dataset | Known seed → known crash round-trip |
| Wallet integrity | Feature test | Concurrent ops, balance invariant |
| Race condition | Pest parallel | 100 concurrent cashout → 1 success |
| WS stress | Artillery | 5k concurrent, tick latency |
| Security | OWASP ZAP, HMAC fuzzer | Replay, signature manipulation, IDOR |
| Chaos | Operator callback timeout/500 | Wallet state korunmalı |

---

## 15. Deployment

### Docker Compose servisleri

```yaml
app:        # Laravel + Octane (RoadRunner), 4 replica
scheduler:  # php artisan schedule:work
horizon:    # queue workers
reverb:     # WS server, 2 replica behind sticky LB
nginx:      # reverse proxy + TLS + rate limit
postgres:   # primary + streaming replica
redis:      # cluster
prometheus: # metrics
grafana:    # dashboards
loki:       # logs
```

### CI/CD

GitHub Actions:
- `phpstan max`
- `pest`
- `pint`
- `trivy` image scan
- Deploy via SSH or ArgoCD

### Secrets

HashiCorp Vault veya AWS Secrets Manager. `.env` git'te asla yok. Production'da env var injection.

### Backup

- Postgres `pg_basebackup` + WAL archive → S3
- Daily restore drill (otomatik)
- 30-day retention, COB raporları arşiv

### Monitoring

- Prometheus `/metrics` (Spatie laravel-prometheus)
- Sentry errors
- Grafana: round latency, bet rate, RTP live, callback failure rate
- PagerDuty alerts: RTP drift >1%, wallet mismatch, callback failure >1%, queue depth

---

## 16. MVP Yol Haritası

| Hafta | İş | Çıktı |
|---|---|---|
| **1** | Repo, CI, Filament admin scaffold, base auth, Postgres+Redis docker | Çalışan iskelet, login |
| **2** | Operator model + API key + HMAC mw + Idempotency mw | HMAC auth çalışır |
| **3** | ProvablyFairService + seed mgmt + verify endpoint + tests | Public verify endpoint |
| **4** | GameRound state machine + RunCrashRoundJob (DB-only) | Round DB'de oluşuyor |
| **5** | Bet placement + WalletService (mock callback) + idempotency | Atomic bet/cashout DB |
| **6** | Laravel Reverb + tick broadcast + Vue iskeleti | Tarayıcıda canlı multiplier |
| **7** | Cashout (manual + auto) + race condition tests | 100 concurrent test → 1 success |
| **8** | Webhook dispatcher + retry + DLQ + real operator callbacks | E2E bet → cashout → operator credit |
| **9** | Filament: operators, live rounds, RTP report, seed mgmt | Admin görünür |
| **10** | Load test 1k concurrent, security review, OpenAPI docs, beta deploy | Closed beta 1 operator alabilir |
| 11-12 | Buffer: bug fix, math report hazırlığı | Sertifikasyon paketi |

---

## 17. Kritik Uyarılar

1. **Lisanssız yayın** → cezai. Beta sanal para ile.
2. **Sertifikasız RNG** → `mt_rand` yasak; sadece `random_bytes/random_int`.
3. **Wallet tutarsızlığı** → günlük otomatik mutabakat + mismatch alert.
4. **Race condition** → `lockForUpdate` + atomic UPDATE WHERE + UNIQUE constraint, katmanlı.
5. **Manipüle edilebilir sonuç** → multiplier client'tan ASLA alınmaz; crash point round-pre belirlenir.
6. **Log eksikliği** → her bet/cashout/callback/admin action audit_logs, 5 yıl retention.
7. **Mutabakat eksikliği** → günlük COB raporu, imzalı arşiv.
8. **Operator API güvenliği** → HMAC + timestamp + IP whitelist + idempotency hepsi birlikte.
9. **WS auth** → connect anında token doğrula; `PrivateChannel` kullan.
10. **TOCTOU** → state check ile DB write arası lock bırakma.
11. **Bonus money** → wallet_tx'te flag ilk migration'da; sonradan eklemek kabus.
12. **Currency precision** → `decimal(18,8)` + `bcmath`; float yasak.

---

## Repo Yapısı

```
orange/
├── app/
│   ├── Domain/                  # Domain-driven boundaries
│   │   ├── Operator/
│   │   ├── Game/
│   │   ├── Wallet/
│   │   ├── ProvablyFair/
│   │   └── Risk/
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   └── Middleware/
│   ├── Filament/                # Admin panel resources
│   ├── Models/                  # Eloquent
│   ├── Events/                  # Broadcast events
│   └── Jobs/                    # Queue jobs (RunCrashRoundJob)
├── database/
│   ├── migrations/
│   └── factories/
├── docker/
│   ├── nginx/
│   ├── php/
│   └── postgres/
├── docs/
│   ├── api/                     # OpenAPI 3.1
│   ├── operator-integration.md
│   └── provably-fair.md
├── tests/
│   ├── Unit/
│   ├── Feature/
│   └── Math/                    # RTP drift tests
├── docker-compose.yml
├── ARCHITECTURE.md (this file)
└── README.md
```
