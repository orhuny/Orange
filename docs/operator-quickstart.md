# Orange — Operatör Entegrasyon Quickstart

> Bu doküman Orange crash oyununu kendi platformuna entegre edecek operatörler için **kısa** rehberdir. Daha derin teknik detay için: [operator-integration.md](operator-integration.md).

---

## 0. Orange'tan sana verilenler

Onboarding bittiğinde Orange sana 5 değer iletir (güvenli kanal üzerinden):

| Değer | Örnek | Açıklama |
|---|---|---|
| `operator_id` | `019e2fb2-…` | UUID v7. `X-Operator-Id` header'ı olarak gönderilir |
| `api_secret` | 64 hex karakter | HMAC-SHA256 imzalama anahtarı. **Client'a kesinlikle ifşa etme** |
| `base_url` | `https://api.orange.example` | Orange host kökü |
| `reverb_host` + `reverb_port` + `reverb_key` | `wss.orange.example` / `443` / `orange-key` | WebSocket bağlantı bilgisi (public key) |
| `embed_url` | `https://api.orange.example/crash` | Iframe ile gömülecek oyun sayfası |

Bunları `.env` veya secret manager'ında sakla.

---

## 1. Toplam 4 tane HTTP endpoint var

| Yön | Endpoint | Ne yapıyor |
|---|---|---|
| Sen → Orange | `POST /v1/bets` | Bahis yatır |
| Sen → Orange | `POST /v1/bets/{bet_id}/cashout` | Bahisi kapat |
| Orange → Sen | `POST {senin_callback_url}/wallet/debit` | Bahis yatınca Orange senin oyuncu cüzdanından düşer |
| Orange → Sen | `POST {senin_callback_url}/wallet/credit` | Cashout başarılı olunca Orange senin cüzdanına ekler |

Orange'a verdiğin `callback_base_url` set'i `wallet/debit` ve `wallet/credit` endpoint'lerinin altına mount edilir. Yani `https://your.site/api` verirsen Orange `https://your.site/api/wallet/debit`'e POST'lar.

---

## 2. HMAC imza (zorunlu, 4 endpoint için de aynı)

Her mutating çağrıda 4 header:

```
X-Operator-Id: <operator UUID'sin>
X-Timestamp:   <unix saniye, şu an>
X-Signature:   hash_hmac('sha256', message, api_secret)
Idempotency-Key: <her request için fresh UUID>
```

`message` formatı (literal `\n`):

```
METHOD\nPATH\nBODY\nTIMESTAMP
```

- `METHOD`: büyük harf, `POST`.
- `PATH`: leading `/` ile, ör. `/v1/bets`. Callback'lerde: `/api/wallet/debit` (senin mount path'in).
- `BODY`: HTTP gövdesinin **birebir** byte'ları. Boşsa boş string.
- `TIMESTAMP`: aynı `X-Timestamp` integer'ı.

PHP örneği:

```php
$message = "POST\n/v1/bets\n{$jsonBody}\n{$timestamp}";
$signature = hash_hmac('sha256', $message, $apiSecret);
```

Aynı formülü hem **outbound** (Orange'a giderken) hem **inbound** (Orange'dan callback geldiğinde) kullanırsın. Tek anahtar, iki yön.

---

## 3. Adım adım entegrasyon

### Adım 1 — Outbound signer

Sunucu tarafında küçük bir helper:

```pseudocode
function sendSigned(method, path, body):
    timestamp = now_unix_seconds()
    signature = hmac_sha256(api_secret, method + "\n" + path + "\n" + body + "\n" + timestamp)
    return HTTP request with:
        headers: X-Operator-Id, X-Timestamp, X-Signature, Idempotency-Key=fresh_uuid
        body:    body
```

`Idempotency-Key`'i **her request için fresh UUID** üret. Eğer retry yapacaksan aynı key'i kullan (Orange duplicate'i cached response'la cevaplar).

### Adım 2 — Wallet callback receiver (sen sunucu olarak)

3 endpoint aç:

```
POST {your_base}/api/wallet/debit
POST {your_base}/api/wallet/credit
POST {your_base}/api/wallet/rollback
```

