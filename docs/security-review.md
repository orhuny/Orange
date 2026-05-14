# Orange — Security Review (pre-beta)

Pre-launch security checklist for the closed-beta. Maps each control in
[ARCHITECTURE.md §9](../ARCHITECTURE.md#9-güvenlik) and §17 to its current
implementation status.

> **Status legend:** ✅ implemented · 🟡 partial / future · ❌ not yet

| # | Control | Status | Where it lives |
|---|---|---|---|
| 1 | API key sizes / hashing | 🟡 | `operator_api_keys` table exists; key issuance flow is admin-only and not yet wired into the API. Operator HMAC currently uses `operators.secret_key` (encrypted at rest with `'encrypted'` cast). |
| 2 | Replay attack protection | ✅ | `VerifyOperatorSignature` middleware: `±30s` timestamp window + Redis nonce cache (key `operator:{id}:nonce:{ts}:{sig}`, TTL 60s). |
| 3 | Rate limiting | ❌ | Defer to Nginx/Cloudflare layer. `OPERATOR_API_DEFAULT_RATE_LIMIT_PER_MINUTE` is stubbed in `.env` but not enforced in middleware. |
| 4 | Idempotency | ✅ | `EnforceIdempotency` middleware. `(operator_id, idempotency_key)` cached for `OPERATOR_API_IDEMPOTENCY_TTL_HOURS` (24). Same key + same payload → cached response. Same key + different payload → 409 `idempotency_conflict`. |
| 5 | Transaction locking | ✅ | `RoundOrchestrator::transition` and `CashoutService::cashout` both use `DB::transaction` + `lockForUpdate` on the round row. |
| 6 | Cashout race | ✅ | Atomic `UPDATE bets SET status='won' WHERE id=? AND status='placed'` returning affected-row count. Only one of N concurrent callers wins (verified by `tests/Feature/Game/CashoutServiceTest.php` — 100 concurrent attempts, exactly 1 success). |
| 7 | Wallet manipulation | 🟡 | `wallet_transactions` is double-entry-friendly (debit/credit/rollback enum) and unique on `(operator_id, idempotency_key)`. Daily reconciliation job is **not yet implemented**. |
| 8 | Audit trail | ❌ | `audit_logs` table not yet shipped. Append-only hash-chained audit table planned. |
| 9 | Admin auth | 🟡 | Filament panel at `/admin` is gated by `User::canAccessPanel()` (env=local OR `@orange.local`/`@arkingroup.com` email). 2FA is **not yet enabled**. |
| 10 | DDoS | ❌ | Layer is Nginx + Cloudflare; not part of this codebase. |
| 11 | SQL injection | ✅ | Eloquent + parameterised queries everywhere. No `DB::raw` with user input. CI grep step recommended for Week 11. |
| 12 | JWT session (game client) | ❌ | Game-client launch token flow not yet implemented (Week 8 stub only). |
| 13 | Provider → operator HMAC | ✅ | `OperatorWalletClient::dispatch` signs every wallet callback with the same scheme used inbound. `DeliverWebhookJob` does the same for outbound webhooks. |
| 14 | Operator callback timeout | ✅ | `wallet.callback.timeout_seconds=5`, retry 3× with exp backoff. 4xx is terminal (no retry); 5xx/timeout retries; final failure marks `wallet_transactions.status=failed` and logs. |
| 15 | Webhook DLQ | ✅ | `DeliverWebhookJob` walks `wallet.webhooks.retry_backoff_seconds` ([10, 30, 120, 600, 1800]) up to `max_attempts=5`, then sets `webhook.status=dlq`. Admin can re-queue from `/admin/webhooks`. |
| 16 | Provably fair | ✅ | `ProvablyFairService` (bustabit-style HMAC-SHA256, ~3% house edge); seed lifecycle in `SeedRotator`; public verify at `POST /v1/fairness/verify`. |
| 17 | Float arithmetic | ✅ | All money fields are `decimal(18,8)`; payout multiplications use `bcmul`/`bcadd`. No `float` math in wallet path. |

## Manual review checklist (before beta)

- [ ] Run `vendor/bin/pint --test` and `vendor/bin/phpstan analyse` in CI
- [ ] Run `npm audit` and `composer audit` for known CVEs
- [ ] OWASP ZAP scan against the `/admin` panel and `/v1/*` API
- [ ] HMAC signature fuzzer:
    - missing headers → 401 `missing_signature_headers`
    - timestamp drift > 30s → 401 `timestamp_outside_window`
    - bit-flip in body → 401 `signature_mismatch`
    - replayed (operator, ts, sig) → 401 `replay_detected`
- [ ] Concurrency soak: 1k VUs × 5min via `tests/Load/k6-bet-cashout.js`,
      assert no double-cashouts and `wallet_transactions` idempotency
      uniques never collide
- [ ] Verify `secret_key` columns are encrypted in dump (`pg_dump | grep`)
- [ ] Confirm no `.env` / `auth.json` is committed
- [ ] Operator onboarding doc: secret rotation procedure, IP whitelist
      semantics, callback signature verification snippet (PHP / Node /
      Python)

## Pre-license blockers

These must close before turning on real-money traffic:

1. **Lisans** (Curaçao / Anjouan / MGA) — see ARCHITECTURE.md §2
2. **RNG sertifikasyonu** (iTech Labs / GLI) — `random_bytes(32)` is the
   only RNG source; need a labrun against `ProvablyFairService`
3. **Audit logs** (control #8) — append-only hash-chained `audit_logs`
4. **Admin 2FA** (control #9)
5. **Daily wallet reconciliation** (control #7)
6. **Game-client JWT launch flow** (control #12)
