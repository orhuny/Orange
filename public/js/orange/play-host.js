/**
 * Orange's host page for direct play: embeds a game frame and acts as its parent. The
 * game's intents (place_bet, cashout) become calls to the player API, authenticated by
 * the play session; the answers go back to the game as the usual bridge messages, so a
 * game page works the same whether an operator or Orange hosts it. Fullscreen and
 * navigation messages pass through to the operator page around us.
 *
 * The balance: the operator's wallet owns it, so the game is only ever shown a figure that
 * wallet reported (balance_after of its newest answer), never one worked out here. After a
 * win whose payment is still on its way, the page asks GET /balance until the wallet has
 * answered for it, and sends the game each new figure as balance_update.
 */
import { createGameBridge, createHostBridge, FROM_GAME } from './bridge.js';

/** A balance as a plain decimal string ("12.50"), or null for anything else. */
function decimal(value) {
    if (typeof value !== 'string' && typeof value !== 'number') {
        return null;
    }
    const s = String(value).trim();
    return /^-?\d+(\.\d+)?$/.test(s) ? s : null;
}

/**
 * @param {{ gameCode: string|null, frameUrl: string|null, bearer: string|null, storageKey: string,
 *           outerOrigins: string[], playApi: string, frame: HTMLIFrameElement,
 *           onExpired: (reason?: 'idle' | 'expired') => void, onBackToLobby?: () => void,
 *           onFullscreen?: (active: boolean) => void,
 *           onBalance?: (balance: string) => void,
 *           balancePollMs?: number[],
 *           onGameReady?: (payload: { home?: boolean }) => void }} config
 *        onGameReady: the game announced itself and the host sent player_info.
 *        onBalance: the balance changed after session() had answered (a bet, a win paid in,
 *        the operator page's own figure).
 *        balancePollMs: the waits between GET /balance calls while a win is still pending.
 *        onExpired: the session is over, 'idle' when the player was away for the idle time.
 *        gameCode/frameUrl null: a lobby, which opens games with open(code, url).
 */
