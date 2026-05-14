// Orange — k6 load test
//
// Simulates 1k concurrent virtual users placing a bet and cashing out.
// Target: p95 latency < 100ms per ARCHITECTURE.md §11.
//
// Usage:
//   $env:K6_OPERATOR_ID = '<uuid>';
//   $env:K6_OPERATOR_SECRET = '<plaintext shared secret>';
//   $env:K6_ROUND_UUID = '<active round uuid>';
//   k6 run tests/Load/k6-bet-cashout.js
//
// Generate operator+seed via the admin panel first; export round_uuid via
// `docker compose exec orange-postgres psql -U orange -d orange -c "
//   SELECT round_uuid FROM game_rounds WHERE state='betting' LIMIT 1;"`.

import http from 'k6/http';
import crypto from 'k6/crypto';
import { check, sleep } from 'k6';
import { uuidv4 } from 'https://jslib.k6.io/k6-utils/1.4.0/index.js';

const BASE = __ENV.K6_BASE_URL || 'http://localhost:8000';
const OPERATOR_ID = __ENV.K6_OPERATOR_ID;
const OPERATOR_SECRET = __ENV.K6_OPERATOR_SECRET;
const ROUND_UUID = __ENV.K6_ROUND_UUID;

if (!OPERATOR_ID || !OPERATOR_SECRET || !ROUND_UUID) {
    throw new Error('K6_OPERATOR_ID, K6_OPERATOR_SECRET, K6_ROUND_UUID must be set');
}

export const options = {
    scenarios: {
        bet_then_cashout: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '30s', target: 200 },
                { duration: '60s', target: 1000 },
                { duration: '30s', target: 0 },
            ],
        },
    },
    thresholds: {
        http_req_duration: ['p(95)<100'],
        checks: ['rate>0.99'],
    },
};

function sign(method, path, body, timestamp) {
    const message = `${method.toUpperCase()}\n${path}\n${body}\n${timestamp}`;
    return crypto.hmac('sha256', OPERATOR_SECRET, message, 'hex');
}

function signedHeaders(method, path, body, idempotencyKey) {
    const timestamp = Math.floor(Date.now() / 1000);
    return {
        'X-Operator-Id': OPERATOR_ID,
        'X-Timestamp': String(timestamp),
        'X-Signature': sign(method, path, body, timestamp),
        'Idempotency-Key': idempotencyKey,
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    };
}

export default function () {
    const externalPlayerId = `k6-${uuidv4()}`;
    const betKey = `bet-${uuidv4()}`;

    const betBody = JSON.stringify({
        round_uuid: ROUND_UUID,
        external_player_id: externalPlayerId,
        amount: '1.00',
        currency: 'EUR',
        auto_cashout_at: 1.50,
    });

    const betRes = http.post(`${BASE}/v1/bets`, betBody, {
        headers: signedHeaders('POST', '/v1/bets', betBody, betKey),
    });

    check(betRes, {
        'bet 201 or 409 betting_closed': (r) => r.status === 201 || r.status === 409,
    });

    if (betRes.status !== 201) {
        return;
    }

    const betId = betRes.json('bet_id');
    sleep(0.5);

    const cashoutKey = `cash-${uuidv4()}`;
    const cashoutPath = `/v1/bets/${betId}/cashout`;
    const cashoutRes = http.post(`${BASE}${cashoutPath}`, '[]', {
        headers: signedHeaders('POST', cashoutPath, '[]', cashoutKey),
    });

    check(cashoutRes, {
        'cashout 200 / 409 race-loss': (r) => r.status === 200 || r.status === 409,
    });
}