Her birinde:
1. **Verify HMAC**: aynı formülü Orange'ın gönderdiği header'larla doğrula. Eşleşmezse 401.
2. **Idempotency check**: `tx_id` zaten log tablonda varsa, cached response'u dön (`200` + `Idempotent-Replay: true` header).
3. **Player'ı `player_external_id` ile bul**. Yoksa 404.
4. **Currency match** kontrol et. Tutmazsa 422.
5. **Bakiye işlemi** (DB transaction içinde, row lock ile):
   - `debit`: balance düş. Yetersizse 402 `insufficient_funds`.
   - `credit`: balance ekle.
   - `rollback`: orijinal `tx_id` referansını bul, ters çevir.
6. **Response**:
   ```json
   { "balance_after": "100.00000000", "operator_tx_id": "your_unique_id" }
   ```

Beklenen request body (Orange senin endpoint'ine gönderir):
```json
{
  "player_external_id": "ext_player_001",
  "amount": "10.00000000",
  "currency": "EUR",
  "tx_id": "bet:abc-uuid",
  "reference": { "type": "debit", "bet_id": 42 }
}
```

### Adım 3 — Round bilgilerini WebSocket'ten dinle

Orange tarafında yeni round açıldığında client'ın haberdar olması için Reverb (Pusher protokolü) channel'ına subscribe ol:

```js
const pusher = new Pusher(reverbKey, {
    wsHost: reverbHost, wsPort: reverbPort, wssPort: reverbPort,
    forceTLS: true, enabledTransports: ['ws', 'wss'],
    disableStats: true, cluster: 'mt1',
});
const channel = pusher.subscribe('crash');

channel.bind('round.betting_opened', (e) => {
    // e.round_uuid → bahis yatırırken bu UUID'yi POST /v1/bets gövdesine koy
});
channel.bind('round.started',  (e) => { /* round canlı, cashout açık */ });
channel.bind('round.tick',     (e) => { /* e.multiplier (10 Hz) */ });
channel.bind('round.crashed',  (e) => { /* round bitti, crash_point */ });
channel.bind('round.settled',  (e) => { /* seed reveal */ });
```

`crash` channel **public** — auth gerekmez.

### Adım 4 — Oyun UI'sini iframe ile göm

```html
<iframe
    src="{embed_url}"
    allow="autoplay; fullscreen"
    allowfullscreen
    width="100%" height="720"
    style="border:0;">
</iframe>
```

Orange'a operatör origin'(ler)inizi (`https://your.site`, staging dahil) bildir, allow-list'e eklesin (yoksa browser CSP iframe'i bloklar).

Iframe içinde **küçük bir HUD** var (Place Bet / Cash Out butonları). Buton tıklamaları doğrudan Orange'a gitmez — `postMessage` ile parent sayfana intent gönderir. Sen onları sunucundan signed REST çağrısına çevirip Orange'a iletirsin.

iframe → parent mesajları:
| `type` | `payload` | Ne zaman |
|---|---|---|
| `embed_ready` | `{}` | Iframe hazır, `player_info` bekliyor |
| `place_bet` | `{ amount, auto_cashout_at, round_uuid }` | Kullanıcı Place Bet'e bastı |
| `cashout` | `{ bet_id }` | Kullanıcı Cash Out'a bastı |
| `enter_fullscreen` / `exit_fullscreen` | `{}` | Sağ üst fullscreen toggle |

parent → iframe mesajları:
| `type` | `payload` | Amaç |
|---|---|---|
| `player_info` | `{ id, username, currency, balance }` | Kim oynuyor |
| `round_state` | `{ phase, round_uuid }` | UI state senkronu |
| `bet_placed_result` | `{ success, bet_id, … }` | `/v1/bets` cevabını yansıt |
| `cashout_result` | `{ success, payout, … }` | `/v1/bets/{id}/cashout` cevabını yansıt |
| `fullscreen_state` | `{ active: bool }` | Fullscreen on/off (iframe ikonu flip eder) |

**Güvenlik**: parent tarafta `event.origin === embed_url_host` kontrolü zorunlu.

### Adım 5 — Bahis flow'u uçtan uca

