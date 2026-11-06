/**
 * Orange's host page for direct play: embeds a game frame and acts as its parent. The
 * game's intents (place_bet, cashout) become calls to the player API, authenticated by
 * the play session; the answers go back to the game as the usual bridge messages, so a
 * game page works the same whether an operator or Orange hosts it. Fullscreen and
 * navigation messages pass through to the operator page around us.
 */
import { createGameBridge, createHostBridge, FROM_GAME } from './bridge.js';

/**
 * @param {{ gameCode: string|null, frameUrl: string|null, bearer: string|null, storageKey: string,
 *           outerOrigins: string[], playApi: string, frame: HTMLIFrameElement,
 *           onExpired: () => void, onBackToLobby?: () => void,
 *           onFullscreen?: (active: boolean) => void }} config
 *        gameCode/frameUrl null: a lobby, which opens games with open(code, url).
 */
export function startPlayHost(config) {
    let gameCode = config.gameCode;

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

    /**
     * 32 random hex characters. Not crypto.randomUUID(): browsers only offer it in secure
     * contexts, and this page isn't one inside an http casino page, even though it is https.
     */
    function idempotencyKey() {
        return Array.from(crypto.getRandomValues(new Uint8Array(16)), (b) => b.toString(16).padStart(2, '0')).join('');
    }

    async function api(path, { method = 'GET', body, idempotent = false } = {}) {
        const headers = { Accept: 'application/json', Authorization: `Bearer ${token}` };
        if (body !== undefined) {
            headers['Content-Type'] = 'application/json';
        }
        if (idempotent) {
            headers['Idempotency-Key'] = idempotencyKey();
        }
        let response;
        try {
            response = await fetch(config.playApi + path, {
                method,
                headers,
                body: body === undefined ? undefined : JSON.stringify(body),
            });
        } catch (_) {
            // No answer at all: the game still gets one (a failed bet or cashout), never silence.
            return { ok: false, status: 0, data: { error: 'network_error' } };
        }
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
            if (type === 'fullscreen_state') {
                config.onFullscreen?.(!!payload?.active);
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
                        // We answer bet_history, so the game may offer its bet history. Only
                        // if the bridge we got knows it: a browser that ignores the import map
                        // can still pair this script with an old cached bridge, which drops it.
                        bet_history: FROM_GAME.includes('bet_history'),
                    });
                    break;
                }
                case 'bet_history': {
                    if (!gameCode) {
                        return;
                    }
                    const r = await api(`/games/${encodeURIComponent(gameCode)}/bets`);
                    inner.send('bet_history_result', r.ok
                        ? { success: true, bets: r.data.bets ?? [] }
                        : { success: false, error: r.data.error || `http_${r.status}` });
                    break;
                }
                case 'place_bet': {
                    if (!gameCode) {
                        return;
                    }
                    const r = await api(`/games/${encodeURIComponent(gameCode)}/bets`, {
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
                case 'back_to_lobby':
                    // In a lobby we go back ourselves; the operator page is told either way.
                    config.onBackToLobby?.();
                    outer.send(type, payload);
                    break;
                case 'enter_fullscreen':
                case 'exit_fullscreen':
                case 'launch_game':
                    outer.send(type, payload);
                    break;
            }
        },
    });

    outer.ready();
    if (config.frameUrl) {
        config.frame.src = config.frameUrl;
    }

    return {
        outer,
        inner,
        api,
        /** Lobby: load a game into the frame; its bets go to that game from now on. */
        open(code, frameUrl) {
            gameCode = code;
            config.frame.src = frameUrl;
            outer.send('launch_game', { code });
        },
        /** Lobby: unload the game. */
        close() {
            gameCode = null;
            config.frame.src = 'about:blank';
        },
        get gameCode() {
            return gameCode;
        },
    };
}
