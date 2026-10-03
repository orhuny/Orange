# OrangeConsumer Entegrasyon Dokümanı

> Hedef: OrangeConsumer'ı Orange'ın **first-class referans operatörü** olarak entegre etmek için, `afq-integrations` projesindeki oppi / egt / softgaming operator-tarafı entegrasyonlarından çıkarılan pattern'leri Orange + OrangeConsumer ikilisine uyarlamak.
>
> Bu doküman read-only kabul edilen `afq-integrations` projesini yalnızca referans olarak inceler; tüm değişiklik önerileri **Orange** veya **OrangeConsumer** projelerine yöneliktir.

---

## 1. Amaç

`afq-integrations` üç farklı casino sağlayıcısını (Oppi, EGT, SoftGaming) tek bir operatör platformuna entegre eden olgun bir referans projedir. Bu projedeki ortak iskelet:

- imza tabanlı doğrulama
- per-provider config + log channel + log tablosu + service + controller + routes file
- idempotent transaction handling
- amount/playerId format dönüşümleri
- merkezi wallet/transaction servisi
- duplicate / rollback / partial-failure yönetimi

Bu pattern'in *aynısı* Orange ile OrangeConsumer arasında uygulanacak; aradaki tek fark **yön**: afq operatör tarafıydı, Orange ise sağlayıcı tarafıdır (Orange = provider, OrangeConsumer = operator).

Doküman aşağıdaki çıktıları üretir:

- Hangi pattern'i Orange'da nasıl somutlaştıracağımız.
- OrangeConsumer'a eklenmesi gereken (şu anda eksik olan) wallet callback alıcısı.
- Orange tarafında onboarding seeder'ı + log channel + embed allow-list.
- Uçtan uca test senaryoları.

---

## 2. İncelenen Referans Entegrasyonlar

| Sağlayıcı | Kaynak (afq-integrations, read-only) | Pattern özeti |
|---|---|---|
| Oppi | [app/Services/OppiCasinoService.php](../../afq-integrations/app/Services/OppiCasinoService.php), [app/Http/Controllers/OppiCasinoController.php](../../afq-integrations/app/Http/Controllers/OppiCasinoController.php), [routes/oppi.php](../../afq-integrations/routes/oppi.php), [config/oppi.php](../../afq-integrations/config/oppi.php) | HMAC-SHA256 + `x-api-key` + `x-timestamp` (±5dk), `clientId*playerId`, amount × 100000, `RS_*` status enum, `oppi_logs` tablosu, `transactions[]` ile bulk rollback. |
| EGT | [app/Services/EgtCasinoService.php](../../afq-integrations/app/Services/EgtCasinoService.php), [app/Http/Controllers/EgtCasinoController.php](../../afq-integrations/app/Http/Controllers/EgtCasinoController.php), [routes/egt.php](../../afq-integrations/routes/egt.php), [config/egt.php](../../afq-integrations/config/egt.php) | HMAC-SHA512 base64 + `X-Checksum-Fields`, defence-code (Redis cache + DB row), `egt_logs`, Client Portal URL caching (5dk). |
| SoftGaming (SG) | [app/Services/SgCasinoService.php](../../afq-integrations/app/Services/SgCasinoService.php), [app/Http/Controllers/SgCasinoController.php](../../afq-integrations/app/Http/Controllers/SgCasinoController.php), [routes/sg.php](../../afq-integrations/routes/sg.php), [config/sg.php](../../afq-integrations/config/sg.php) | `hmac` body alanı + ksort + sha256(sha256(secret)), IP allow-list, `tid` + `i_actionid` idempotency, tek dispatcher endpoint (`type=ping/balance/debit/credit`). |

Ortak destek noktaları (afq tarafında):

- [app/Services/TransactionService.php](../../afq-integrations/app/Services/TransactionService.php) — merkezi cüzdan RPC client'ı (RabbitMQ üzerinden).
- [app/Models/Integration.php](../../afq-integrations/app/Models/Integration.php) — provider başına sabit ID (`OPPI=1`, `EGT=2`, `SG=3`).
- [config/logging.php](../../afq-integrations/config/logging.php) — `oppi`, `egt`, `sg` ayrı log channel'ları.
- [app/Providers/RouteServiceProvider.php](../../afq-integrations/app/Providers/RouteServiceProvider.php) — `Route::prefix('oppi')->group(routes/oppi.php)` ile per-provider mount.

---

## 3. Ortak Entegrasyon Pattern'i

`afq-integrations` üç entegrasyondan çıkarılan **12 ortak ilke**:

1. **İmza** — her dış çağrı HMAC ile imzalanır; ters tarafta middleware/service constant-time `hash_equals` ile doğrular. Timestamp window + nonce/tid cache replay'i engeller.
2. **Player ID formatı** — `clientId{ayraç}playerId`. Çok-operatörlü provider'da zorunlu (oppi/egt `*`, sg `-`). Service'te `parse` / `format` helper'ı vardır.
3. **Amount formatı** — provider'ın iç temsili (oppi int×1e5, egt int×100, sg string 2 ondalık) ile domain decimal arasında `formatAmount` / `parseAmount` ile köprü kurulur. Float **kullanılmaz**, bcmath/integer.
4. **Idempotency** — provider'ın gönderdiği `transactionId / tid / requestId` log tablosunda `(id, type)` çiftiyle dedup edilir. Aynı id farklı amount ile gelirse `DUPLICATE_TRANSACTION_DIFFERENT_AMOUNT` döner; aynı id aynı amount ise cached response döner (yeni cüzdan işlemi yapılmaz).
5. **Per-provider log channel** — `config/logging.php` içinde ayrı kanal → `storage/logs/<provider>.log`. Operasyonel ayrıştırma + grep kolaylığı.
6. **Provider sabit ID** — `Integration` constant'ları wallet / games / log tablolarında FK olarak kullanılır.
7. **Provider başına log tablosu** — `oppi_logs`, `egt_logs`, `sg_logs`. Ortak alanlar: `transaction_id`/`tid`, `player_id`, `game_id`, `type`, `amount`, `balance_before`, `balance_after`, `raw JSON`, `session_id`. `raw` alanı tüm payload'ı tutarak forensic / debug imkanı sağlar.
8. **Merkezi cüzdan servisi** — provider service'leri kendileri ledger tutmaz; `TransactionService::withdraw / deposit` ile domain cüzdanına işler. `transactionType` sabitleri (`casino_play_withdraw`, `casino_play_deposit`, `*_cancel`) standartlaştırılmış.
9. **Per-provider routes file** — `routes/<provider>.php` + `RouteServiceProvider` içinde `Route::prefix('<provider>')->group(...)`. API prefix'ten ayrı.
10. **Game sync + launch URL** — `syncGames` ile provider game list → `casino_games` tablosuna upsert. `getGameUrl` / `generateGameLaunchUrl` imzalı launch URL üretir.
11. **Standardize hata kodu** — `oppi.RS_OK / RS_ERROR_*`, `egt.OK / ERR_*`, `sg.OK / ERROR`. Validation, signature, duplicate, insufficient funds, currency mismatch için açık kodlar.
12. **Currency validation** — provider'ın gönderdiği currency ile platform player'ın kayıtlı currency'si karşılaştırılır; mismatch'te işlem reddedilir.

---

## 4. OrangeConsumer Entegrasyon Yaklaşımı

### 4.1 Mevcut durum

Orange tarafında üst-üste binmiş bir altyapı zaten kuruludur:

- [app/Http/Middleware/VerifyOperatorSignature.php](../app/Http/Middleware/VerifyOperatorSignature.php) — afq'daki `verifySignature` muadili.
- [app/Http/Middleware/EnforceIdempotency.php](../app/Http/Middleware/EnforceIdempotency.php) — `(operator_id, Idempotency-Key)` cache.
- [app/Domain/Operator/Services/OperatorSignature.php](../app/Domain/Operator/Services/OperatorSignature.php) — HMAC-SHA256 imzalayıcı.
- [app/Domain/Wallet/Services/OperatorWalletClient.php](../app/Domain/Wallet/Services/OperatorWalletClient.php) — Orange → Operator wallet callback'i (debit/credit/rollback).
- [app/Domain/Wallet/Services/WalletService.php](../app/Domain/Wallet/Services/WalletService.php) — `wallet_transactions` ledger + retry orchestration.
- [routes/api.php](../routes/api.php) — `/v1/bets`, `/v1/bets/{bet}/cashout`, `/v1/ping`, `/v1/fairness/verify`.

OrangeConsumer tarafında ise:

- [app/Services/Orange/OrangeClient.php](../../OrangeConsumer/app/Services/Orange/OrangeClient.php) — HMAC + log + retry.
- [app/Services/Orange/OrangeSignature.php](../../OrangeConsumer/app/Services/Orange/OrangeSignature.php) — Orange'ınkiyle birebir aynı formül.
- [app/Models/OrangeConsumerLog.php](../../OrangeConsumer/app/Models/OrangeConsumerLog.php) — afq'daki `oppi_logs` muadili (yalnız outbound).

### 4.2 Yön farkı (kritik)

