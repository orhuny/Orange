/**
 * Orange's host page for direct play: embeds a game frame and acts as its parent. The
 * game's intents (place_bet, cashout) become calls to the player API, authenticated by
 * the play session; the answers go back to the game as the usual bridge messages, so a
 * game page works the same whether an operator or Orange hosts it. Fullscreen and
 * navigation messages pass through to the operator page around us.
 */
import { createGameBridge, createHostBridge } from './bridge.js';

/**
 * @param {{ gameCode: string, frameUrl: string, bearer: string|null, storageKey: string,
 *           outerOrigins: string[], playApi: string, frame: HTMLIFrameElement,
 *           onExpired: () => void }} config
 */
export function startPlayHost(config) {
    let token = config.bearer;
    try {
        // The launch token works once: keep the session for reloads of this tab.
        if (token) {
            sessionStorage.setItem(config.storageKey, token);
        } else {
            token = sessionStorage.getItem(config.storageKey);
        }
    } catch (_) { /* storage blocked: reloads will need a new launch */ }
    if (config.bearer) {
        history.replaceState(null, '', location.pathname);
    }
    if (!token) {
        config.onExpired();
        return null;
    }

    async function api(path, { method = 'GET', body, idempotent = false } = {}) {
        const headers = { Accept: 'application/json', Authorization: `Bearer ${token}` };
        if (body !== undefined) {
            headers['Content-Type'] = 'application/json';
        }
        if (idempotent) {
            headers['Idempotency-Key'] = crypto.randomUUID().replaceAll('-', '');
        }
        const response = await fetch(config.playApi + path, {
            method,
            headers,
            body: body === undefined ? undefined : JSON.stringify(body),
        });
        let data = {};
        try {
            data = await response.json();
        } catch (_) { /* empty or non-JSON body */ }
        if (response.status === 401) {
            config.onExpired();
        }
        return { ok: response.ok, status: response.status, data };
    }

    let balance = null;

    const outer = createGameBridge({
        allowedOrigins: config.outerOrigins,
        onMessage(type, payload) {
            // The operator page may still drive fullscreen and push balance updates.
            if (type === 'fullscreen_state' || type === 'balance_update') {
                inner.send(type, payload);
            }
        },
    });

    const inner = createHostBridge({
        frame: config.frame,
        async onIntent(type, payload) {
            switch (type) {
                case 'embed_ready': {
                    const r = await api('/session');
                    if (!r.ok) {
                        return;
                    }
                    balance = r.data.balance;
                    inner.send('player_info', {
                        id: r.data.player.id,
                        username: r.data.player.id,
                        currency: r.data.player.currency,
                        balance: balance ?? '',
                    });
                    break;
                }
                case 'place_bet': {
                    const r = await api(`/games/${encodeURIComponent(config.gameCode)}/bets`, {
                        method: 'POST',
                        idempotent: true,
                        body: {
                            amount: String(payload.amount ?? ''),
                            round_uuid: payload.round_uuid ?? null,
                            // Instant games send their own params (dice: target, direction);
                            // crash sends auto_cashout_at at the top level, as it always has.
                            params: payload.params ?? { auto_cashout_at: payload.auto_cashout_at ?? null },
                        },
                    });
                    if (r.ok) {
                        balance = r.data.player_balance ?? balance;
                        inner.send('bet_placed_result', {
                            success: true,
                            bet_id: r.data.bet_id,
                            round_uuid: r.data.round_uuid ?? null,
                            amount: r.data.amount,
                            auto_cashout_at: r.data.auto_cashout_at ?? null,
                            player_balance: balance,
                            // Instant games resolve at once: their result comes with the bet.
                            status: r.data.status,
                            outcome: r.data.outcome ?? null,
                            multiplier: r.data.multiplier ?? null,
                            payout_amount: r.data.payout_amount ?? null,
                            fairness: r.data.fairness ?? null,
                        });
                    } else {
                        inner.send('bet_placed_result', {
                            success: false,
                            error: r.data.reason || r.data.error || `http_${r.status}`,
                            round_uuid: payload.round_uuid ?? null,
                        });
                    }
                    break;
                }
                case 'cashout': {
                    const r = await api(`/bets/${encodeURIComponent(payload.bet_id)}/cashout`, { method: 'POST', body: {} });
                    if (r.ok) {
                        inner.send('cashout_result', {
                            success: true,
                            bet_id: r.data.bet_id,
                            multiplier: r.data.multiplier,
                            payout: r.data.payout_amount,
                        });
                    } else {
                        inner.send('cashout_result', {
                            success: false,
                            bet_id: payload.bet_id,
                            error: r.data.error || `http_${r.status}`,
                            error_message: r.data.message || '',
                        });
                    }
                    break;
                }
                case 'enter_fullscreen':
                case 'exit_fullscreen':
                case 'launch_game':
                case 'back_to_lobby':
                    outer.send(type, payload);
                    break;
            }
        },
    });

    outer.ready();
    config.frame.src = config.frameUrl;

    return { outer, inner, api };
}