```
1. Browser: WebSocket'ten `round.betting_opened` event'i geldi → state.roundUuid = X
2. Kullanıcı iframe HUD'unda Place Bet → iframe parent'a `place_bet` postMessage
3. Parent JS → sunucuna POST /your-app/bets (kendi API'n)
4. Sunucun → HMAC signed POST /v1/bets to Orange
   Body: { player_external_id, round_uuid: X, amount, currency, auto_cashout_at? }
5. Orange 201 Created + { bet_id, status: "placed", … } döner
6. Aynı sırada (saniye altı) Orange senin /api/wallet/debit endpoint'ine POST atar
   (queue worker async firelar, bet response'unu beklemez)
7. Sen wallet/debit'i işle, balance düş, 200 dön
8. Round running → tick stream → kullanıcı Cash Out'a basar
9. Sunucun → POST /v1/bets/{bet_id}/cashout to Orange
10. Orange 200 + { multiplier, payout_amount, … }
11. Orange aynı şekilde /api/wallet/credit'i async firelar
12. Sen credit'i işle, balance ekle
```

---

## 4. Hızlı test (entegrasyonun çalıştığını doğrula)

1. **Imza testi**: `POST /v1/ping` (HMAC ile, gövde `{"hello":"orange"}`). 201 + echo bekle.
2. **Saat kayması testi**: timestamp'i 60s ileri/geri at, `timestamp_outside_window` (401) bekle.
3. **Replay testi**: aynı imzalı request'i iki kere gönder, ikincisinde `replay_detected` (401).
4. **Idempotency testi**: aynı Idempotency-Key ile iki bet, ikincisi cached response döndürür (yeni bet yaratmaz).
5. **Happy path**: WebSocket'ten `betting_opened` bekle, bet yatır, `bet 201 → wallet/debit 200` zincirini logla, cashout yap, `cashout 200 → wallet/credit 200` zincirini logla.
6. **Loss path**: bahis yatır, cashout yapma, `round.crashed`'i bekle. Senin tarafta wallet/debit zaten düştü, geri ekleme yok (kaybedildi, doğru davranış).

Çalışan referans operatör: `OrangeConsumer` projesi. `.env`'sini `ORANGE_BASE_URL` + `ORANGE_OPERATOR_ID` + `ORANGE_API_SECRET` ile ayarlayıp kıyas yap.

---

## 5. Production'a geçmeden önce

- [ ] `api_secret`'i kod havuzunda **yok**. Vault / Secret Manager kullanılıyor.
- [ ] Her bet için fresh `Idempotency-Key` UUID üretiliyor; retry'da **aynı** key kullanılıyor.
- [ ] Wallet callback receiver'da `tx_id` ile idempotency, row lock ile balance update var.
- [ ] Operator origin(leri) Orange'a bildirildi, iframe `frame-ancestors` CSP hatası vermiyor.
- [ ] WebSocket auto-reconnect mantığı yazılı.
- [ ] `decimal(18,8)` / bcmath kullanılıyor — float yasak.
- [ ] Saat NTP ile senkronize, ±30s window içinde.
- [ ] `wallet/debit` 4xx hataları operatör tarafında alarmlanıyor (insufficient_funds, currency_mismatch, vb.).
- [ ] Logging: Orange'a gönderilen + Orange'dan gelen tüm request/response persistent (en az 30 gün).
- [ ] (Test ortamında) IP whitelist tedarik edildi; production'da Orange'a senin sunucularının çıkış IP'leri verildi.

---

## 6. Hata kodları — kısa sözlük

| HTTP | Body `error` | Anlamı |
|---|---|---|
| 401 | `signature_mismatch` | HMAC formülü/secret yanlış |
| 401 | `timestamp_outside_window` | Saat kayması >30s |
| 401 | `replay_detected` | Aynı (timestamp+signature) ikinci kez geldi |
| 401 | `unknown_operator` | `X-Operator-Id` Orange DB'sinde yok |
| 404 | `unknown_round` | `round_uuid` Orange'da yok |
| 409 | `betting_closed` | Bet attığın round artık `betting` state'inde değil |
| 409 | `already_cashed_out` / `already_settled` | Cashout zaten yapıldı |
| 422 | (validation) | Body alanları eksik / format hatalı |

---

## 7. İletişim

Onboarding, secret rotation, prod allow-list: `dev@arkingroup.com`.

Üretim credential'ları **email'le gönderilmez**; secure channel (1Password share, signal vb.) kullanılır.