| afq-integrations rol | Orange/OrangeConsumer karşılığı |
|---|---|
| afq = operatör (HMAC doğrular, cüzdan tutar) | OrangeConsumer = operatör (Orange'tan gelen callback'leri HMAC doğrular, ConsumerPlayer.balance ledger'ı tutar) |
| Oppi/EGT/SG = sağlayıcı (HMAC imzalar, cüzdan callback'i atar) | Orange = sağlayıcı (REST endpoint'leri sunar, operatör cüzdanına debit/credit callback'i atar) |
| `oppi_logs`, `egt_logs`, `sg_logs` (inbound provider request'leri) | OrangeConsumer'da yalnız **outbound** `orange_consumer_logs` var; **inbound wallet callback için log eksik** |

### 4.3 Hedef mimari

```
OrangeConsumer (operatör, :8001)                       Orange (sağlayıcı, :8000)
─────────────────────────────────                       ─────────────────────────
[place bet]                                             
  BetService.placeBet                                   
   ├─ wallet.debit (LOCAL stand-in — opsiyonel)        
   └─ OrangeClient.placeBet ──HMAC POST /v1/bets──►    VerifyOperatorSignature
                                                         EnforceIdempotency
                                                         BetPlacementService
                                                          ├─ WalletService.debit
                                                          │   └─ OperatorWalletClient
                                                          │       └──HMAC POST {operator.callback_base_url}/wallet/debit──►   ┐
   VerifyOrangeSignature ◄────────────────────────────────────────────────────────────────────────────────────────────────────┘
   WalletCallbackController.debit                       
    ├─ idempotency check (tx_id)                        
    ├─ ConsumerPlayer.balance -= amount                 
    └─ 200 {balance_after, operator_tx_id} ─────────────►Wallet ledger güncellenir
                                                         BetController 201
   OrangeClient: 201 Created                            
   BetService: response döner                           
```

OrangeConsumer'a eklenecek üç parça **kalın** ile gösterildi: `VerifyOrangeSignature` middleware, `WalletCallbackController`, `wallet_callback_logs` (veya mevcut log tablosuna `direction` alanı).

---

## 5. Gerekli Konfigürasyonlar

### 5.1 Orange tarafı

**`.env` (yeni anahtarlar)**

```
ORANGE_CONSUMER_SECRET=<64-char-rand>          # Operator.secret_key seed'i; production'da Vault'tan beslenir
EMBED_ALLOWED_ORIGINS=http://localhost:8001    # OrangeConsumer iframe'i bu host'tan yüklüyor
```

> **Varsayım**: production'da OrangeConsumer ayrı bir host (`https://consumer.orange.example`) altında deploy edilir; o origin de `EMBED_ALLOWED_ORIGINS` listesine eklenir.

**`config/orangeconsumer.php` (yeni; afq'daki `config/oppi.php` muadili — opsiyonel ama önerilir)**

```php
return [
    'operator_slug'      => env('ORANGE_CONSUMER_SLUG', 'orange-consumer'),
    'operator_name'      => env('ORANGE_CONSUMER_NAME', 'OrangeConsumer Reference Operator'),
    'secret_key'         => env('ORANGE_CONSUMER_SECRET'),
    'callback_base_url'  => env('ORANGE_CONSUMER_CALLBACK_URL', 'http://localhost:8001/api/wallet'),
    'ip_whitelist'       => array_filter(explode(',', (string) env('ORANGE_CONSUMER_IPS', '127.0.0.1,::1'))),
    'default_currency'   => env('ORANGE_CONSUMER_CURRENCY', 'EUR'),
];
```

**`config/logging.php`** — afq'daki `oppi` channel pattern'i:

```php
'operator' => [
    'driver' => 'daily',
    'path'   => storage_path('logs/operator.log'),
    'level'  => env('LOG_LEVEL', 'debug'),
    'days'   => 14,
],
```

`OperatorWalletClient` ve `WalletService` içindeki `Log::warning(...)` çağrıları `Log::channel('operator')->warning(...)` olarak güncellenir.

**Seeder — `database/seeders/ConsumerOperatorSeeder.php` (yeni)**

```php
Operator::updateOrCreate(
    ['slug' => config('orangeconsumer.operator_slug')],
    [
        'name'              => config('orangeconsumer.operator_name'),
        'status'            => OperatorStatus::Active,
        'callback_base_url' => config('orangeconsumer.callback_base_url'),
        'secret_key'        => config('orangeconsumer.secret_key'),   // Eloquent encrypted cast eder
        'ip_whitelist'      => config('orangeconsumer.ip_whitelist'),
        'config'            => ['default_currency' => config('orangeconsumer.default_currency')],
    ],
);
```

`DatabaseSeeder::run()` içine `if (app()->environment(['local', 'testing'])) $this->call(ConsumerOperatorSeeder::class);` ile koşullu çağrı eklenir. Production'da bu operatör Filament admin'inden manuel açılır.

### 5.2 OrangeConsumer tarafı

**`.env` (mevcut anahtarlar — README'sinde zaten dokümante; yeni anahtar yok)**

```
ORANGE_BASE_URL=http://localhost:8000
ORANGE_OPERATOR_ID=<seeder'ın ürettiği UUID>
ORANGE_API_SECRET=<ORANGE_CONSUMER_SECRET ile aynı>
```

Aynı `ORANGE_API_SECRET` iki rolde kullanılır:

1. OrangeConsumer → Orange çağrılarını **imzalamak**.
2. Orange → OrangeConsumer wallet callback'lerini **doğrulamak** (yeni middleware).

**`config/orange.php`** — mevcut dosyada bir alan **eklenmesi önerilir** (kullanım kolaylığı için ek; mevcut alanlara dokunulmaz):

```php
'callback_signature_window_seconds' => (int) env('ORANGE_CALLBACK_TS_WINDOW', 30),
```

---

## 6. Servis ve Akış Tasarımı

### 6.1 Orange tarafı (çoğu mevcut — yalnız küçük dokunuşlar)

| Bileşen | Durum | Aksiyon |
|---|---|---|
| `VerifyOperatorSignature` middleware | Var | Değişiklik yok |
| `EnforceIdempotency` middleware | Var | Değişiklik yok |
| `OperatorWalletClient::dispatch` | Var | Log channel'ı `operator`'a yönlendir |
| `WalletService::debit/credit/rollback` | Var | Aynı şekilde log channel güncelle |
| `ConsumerOperatorSeeder` | **Yeni** | §5.1 |
| `config/orangeconsumer.php` | **Yeni (ops.)** | §5.1 |
| `logging.operator` channel | **Yeni** | §5.1 |

### 6.2 OrangeConsumer tarafı (yeni sınıflar)

Klasör yapısı (afq'daki Oppi pattern'inin OrangeConsumer'a uyarlanmış hali):

```
OrangeConsumer/
├── app/
│   ├── Http/
│   │   ├── Middleware/
│   │   │   └── VerifyOrangeSignature.php          # YENİ
│   │   └── Controllers/
│   │       └── Wallet/
│   │           └── WalletCallbackController.php    # YENİ
│   ├── Services/
│   │   └── Orange/
│   │       └── WalletReceiver.php                  # YENİ (domain helper)
│   └── Models/
│       └── WalletCallbackLog.php                   # YENİ (veya direction kolonu)
├── database/
│   └── migrations/
│       └── 2026_05_24_000001_create_wallet_callback_logs_table.php   # YENİ
└── routes/
    └── api.php                                     # GÜNCELLE
```

**`VerifyOrangeSignature` middleware** — `OperatorSignature::compute` ile aynı formülü kullanır:

```php
$expected = hash_hmac('sha256',
    strtoupper($req->method())."\n".'/'.ltrim($req->path(), '/')."\n".$req->getContent()."\n".$timestamp,
    config('orange.api_secret')
);
if (!hash_equals($expected, $req->header('X-Signature'))) abort(401, 'signature_mismatch');
```

`X-Operator-Id` header'ı `config('orange.operator_id')` ile karşılaştırılır; eşleşmezse 401. Timestamp window `config('orange.callback_signature_window_seconds')`.

**`WalletCallbackController`** — afq'daki `OppiCasinoController::processBet` + `processPayout` + `rollback` muadili, ama yön ters:

```php
public function debit(Request $r) {
    $r->validate([
        'player_external_id' => 'required|string',
        'amount' => 'required|numeric|gt:0',
        'currency' => 'required|string|size:3',
        'tx_id' => 'required|string',
        'reference' => 'required|array',
    ]);

    return DB::transaction(function () use ($r) {
        // Idempotency — afq'daki transactionExists muadili
        if ($existing = WalletCallbackLog::where('tx_id', $r->tx_id)->first()) {
            return response()->json($existing->response_payload, 200)
                ->header('Idempotent-Replay', 'true');
        }

        $player = ConsumerPlayer::where('external_player_id', $r->player_external_id)->lockForUpdate()->firstOrFail();

        if (bccomp($player->balance, $r->amount, 8) < 0) {
            return response()->json(['error' => 'insufficient_funds'], 402);
        }

        $player->balance = bcsub($player->balance, $r->amount, 8);
        $player->save();

        $resp = ['balance_after' => $player->balance, 'operator_tx_id' => 'oc_'.Str::ulid()];
        WalletCallbackLog::create([
            'tx_id' => $r->tx_id,
            'type' => 'debit',
            'player_id' => $player->id,
            'amount' => $r->amount,
            'currency' => $r->currency,
            'request_payload' => $r->all(),
            'response_payload' => $resp,
            'status_code' => 200,
        ]);
        return response()->json($resp);
    });
}
```

`credit` debit'in tersidir; `rollback` ise `tx_id` referansından orijinali bulup ters yöne yazar (afq Oppi `rollback`'taki gibi).

**Routes — `routes/api.php`'e ekle:**

```php
Route::prefix('wallet')
    ->middleware(VerifyOrangeSignature::class)
    ->controller(WalletCallbackController::class)
    ->group(function () {
        Route::post('debit',    'debit');
        Route::post('credit',   'credit');
        Route::post('rollback', 'rollback');
    });
```

**Migration — `wallet_callback_logs`:**

```php
Schema::create('wallet_callback_logs', function (Blueprint $t) {
    $t->id();
    $t->string('tx_id')->unique();              // Idempotency anchor
    $t->string('type');                          // debit | credit | rollback
    $t->foreignId('player_id')->nullable()->constrained('consumer_players');
    $t->decimal('amount', 18, 8);
    $t->string('currency', 3);
    $t->json('request_payload');
    $t->json('response_payload')->nullable();
    $t->unsignedSmallInteger('status_code')->nullable();
    $t->string('reference_type')->nullable();
    $t->string('reference_id')->nullable();
    $t->timestamps();
    $t->index('player_id');
    $t->index('type');
});
```

### 6.3 Çift cüzdan tehlikesi

OrangeConsumer'ın `BetService` şu an Orange çağrısından **önce** lokal `WalletService::debit` çağırıyor (Orange'da wallet endpoint olmadığı için stand-in). Orange artık wallet callback atmaya başlayınca **aynı bet için iki kez** balance düşer.

Çözüm seçenekleri:

- **A (önerilen)**: `BetService::placeBet` içindeki lokal debit'i kaldır → tek source of truth Orange callback. Bet response'u 201 dönerse balance zaten WalletCallbackController eliyle düşmüş olur.
- **B**: Lokal debit'i koru, callback geldiğinde `tx_id` ile maç ettirip skip et (idempotent). Daha karmaşık ama race-safe.

Doküman seçeneği A'yı varsayar; B tercih edilirse §11.5'teki notu uygula.

---

## 7. Hata Yönetimi

### 7.1 OrangeConsumer wallet callback yanıtları (Orange'ın beklediği)

| HTTP | Body | Anlam | Orange davranışı |
|---|---|---|---|
| 200 | `{balance_after, operator_tx_id}` | Başarılı | `wallet_transactions.status=completed` |
| 401 | `{error: "signature_mismatch"}` | HMAC fail | 4xx → retry **yok**, `failed` |
| 402 | `{error: "insufficient_funds"}` | Yetersiz bakiye | 4xx → `failed`; admin reconciliation |
| 404 | `{error: "player_not_found"}` | external_player_id yok | 4xx → `failed` |
| 409 | `{error: "already_processed"}` + cached body | tx_id duplicate | İdeal: 200 + Idempotent-Replay header → Orange success kabul eder |
| 422 | `{error: "validation_failed", ...}` | Currency/amount validation | 4xx → `failed` |
| 5xx | `{error: "internal"}` | Operator hatası | 3 retry exponential backoff, sonra `failed` |

### 7.2 Orange tarafı hata kodları (mevcut, dokümante edildi)

- **VerifyOperatorSignature** (401): `missing_signature_headers`, `invalid_timestamp`, `timestamp_outside_window`, `unknown_operator`, `operator_inactive`, `signature_mismatch`, `replay_detected`. Detay [docs/operator-integration.md](operator-integration.md#7-error-codes).
- **EnforceIdempotency**: 400 `missing_idempotency_key`, 409 `idempotency_conflict`.
- **BetController**: 404 `unknown_round`, 409 `betting_closed`, 422 validation.
- **CashoutController**: 404 `bet_not_found`, 409 `already_cashed_out` / `already_settled` / `round_not_running`.

### 7.3 Retry stratejisi (mevcut [config/wallet.php](../config/wallet.php))

- Timeout 5s, 3 attempt, 500ms backoff (exponential).
- 4xx → ASLA retry; `WalletCallbackFailed` exception → `wallet_transactions.status=failed` → admin reconciliation gerekir.
- 5xx / network → retry exhausted ise `failed` + (öneri) DLQ alarmı.

### 7.4 Partial failure örnekleri

- **Bet 201 + wallet debit fail**: Orange'da bet kaydı oluştu ama operator cüzdanı düşmedi → `wallet_transactions.status=failed`. Player UI'da balance düşmedi ama bet open görünür. Manual reconciliation: `wallet_transactions` admin ekranından retry/rollback.
- **Cashout 200 + wallet credit fail**: Orange'da cashout oluştu, payout operator'a iletilemedi → `failed` flag → admin retry → operator balance gerçekleşir.

---

## 8. Loglama ve İzleme

### 8.1 Orange tarafı

- **Yeni `operator` log channel** (§5.1). `OperatorWalletClient::dispatch` her attempt'i loglar: `{operator_id, tx_id, type, url, attempt, status, latency_ms}`.
- `WalletService::record` mevcut `wallet.callback.failed` warning'i `Log::channel('operator')` üzerine taşınır.
- **`wallet_transactions` tablosu** zaten attempt sayısı, response body ve final status'u tutuyor → her şey persistent.
- Filament admin'inde **Wallet Transactions** Resource → filter `status=failed` + retry button.

### 8.2 OrangeConsumer tarafı

- Mevcut [`orange_consumer_logs`](../../OrangeConsumer/app/Models/OrangeConsumerLog.php) **outbound** kayıtları tutar.
- **Yeni `wallet_callback_logs`** inbound kayıtları tutar (§6.2).
- (Alternatif) Mevcut tabloya `direction` enum kolonu eklenip tek tabloda birleştirilebilir; ancak ayrı tablo afq pattern'ine daha yakındır (her tablo kendi schema'sıyla).
- Test panel `/consumer/logs` route'una `wallet_callback_logs` listing'i de eklenir.

### 8.3 Metrik ve alarm önerileri

| Metrik | Eşik | Eylem |
|---|---|---|
| `wallet.callback.attempts.failed.rate` | > %1 / 5dk | PagerDuty |
| `wallet.callback.latency.p95` | > 2s | Warning |
| `operator.signature.replay_detected.count` | > 0 | Investigate (clock skew veya suistimal) |
| Reverb `crash` channel disconnect | > 30s | UI banner |

ARCHITECTURE.md'deki RTP drift / wallet mismatch alarmları zaten tanımlı; bu doküman onların üzerine ekler.

---

## 9. Test Senaryoları

### 9.1 Smoke (happy path)

1. `php artisan db:seed --class=ConsumerOperatorSeeder` (Orange).
2. OrangeConsumer `.env` → seeder'ın bastığı operator UUID + shared secret.
3. `POST /v1/ping` (signed) → 201 + echo body. **PASS** kriteri: HMAC çalışıyor.
4. Round.betting_opened event'i WSS'ten gelir → BetService.placeBet → Orange 201 + WalletCallbackController.debit 200.
5. ConsumerPlayer.balance düşer + Orange `wallet_transactions.status=completed`.
6. Cashout → Orange 200 + WalletCallbackController.credit 200 → balance artar.

### 9.2 Negative path (per pattern)

| Test | Beklenen |
|---|---|
| Yanlış secret ile sign | 401 `signature_mismatch` |
| `time() - 60` ile sign | 401 `timestamp_outside_window` |
| Aynı `Idempotency-Key` farklı body | 409 `idempotency_conflict` |
| Round dışı bet | 409 `betting_closed` |
| Unknown `round_uuid` | 404 `unknown_round` |
| OrangeConsumer offline + bet → wallet callback | Bet 201, `wallet_transactions.status=failed` (3 retry) |
| ConsumerPlayer balance < amount + callback | 402 `insufficient_funds` döner; Orange operatöre 4xx olarak işler |
| Aynı `tx_id` ile callback iki kez | 1st: 200 normal; 2nd: 200 + `Idempotent-Replay: true`, balance bir kez düşer |
| 100 paralel cashout, aynı bet | 1 success (200), 99 × 409 `already_settled` |

### 9.3 Provably-fair

`POST /v1/fairness/verify` → seed reveal sonrası round'un crash_point'i client tarafında reproduce edilebilmeli. (Mevcut [docs/api/openapi.yaml](api/openapi.yaml) ve [ARCHITECTURE.md §6](../ARCHITECTURE.md#6-provably-fair-algoritması)'da dokümante.)

### 9.4 OrangeConsumer wallet endpoint unit + feature testleri

- Feature: `Http::fake()` ile Orange'ı stub'la, callback endpoint'ini real model + DB ile test et.
- Unit: `VerifyOrangeSignature` middleware'i — happy path, bad signature, stale timestamp, replay.
- Idempotency: `tx_id` dup → cached response, balance değişmez.

### 9.5 Cross-project sync test

OrangeConsumer'ın `ConsumerPlayerSeeder.external_player_id` değerleri Orange `players.external_player_id` ile **eşleşmeli**, aksi halde callback `player_not_found` (404) döner. Seeder'lar koordineli çalışmalı (§11.4).

---

## 10. Uygulama Adımları (sıralı)

> Aşağıdaki sıralama mümkün olduğunca her adımda çalışan bir state bırakır.

1. **Orange — `ConsumerOperatorSeeder` ekle** ve `DatabaseSeeder`'a koşullu çağrı ekle. Yerel migrate+seed → `operators` tablosunda satır oluşur.
2. **Orange — `config/orangeconsumer.php` ekle** (opsiyonel ama önerilir). `.env`'ye `ORANGE_CONSUMER_SECRET` yaz.
3. **Orange — `logging.php` `operator` channel ekle**. `OperatorWalletClient` ve `WalletService` içindeki `Log::warning` çağrılarını `Log::channel('operator')->...` ile değiştir.
4. **Orange — `EMBED_ALLOWED_ORIGINS` env'sine OrangeConsumer origin'lerini ekle.** [config/embed.php](../config/embed.php) zaten env-driven.
5. **OrangeConsumer — `ORANGE_OPERATOR_ID` ve `ORANGE_API_SECRET`** seeder çıktısına göre `.env`'ye yaz.
6. **OrangeConsumer — `wallet_callback_logs` migration + model ekle.**
7. **OrangeConsumer — `VerifyOrangeSignature` middleware ekle** (`OrangeSignature::compute` formülü ile birebir, ters yönde).
8. **OrangeConsumer — `WalletCallbackController::{debit, credit, rollback}` ekle**, `routes/api.php`'e mount et.
9. **OrangeConsumer — `BetService::placeBet`'teki lokal debit'i kaldır** (§6.3 seçenek A) veya idempotency match'i ekle (seçenek B).
10. **End-to-end smoke test**:
    - Orange: `php artisan serve --port=8000`, `php artisan reverb:start`, `php artisan queue:work`.
    - OrangeConsumer: `php artisan serve --port=8001`.
    - Tarayıcı: `http://localhost:8001/consumer/players/1` → embed iframe → Place Bet → Cashout. İki taraftaki log dosyalarını gözle (`storage/logs/operator-*.log` ve `wallet_callback_logs` rows).
11. **Filament admin'den wallet_transactions ekranını gözle** → `completed` statüsünde olmalı.
12. **Negative path testlerini koştur** (§9.2 listesi).
13. **README cross-link**:
    - [Orange/README.md](../README.md) içine bu dokümana referans.
    - [OrangeConsumer/README.md](../../OrangeConsumer/README.md) §"Known limitations" bölümünden "Orange'ın wallet endpoint'i yok" maddesini silip yeni callback receiver'a link ver.

---

## 11. Dikkat Edilmesi Gerekenler

1. **Yön ters**: afq'da operatör HMAC'i doğrularken, OrangeConsumer'da hem **imzalayıcı** (outbound) hem **doğrulayıcı** (inbound) rolündedir. İki tarafta da aynı `OrangeSignature::compute` formülü kullanılmalı (`METHOD\nPATH\nBODY\nTIMESTAMP`).

2. **`secret_key` encrypted cast**: Orange'da [Operator::casts()](../app/Domain/Operator/Models/Operator.php) içinde `secret_key` encrypted. `Operator::find($id)->secret_key` accessor'dan plaintext döner; raw DB query ile okuma garbage verir → signature mismatch. Seeder'da plaintext yazılır, Eloquent encrypt eder.

3. **Path imzalama**: Orange `bootstrap/app.php` `apiPrefix: ''` → route mount `/v1/...`. Hem `VerifyOperatorSignature` ([middleware.path](../app/Http/Middleware/VerifyOperatorSignature.php#L51)) hem `OperatorWalletClient` ([parse_url path](../app/Domain/Wallet/Services/OperatorWalletClient.php#L44)) bu prefix'i kullanır. OrangeConsumer'da callback path'i `/api/wallet/debit` → `parse_url($url, PHP_URL_PATH)` doğru sonuç verir.

4. **Idempotency-Key her request için fresh**, **retry'da AYNI key**. Mevcut [OrangeClient::signedPost](../../OrangeConsumer/app/Services/Orange/OrangeClient.php#L116) her çağrıda `Str::uuid()` üretiyor → retry path'i için BetService seviyesinde key memoization gerekir. afq'daki Oppi pattern'inde `transactionId` request'le birlikte input olarak gelir; OrangeConsumer'da bunu `Str::orderedUuid()` ile bet domain'inde tek üretip retry'larda cache'lemek (örn. Redis 24h) gerekir.

5. **Çift cüzdan**: §6.3'te anlatıldı. Seçenek A → tek source of truth Orange callback; seçenek B → idempotency-anchored skip. **Varsayım**: bu doküman A'yı baz alır.

6. **Replay cache TTL 60s, timestamp window 30s**: middleware'daki bu boşluk OrangeConsumer outbound çağrılarda nadiren tetiklenir (her çağrı fresh `time()`). Wallet callback'lerde Orange tek sefer atar → replay tetiklenmez.

7. **Embed origin**: localhost:8001 ile çalışıyorsa `EMBED_ALLOWED_ORIGINS` env'sine eklenmediği sürece browser iframe'i `frame-ancestors` CSP nedeniyle render etmez. Staging ve prod origin'leri de eklenmeli.

8. **Reverb auth**: `crash` channel public → key paylaşımı yeterli (`ORANGE_REVERB_KEY`). Private channel'a (`private-player.{id}`) geçilirse Pusher auth endpoint sign edilmeli, bu doküman kapsamı dışı.

9. **Wallet callback URL absolute & doğru port**: `Operator.callback_base_url = http://localhost:8001/api/wallet` formatında (sonunda **tek tip** type ekleniyor → debit/credit/rollback). [OperatorWalletClient::dispatch](../app/Domain/Wallet/Services/OperatorWalletClient.php#L33) `rtrim + /wallet/<type>` yapıyor; çift `/wallet` olmaması için seeder'da `callback_base_url`'i `/api` ile bitir, type kısmı service tarafından append edilsin. (afq'da bu sorunlu olabiliyor; doğrulama gerekir.)

   > **Varsayım**: Üst koddaki [parse_url path](../app/Domain/Wallet/Services/OperatorWalletClient.php#L44) `/wallet/debit` yerine `/api/wallet/debit` döner; OrangeConsumer middleware bu path'i imzalı doğrularken aynı path'i kullanmalı. Doğru çalışıp çalışmadığı uçtan uca testte (§10.10) sınanmalı.

10. **`OrangeConsumerLog::create` log persistance hatası swallow**: [OrangeClient::logAndReturn](../../OrangeConsumer/app/Services/Orange/OrangeClient.php#L301-L317) log persist fail'i `Log::warning` ile yutuyor — production'da bu davranış audit gereksinimini ihlal edebilir. Production'da fail-loud olabilir.

11. **Production'da secret rotation**: bu doküman dev/test odaklı. Production'da:
    - `operators.secret_key` rotate edildiğinde OrangeConsumer'ın `.env`'i de aynı anda güncellenmeli.
    - Geçiş süresi boyunca **iki secret** doğrulayan grace window gerekir veya kısa downtime kabul edilir.
    - Bu prosedür ayrı bir runbook'ta tanımlanmalı (bu dokümanın scope'u dışında).

12. **Currency tutarlılığı**: `ConsumerPlayer.currency` ↔ Orange `players.currency` ↔ Orange operator `config.default_currency`. Bet request'inde uyumsuzluk 422 döner. Seeder'ları çift-yönlü senkron tutmak şarttır (§11.4).

13. **OrangeConsumer test player seeder cross-sync**:
    - Bugün [OrangeConsumer `ConsumerPlayerSeeder`](../../OrangeConsumer/database/seeders/ConsumerPlayerSeeder.php) `ext_player_0001..0005` deterministik player'lar oluşturuyor.
    - Orange'da aynı `external_player_id`'lere sahip `players` satırları olmalı, yoksa `unknown_player` / `player_not_found` döner.
    - Çözüm: Orange tarafına da bir `ConsumerPlayerMirrorSeeder` ekle; OrangeConsumer seed'inden sonra Orange seed'i tetiklensin, ya da iki tarafta sabit listeyi paylaşan bir JSON fixture kullan.
    - **Varsayım**: bu cross-seed bu dokümanın "Uygulama Adımları" §10'unun bir alt adımı olarak ele alınır; varsayılan implementasyon: Orange `ConsumerOperatorSeeder` aynı zamanda 10 test player'ı (UUID'siz, external_player_id ile) seed eder.

14. **`Idempotent-Replay` header**: Orange middleware bu header'ı yanıtta dönüyor ([EnforceIdempotency.php:48](../app/Http/Middleware/EnforceIdempotency.php#L48)). OrangeConsumer wallet callback handler da aynı semantiği taşımalı → Orange retry path'inde cache'i hit ettiğinde net bir gözlem sağlanır.

15. **`Operator.ip_whitelist` enforcement henüz yok**: `Operator` modeli alanı taşıyor ama `VerifyOperatorSignature` middleware'i kontrol etmiyor. afq'daki sg pattern'inde IP allow-list zorunluydu. Production'da bu kontrol middleware'a eklenmeli (`if (!in_array($req->ip(), $operator->ip_whitelist ?? [], true)) abort(401, 'ip_not_allowed');`). Bu dokümanın kapsamı dışındadır ama eklenmelidir.

16. **afq pattern'ından alınan tek pas geçilen kural**: per-provider `Integration` constant. Orange'da operator UUID kullanılıyor → constant gereksiz; ancak OrangeConsumer için "test refer operator" gibi anlamlı bir slug (`orange-consumer`) sabitlenmeli ki kod içinde `Operator::where('slug', config('orangeconsumer.operator_slug'))->first()` ile bulunabilsin.

---

## 12. Çapraz Referanslar

- [Orange/ARCHITECTURE.md](../ARCHITECTURE.md) — sistemin tamamı, MVP yol haritası, kritik uyarılar.
- [Orange/docs/operator-integration.md](operator-integration.md) — public operator API contract'ı (HMAC, REST, WS, iframe).
- [OrangeConsumer/README.md](../../OrangeConsumer/README.md) — referans operatör test paneli.
- [afq-integrations OPPI README](../../afq-integrations/README_OPPI_CASINO_INTEGRATION.md) — read-only referans.
- [afq-integrations EGT README](../../afq-integrations/README_EGT_CASINO_INTEGRATION.md) — read-only referans.

> İletişim: `dev@arkingroup.com`.