export function startPlayHost(config) {
    let gameCode = config.gameCode;
    let frameLoad = 0;

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
        config.onExpired('expired');
        return null;
    }

    /**
     * 32 random hex characters. Not crypto.randomUUID(): browsers only offer it in secure
     * contexts, and this page isn't one inside an http casino page, even though it is https.
     */
    function idempotencyKey() {
        return Array.from(crypto.getRandomValues(new Uint8Array(16)), (b) => b.toString(16).padStart(2, '0')).join('');
    }

    // ---- Idle timeout ---------------------------------------------------------------------
    // The session ends after the operator's idle time (/session: idle_timeout_seconds) without
    // a player API request: a bet, opening a game, the bet history. The server ends it too,
    // within a minute after; the page does it on time, even if the player never comes back.
    let idleMs = null;
    let lastRequest = Date.now();
    let idleTimer = 0;
    let ended = false;

    function armIdle() {
        clearTimeout(idleTimer);
        if (idleMs !== null && !ended) {
            idleTimer = setTimeout(checkIdle, Math.max(1000, lastRequest + idleMs - Date.now()));
        }
    }

    // Timers are throttled in a hidden tab: the clock decides, and a tab coming back checks.
    function checkIdle() {
        if (idleMs === null || ended) {
            return;
        }
        if (Date.now() - lastRequest >= idleMs) {
            expire('idle');
        } else {
            armIdle();
        }
    }
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            checkIdle();
        }
    });

    /** The session is over ('idle', or 'expired' when the server no longer accepts it). */
    function expire(reason) {
        if (ended) {
            return;
        }
        ended = true;
        clearTimeout(idleTimer);
        // A reload must not bring it back.
        try {
            sessionStorage.removeItem(config.storageKey);
        } catch (_) { /* storage blocked */ }
        outer.send('session_expired', { reason });
        config.onExpired(reason);
    }

    async function api(path, { method = 'GET', body, idempotent = false } = {}) {
        if (ended) {
            return { ok: false, status: 401, data: { error: 'session_expired' } };
        }
        lastRequest = Date.now();
        armIdle();
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
            expire('expired');
        }
        return { ok: response.ok, status: response.status, data };
    }

    // The newest balance this page knows: what the game and the lobby show.
    let balance = null;
    // Orange learns the balance only from its wallet calls, so after a deposit at the casino
    // its figure is old. The operator page knows better: its player_info (an answer to our
    // embed_ready) or balance_update carries the casino's own figure. That one is shown until
    // a wallet call in this session reports a newer one (/session: balance_before_launch).
    let operatorBalance = null;
    let walletSinceLaunch = null; // null until /session answers; true once a bet went through here
    let gameShown = false; // the open game has had its player_info

    /** What Orange says the wallet reported (/session, /balance) → the figure to show. */
    function fromWallet(reported, beforeLaunch) {
        walletSinceLaunch = walletSinceLaunch === true || beforeLaunch !== true;
        return !walletSinceLaunch && operatorBalance !== null ? operatorBalance : (decimal(reported) ?? null);
    }

    /** A newer figure: the open game and the lobby show it. */
    function show(next) {
        if (next === null || next === balance) {
            return;
        }
        balance = next;
        if (gameShown) {
            inner.send('balance_update', { balance });
        }
        config.onBalance?.(balance);
    }

    /** GET /session; `balance` is then the newest figure (the operator's or Orange's). */
    async function session() {
        const r = await api('/session');
        if (r.ok) {
            balance = fromWallet(r.data.balance, r.data.balance_before_launch);
            const idle = Number(r.data.idle_timeout_seconds);
            idleMs = idle > 0 ? idle * 1000 : null;
            armIdle();
            if (r.data.balance_pending === true) {
                refreshBalance();
            }
        }
        return r;
    }

    // ---- Balance after a win --------------------------------------------------------------
    // A win the wallet hasn't answered for yet (a crash cash-out is paid from the queue) isn't
    // in its balance yet: ask GET /balance, at growing intervals, until it is. One loop at a
    // time; a request during a loop runs it once more after, so no win is missed.
    const pollMs = config.balancePollMs ?? [0, 400, 800, 1600, 3200, 6400];
    let polling = false;
    let pollAgain = false;

    async function refreshBalance() {
        if (polling) {
            pollAgain = true;
            return;
        }
        polling = true;
        try {
            do {
                pollAgain = false;
                for (const wait of pollMs) {
                    if (wait > 0) {
                        await new Promise((resolve) => setTimeout(resolve, wait));
                    }
                    const r = await api('/balance');
                    if (!r.ok) {
                        return;
                    }
                    show(fromWallet(r.data.balance, r.data.before_launch));
                    if (r.data.pending !== true) {
                        break;
                    }
                }
            } while (pollAgain && !ended);
        } finally {
            polling = false;
        }
    }

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
            if (type === 'player_info' || type === 'balance_update') {
                const reported = decimal(payload?.balance);
                if (reported === null) {
                    return;
                }
                operatorBalance = reported;
                // It came after /session had answered: show it now, unless Orange's is newer.
                if (walletSinceLaunch === false) {
                    balance = reported;
                    if (type === 'player_info' && gameShown) {
                        inner.send('balance_update', { balance: reported });
                    }
                    config.onBalance?.(reported);
                }
            }
        },
    });

    const inner = createHostBridge({
        frame: config.frame,
        async onIntent(type, payload) {
            switch (type) {
                case 'embed_ready': {
                    const readyLoad = frameLoad;
                    // The game's rules show the limits the player's bets are played with; without
                    // them the game still starts.
                    const [r, info] = await Promise.all([
                        session(),
                        gameCode ? api(`/games/${encodeURIComponent(gameCode)}`).catch(() => null) : null,
                    ]);
                    if (!r.ok || readyLoad !== frameLoad) {
                        return;
                    }
                    inner.send('player_info', {
                        id: r.data.player.id,
                        // The operator's display name when the launch gave one, else the id.
                        username: r.data.player.username || r.data.player.id,
                        currency: r.data.player.currency,
                        balance: balance ?? '',
                        // We answer bet_history, so the game may offer its bet history. Only
                        // if the bridge we got knows it: a browser that ignores the import map
                        // can still pair this script with an old cached bridge, which drops it.
                        bet_history: FROM_GAME.includes('bet_history'),
                        // Each bet is its own call, so a player may hold two a round (the
                        // game's second bet slot); results name the slot they answer.
                        bet_slots: 2,
                        // A lobby takes the player back (back_to_lobby): the game may offer Home.
                        home: typeof config.onBackToLobby === 'function',
                        // { min_bet, max_bet, max_win }, decimal strings or null (no limit).
                        ...(info?.ok && info.data.limits ? { limits: info.data.limits } : {}),
                    });
                    gameShown = true;
                    config.onGameReady?.(payload);
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
                    // Two bets can be in flight at once and answer out of order: echo the slot.
                    const slot = payload.slot === undefined ? {} : { slot: payload.slot };
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
                        // A wallet call in this session: Orange's figure is the newer one now.
                        walletSinceLaunch = true;
                        // The game shows it with the result (an instant game once the outcome
                        // has played), so it's not pushed as a balance_update as well.
                        balance = decimal(r.data.player_balance) ?? balance;
                        config.onBalance?.(balance);
                        const pending = r.data.balance_pending === true;
                        inner.send('bet_placed_result', {
                            success: true,
                            bet_id: r.data.bet_id,
                            round_uuid: r.data.round_uuid ?? null,
                            amount: r.data.amount,
                            auto_cashout_at: r.data.auto_cashout_at ?? null,
                            // The wallet's balance after this bet: an instant game's win is in
                            // it, unless balance_pending (then a balance_update follows).
                            player_balance: balance,
                            balance_pending: pending,
                            // Instant games: the wallet's balance right after the stake, which
                            // they show while the outcome plays.
                            ...(r.data.balance_after_stake ? { balance_after_stake: r.data.balance_after_stake } : {}),
                            // Instant games resolve at once: their result comes with the bet.
                            status: r.data.status,
                            outcome: r.data.outcome ?? null,
                            multiplier: r.data.multiplier ?? null,
                            payout_amount: r.data.payout_amount ?? null,
                            fairness: r.data.fairness ?? null,
                            ...slot,
                        });
                        if (pending) {
                            refreshBalance();
                        }
                    } else {
                        inner.send('bet_placed_result', {
                            success: false,
                            error: r.data.reason || r.data.error || `http_${r.status}`,
                            round_uuid: payload.round_uuid ?? null,
                            ...slot,
                        });
                    }
                    break;
                }
                case 'cashout': {
                    const r = await api(`/bets/${encodeURIComponent(payload.bet_id)}/cashout`, { method: 'POST', body: {} });
                    // Its auto cash-out got there first: the bet is won all the same.
                    const won = r.status === 409 && r.data.bet_status === 'won' ? r.data.cashout : null;
                    if (r.ok || won) {
                        const cashout = r.ok ? r.data : won;
                        inner.send('cashout_result', {
                            success: true,
                            bet_id: r.ok ? r.data.bet_id : payload.bet_id,
                            multiplier: cashout.multiplier,
                            payout: cashout.payout_amount,
                            is_auto: !!cashout.is_auto,
                        });
                        // The win is paid from the queue: its balance follows as balance_update.
                        refreshBalance();
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
                case 'refresh_balance':
                    // The game saw a win it has no balance for (a crash auto cash-out).
                    refreshBalance();
                    break;
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
        /** GET /session, which also brings `balance` up to date. */
        session,
        /** Ask GET /balance until no win is pending; changes reach the game and onBalance. */
        refreshBalance,
        /** The newest balance this page knows, or null (the lobby shows it). */
        get balance() {
            return balance;
        },
        /** Lobby: load a game into the frame; its bets go to that game from now on. The
         *  operator page is told which game, by code and by the name players see. */
        open(code, frameUrl, name = null) {
            frameLoad++;
            gameShown = false;
            gameCode = code;
            config.frame.src = frameUrl;
            outer.send('launch_game', name ? { code, name } : { code });
        },
        /** Lobby: unload the game. */
        close() {
            frameLoad++;
            gameShown = false;
            gameCode = null;
            config.frame.src = 'about:blank';
        },
        get gameCode() {
            return gameCode;
        },
    };
}
