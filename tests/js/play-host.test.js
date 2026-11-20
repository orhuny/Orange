import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { startPlayHost } from '../../public/js/orange/play-host.js';

/** The game frame: records what the host posts to it. */
function fakeFrame() {
    return { src: '', contentWindow: { posted: [], postMessage(data, origin) { this.posted.push({ data, origin }); } } };
}

let frame;
let fetchMock;
let expired;

function json(status, body) {
    return Promise.resolve({ ok: status >= 200 && status < 300, status, json: () => Promise.resolve(body) });
}

function start(bearer = 'ps_test') {
    return startPlayHost({
        gameCode: 'crash',
        frameUrl: 'https://orange.example/crash',
        bearer,
        storageKey: 'orange.play.crash',
        outerOrigins: ['https://op.example'],
        playApi: 'https://orange.example/play-api',
        frame,
        onExpired: expired,
    });
}

/** Deliver a game intent straight to the host bridge's listener, as from the frame. */
async function intent(type, payload = {}) {
    const event = new MessageEvent('message', { data: { type, payload }, origin: window.location.origin });
    Object.defineProperty(event, 'source', { value: frame.contentWindow });
    window.dispatchEvent(event);
    await vi.waitFor(() => expect(fetchMock).toHaveBeenCalled());
    await new Promise((r) => setTimeout(r, 0));
}

beforeEach(() => {
    frame = fakeFrame();
    expired = vi.fn();
    // A call a test didn't set up (e.g. a balance check after a cash-out) gets a plain 404.
    fetchMock = vi.fn(() => json(404, {}));
    vi.stubGlobal('fetch', fetchMock);
    sessionStorage.clear();
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('startPlayHost', () => {
    it('keeps the session for reloads, strips the token from the URL, and loads the game', () => {
        history.replaceState(null, '', '/play/crash?token=lt_x');

        start('ps_first');

        expect(sessionStorage.getItem('orange.play.crash')).toBe('ps_first');
        expect(location.search).toBe('');
        expect(frame.src).toBe('https://orange.example/crash');

        frame = fakeFrame();
        start(null);
        expect(frame.src).toBe('https://orange.example/crash');
        expect(expired).not.toHaveBeenCalled();
    });

    it('reports an expired session when there is nothing to resume', () => {
        expect(start(null)).toBeNull();
        expect(expired).toHaveBeenCalled();
    });

    it('answers embed_ready with player_info from the session', async () => {
        fetchMock.mockReturnValueOnce(json(200, { player: { id: '12*3456', currency: 'EUR' }, balance: '98.00' }));
        start();

        await intent('embed_ready');

        const [url, init] = fetchMock.mock.calls[0];
        expect(url).toBe('https://orange.example/play-api/session');
        expect(init.headers.Authorization).toBe('Bearer ps_test');
        expect(frame.contentWindow.posted.at(-1).data).toEqual({
            type: 'player_info',
            payload: { id: '12*3456', username: '12*3456', currency: 'EUR', balance: '98.00', bet_history: true, bet_slots: 2, home: false },
        });
    });

    describe('after a deposit at the casino', () => {
        /** A message from the operator page around the host. */
        function fromOperator(type, payload) {
            const event = new MessageEvent('message', { data: { type, payload }, origin: 'https://op.example' });
            Object.defineProperty(event, 'source', { value: window.parent });
            window.dispatchEvent(event);
        }

        /** /session answers with Orange's last known balance; other calls with `other`. */
        function serve(balance, beforeLaunch, other = () => json(404, {})) {
            fetchMock.mockImplementation((url) => (url.endsWith('/session')
                ? json(200, { player: { id: 'p-1', currency: 'EUR' }, balance, balance_before_launch: beforeLaunch })
                : other(url)));
        }

        const shown = () => frame.contentWindow.posted.filter((p) => ['player_info', 'balance_update'].includes(p.data.type))
            .map((p) => [p.data.type, p.data.payload.balance ?? p.data.payload.player_balance]);

        it('shows the casino\'s own balance until a bet here reports a newer one', async () => {
            serve('3.00', true, () => json(201, { bet_id: 7, amount: '1.00', player_balance: '249.00' }));
            start();
            fromOperator('player_info', { id: 'p-1', currency: 'EUR', balance: '250.00' });

            await intent('embed_ready');
            expect(shown()).toEqual([['player_info', '250.00']]);

            await intent('place_bet', { amount: '1.00', round_uuid: null });
            // The game reloads; Orange's wallet call is the newer figure now, whatever /session says.
            serve('249.00', true);
            await intent('embed_ready');
            expect(shown().at(-1)).toEqual(['player_info', '249.00']);
        });

        it('keeps Orange\'s balance when a wallet call since the launch reported it', async () => {
            serve('98.00', false);
            start();
            fromOperator('player_info', { id: 'p-1', currency: 'EUR', balance: '250.00' });

            await intent('embed_ready');

            expect(shown()).toEqual([['player_info', '98.00']]);
        });

        it('updates the game and the lobby when the casino\'s figure comes after the game started', async () => {
            const onBalance = vi.fn();
            serve('3.00', true);
            startPlayHost({
                gameCode: 'crash', frameUrl: 'https://orange.example/crash', bearer: 'ps_test', storageKey: 'orange.play.crash',
                outerOrigins: ['https://op.example'], playApi: 'https://orange.example/play-api', frame, onExpired: expired, onBalance,
            });

            await intent('embed_ready');
            fromOperator('player_info', { id: 'p-1', currency: 'EUR', balance: '250.00' });

            expect(shown()).toEqual([['player_info', '3.00'], ['balance_update', '250.00']]);
            expect(onBalance).toHaveBeenCalledWith('250.00');
        });

        it('ignores a balance that is not a plain decimal', async () => {
            serve('3.00', true);
            start();
            fromOperator('player_info', { id: 'p-1', currency: 'EUR', balance: '€250' });

            await intent('embed_ready');

            expect(shown()).toEqual([['player_info', '3.00']]);
        });

        it('gives the lobby the newest balance it knows', async () => {
            serve('3.00', true);
            const host = startPlayHost({
                gameCode: null, frameUrl: null, bearer: 'ps_lobby', storageKey: 'orange.lobby',
                outerOrigins: ['https://op.example'], playApi: 'https://orange.example/play-api', frame, onExpired: expired,
            });
            fromOperator('player_info', { id: 'p-1', currency: 'EUR', balance: '250.00' });

            const r = await host.session();

            expect(r.ok).toBe(true);
            expect(host.balance).toBe('250.00');
        });
    });

    it('tells the game the limits its bets are played with', async () => {
        const limits = { min_bet: '0.10000000', max_bet: '50.00000000', max_win: null };
        fetchMock.mockImplementation((url) => (url.endsWith('/games/crash')
            ? json(200, { code: 'crash', rtp: '0.9700', currency: 'EUR', limits })
            : json(200, { player: { id: 'p-1', currency: 'EUR' }, balance: '98.00' })));
        start();

        await intent('embed_ready');

        expect(fetchMock.mock.calls.map(([url]) => url)).toContain('https://orange.example/play-api/games/crash');
        expect(frame.contentWindow.posted.at(-1).data.payload.limits).toEqual(limits);
    });

    it('names the player as the operator does, when the launch gave a name', async () => {
        fetchMock.mockReturnValueOnce(json(200, { player: { id: 'client_x:7', username: 'orhuntest', currency: 'TRY' }, balance: '1049.73' }));
        start();

        await intent('embed_ready');

        expect(frame.contentWindow.posted.at(-1).data.payload).toMatchObject({ id: 'client_x:7', username: 'orhuntest' });
    });

    it('answers bet_history with the player\'s bets on the open game', async () => {
        const bets = [{ bet_id: 4, status: 'won', amount: '10.00000000', multiplier: '2.5000', round_uuid: 'r-1', crash_point: 4.1 }];
        fetchMock.mockReturnValueOnce(json(200, { bets }));
        start();

        await intent('bet_history');

        const [url, init] = fetchMock.mock.calls[0];
        expect(url).toBe('https://orange.example/play-api/games/crash/bets');
        expect(init.method).toBe('GET');
        expect(frame.contentWindow.posted.at(-1).data).toEqual({ type: 'bet_history_result', payload: { success: true, bets } });
    });

    it('tells the game when its bet history could not be loaded', async () => {
        fetchMock.mockReturnValueOnce(json(404, { error: 'unknown_game' }));
        start();

        await intent('bet_history');

        expect(frame.contentWindow.posted.at(-1).data).toEqual({ type: 'bet_history_result', payload: { success: false, error: 'unknown_game' } });
    });

    it('turns place_bet into an idempotent player-API bet and reports the result', async () => {
        fetchMock.mockReturnValueOnce(json(201, { bet_id: 9, round_uuid: 'r-1', amount: '2.00000000', auto_cashout_at: '2.00', player_balance: '96.00' }));
        start();

        await intent('place_bet', { amount: '2.00', round_uuid: 'r-1', auto_cashout_at: 2 });

        const [url, init] = fetchMock.mock.calls[0];
        expect(url).toBe('https://orange.example/play-api/games/crash/bets');
        expect(init.method).toBe('POST');
        expect(init.headers['Idempotency-Key']).toMatch(/^[0-9a-f]{32}$/);
        expect(JSON.parse(init.body)).toEqual({ amount: '2.00', round_uuid: 'r-1', params: { auto_cashout_at: 2 } });
        expect(frame.contentWindow.posted.at(-1).data).toEqual({
            type: 'bet_placed_result',
            payload: {
                success: true, bet_id: 9, round_uuid: 'r-1', amount: '2.00000000', auto_cashout_at: '2.00', player_balance: '96.00',
                balance_pending: false, status: undefined, outcome: null, multiplier: null, payout_amount: null, fairness: null,
            },
        });
    });

    it('passes an instant game\'s own params through and returns its result', async () => {
        const result = { bet_id: 3, status: 'won', amount: '1.00000000', multiplier: '1.9800', payout_amount: '1.98000000',
            outcome: { roll: '23.45', win: true }, fairness: { server_seed_hash: 'h', client_seed: 'c', nonce: 4 } };
        fetchMock.mockReturnValueOnce(json(201, result));
        start();

        await intent('place_bet', { amount: '1.00', params: { target: '49.50', direction: 'under' } });

        expect(JSON.parse(fetchMock.mock.calls[0][1].body).params).toEqual({ target: '49.50', direction: 'under' });
        expect(frame.contentWindow.posted.at(-1).data.payload).toMatchObject({
            success: true, status: 'won', outcome: { roll: '23.45', win: true }, multiplier: '1.9800', payout_amount: '1.98000000',
            fairness: { server_seed_hash: 'h', client_seed: 'c', nonce: 4 },
        });
    });

    it('places bets inside an http casino page, where crypto.randomUUID does not exist', async () => {
        // An https page framed by an http one is not a secure context: no randomUUID there.
        const real = globalThis.crypto;
        vi.stubGlobal('crypto', { getRandomValues: (bytes) => real.getRandomValues(bytes) });
        fetchMock.mockReturnValueOnce(json(201, { bet_id: 9, round_uuid: 'r-1', amount: '2.00000000', player_balance: '96.00' }));
        start();

        await intent('place_bet', { amount: '2.00', round_uuid: 'r-1' });

        expect(fetchMock.mock.calls[0][1].headers['Idempotency-Key']).toMatch(/^[0-9a-f]{32}$/);
        expect(frame.contentWindow.posted.at(-1).data.payload).toMatchObject({ success: true, bet_id: 9 });
    });

    it('tells the game when a bet could not be sent at all', async () => {
        fetchMock.mockReturnValueOnce(Promise.reject(new TypeError('Failed to fetch')));
        start();

        await intent('place_bet', { amount: '2.00', round_uuid: 'r-1' });

        expect(frame.contentWindow.posted.at(-1).data).toEqual({
            type: 'bet_placed_result',
            payload: { success: false, error: 'network_error', round_uuid: 'r-1' },
        });
    });

    it('names the bet slot each result answers, whichever comes back first', async () => {
        let answerFirst;
        fetchMock
            .mockReturnValueOnce(new Promise((resolve) => { answerFirst = resolve; }))
            .mockReturnValueOnce(json(201, { bet_id: 8, round_uuid: 'r-1', amount: '5.00000000' }));
        start();

        await intent('place_bet', { amount: '2.00', round_uuid: 'r-1', slot: 0 });
        await intent('place_bet', { amount: '5.00', round_uuid: 'r-1', slot: 1 });
        answerFirst({ ok: false, status: 402, json: () => Promise.resolve({ error: 'wallet_rejected', reason: 'insufficient_funds' }) });
        await vi.waitFor(() => expect(frame.contentWindow.posted).toHaveLength(2));

        const [second, first] = frame.contentWindow.posted.map((p) => p.data.payload);
        expect(second).toMatchObject({ success: true, bet_id: 8, slot: 1 });
        expect(first).toEqual({ success: false, error: 'insufficient_funds', round_uuid: 'r-1', slot: 0 });
    });

    it('passes the operator\'s refusal reason to the game', async () => {
        fetchMock.mockReturnValueOnce(json(402, { error: 'wallet_rejected', reason: 'insufficient_funds' }));
        start();

        await intent('place_bet', { amount: '500.00' });

        expect(frame.contentWindow.posted.at(-1).data.payload).toEqual({ success: false, error: 'insufficient_funds', round_uuid: null });
    });

    it('turns cashout into a player-API cashout, and a crash into round_crashed', async () => {
        fetchMock.mockReturnValueOnce(json(200, { bet_id: 9, multiplier: 1.85, payout_amount: '3.70000000' }));
        start();
        await intent('cashout', { bet_id: 9 });
        expect(fetchMock.mock.calls[0][0]).toBe('https://orange.example/play-api/bets/9/cashout');
        expect(frame.contentWindow.posted.at(-1).data.payload).toEqual({ success: true, bet_id: 9, multiplier: 1.85, payout: '3.70000000', is_auto: false });

        fetchMock.mockClear();
        fetchMock.mockReturnValueOnce(json(409, { error: 'round_crashed', bet_status: 'lost' }));
        await intent('cashout', { bet_id: 9 });
        expect(frame.contentWindow.posted.at(-1).data.payload).toMatchObject({ success: false, bet_id: 9, error: 'round_crashed' });
    });

    it('reports a bet its auto cash-out already won as cashed out, not as a failed cash-out', async () => {
        fetchMock.mockReturnValueOnce(json(409, {
            error: 'already_settled', bet_status: 'won', cashout: { multiplier: 2, payout_amount: '4.00000000', is_auto: true },
        }));
        start();

        await intent('cashout', { bet_id: 9 });

        expect(frame.contentWindow.posted.at(-1).data.payload).toEqual({ success: true, bet_id: 9, multiplier: 2, payout: '4.00000000', is_auto: true });
    });

    describe('the balance after a win', () => {
        /** /balance answers these, one per call (the last one repeats); bets and cash-outs as given. */
        function wallet(balances, other = {}) {
            const queue = [...balances];
            fetchMock.mockImplementation((url) => {
                if (url.endsWith('/balance')) {
                    return json(200, queue.length > 1 ? queue.shift() : queue[0]);
                }
                if (url.endsWith('/session')) {
                    return json(200, { player: { id: 'p-1', currency: 'EUR' }, balance: '100.00', balance_before_launch: false });
                }
                return other[url.split('/play-api')[1]] ?? json(404, {});
            });
        }

        function host(extra = {}) {
            return startPlayHost({
                gameCode: 'crash', frameUrl: 'https://orange.example/crash', bearer: 'ps_test', storageKey: 'orange.play.crash',
                outerOrigins: [], playApi: 'https://orange.example/play-api', frame, onExpired: expired, balancePollMs: [0, 1, 1, 1], ...extra,
            });
        }

        const updates = () => frame.contentWindow.posted.filter((p) => p.data.type === 'balance_update').map((p) => p.data.payload.balance);
        const balanceCalls = () => fetchMock.mock.calls.filter(([url]) => url.endsWith('/balance')).length;

        it('asks the wallet\'s balance after a cash-out until the win is paid in, and tells the game', async () => {
            const onBalance = vi.fn();
            wallet([
                { balance: '98.00', pending: true },
                { balance: '98.00', pending: true },
                { balance: '101.70', pending: false },
            ], { '/bets/9/cashout': json(200, { bet_id: 9, multiplier: 1.85, payout_amount: '3.70000000', balance_pending: true }) });
            host({ onBalance });
            await intent('embed_ready');

            await intent('cashout', { bet_id: 9 });
            await vi.waitFor(() => expect(updates()).toEqual(['98.00', '101.70']));

            expect(balanceCalls()).toBe(3);
            expect(onBalance).toHaveBeenLastCalledWith('101.70');
        });

        it('asks when the game saw a win it has no balance for (an auto cash-out)', async () => {
            wallet([{ balance: '104.00', pending: false }]);
            host();
            await intent('embed_ready');

            await intent('refresh_balance');

            await vi.waitFor(() => expect(updates()).toEqual(['104.00']));
            expect(balanceCalls()).toBe(1);
        });

        it('gives an instant game the wallet\'s figures with its result, and asks again when its win is pending', async () => {
            wallet([{ balance: '50.98', pending: false }], {
                '/games/crash/bets': json(201, {
                    bet_id: 3, status: 'won', payout_amount: '1.98000000', outcome: { win: true },
                    balance_after_stake: '49.00', player_balance: '49.00', balance_pending: true,
                }),
            });
            host();
            await intent('embed_ready');

            await intent('place_bet', { amount: '1.00', params: {} });

            const result = await vi.waitFor(() => frame.contentWindow.posted.find((p) => p.data.type === 'bet_placed_result').data.payload);
            expect(result).toMatchObject({ balance_after_stake: '49.00', player_balance: '49.00', balance_pending: true });
            await vi.waitFor(() => expect(updates()).toEqual(['50.98']));
        });

        it('asks only once its win is paid in, and not at all when the bet\'s answer had it', async () => {
            wallet([{ balance: '50.98', pending: false }], {
                '/games/crash/bets': json(201, { bet_id: 3, status: 'won', balance_after_stake: '49.00', player_balance: '50.98', balance_pending: false }),
            });
            host();
            await intent('embed_ready');

            await intent('place_bet', { amount: '1.00', params: {} });

            const result = await vi.waitFor(() => frame.contentWindow.posted.find((p) => p.data.type === 'bet_placed_result').data.payload);
            expect(result).toMatchObject({ player_balance: '50.98', balance_pending: false });
            await new Promise((r) => setTimeout(r, 10));
            expect(balanceCalls()).toBe(0);
            expect(updates()).toEqual([]);
        });

        it('stops asking after its last try, even if the win is still pending', async () => {
            // The balance from before the win, which the wallet keeps reporting.
            wallet([{ balance: '100.00', pending: true }]);
            host();
            await intent('embed_ready');

            await intent('refresh_balance');

            await vi.waitFor(() => expect(balanceCalls()).toBe(4));
            await new Promise((r) => setTimeout(r, 20));
            expect(balanceCalls()).toBe(4);
            expect(updates()).toEqual([]);
        });

        it('asks once more when another win comes while it is still asking', async () => {
            wallet([{ balance: '100.00', pending: true }, { balance: '101.00', pending: false }, { balance: '103.00', pending: false }]);
            host();
            await intent('embed_ready');

            await intent('refresh_balance');
            await intent('refresh_balance');

            await vi.waitFor(() => expect(updates()).toEqual(['101.00', '103.00']));
        });
    });

    it('tells a game in a lobby it can go home, and the lobby that the game has its own Home', async () => {
        const ready = vi.fn();
        startPlayHost({
            gameCode: null, frameUrl: null, bearer: 'ps_lobby', storageKey: 'orange.lobby',
            outerOrigins: [], playApi: 'https://orange.example/play-api', frame, onExpired: expired,
            onBackToLobby: vi.fn(), onGameReady: ready,
        });
        fetchMock.mockReturnValueOnce(json(200, { player: { id: 'p-1', currency: 'EUR' }, balance: '5.00' }));

        await intent('embed_ready', { home: true });

        expect(ready).toHaveBeenCalledWith({ home: true });
        expect(frame.contentWindow.posted.at(-1).data.payload).toMatchObject({ home: true });
    });

    it('keeps the lobby fallback until Home can be enabled in the current game', async () => {
        const ready = vi.fn(() => {
            expect(frame.contentWindow.posted.at(-1).data).toMatchObject({ type: 'player_info', payload: { home: true } });
        });
        const host = startPlayHost({
            gameCode: null, frameUrl: null, bearer: 'ps_lobby', storageKey: 'orange.lobby',
            outerOrigins: [], playApi: 'https://orange.example/play-api', frame, onExpired: expired,
            onBackToLobby: vi.fn(), onGameReady: ready,
        });
        const home = { home: true };
        const session = { player: { id: 'p-1', currency: 'TRY' }, balance: '50.00' };
        let sessionResponse = json(503, {});
        fetchMock.mockImplementation((url) => url.endsWith('/session') ? sessionResponse : json(200, {}));
        host.open('sugar-orchard', 'https://orange.example/games/slot');
        await intent('embed_ready', home);
        expect(ready).not.toHaveBeenCalled();

        let finishSession;
        sessionResponse = new Promise((resolve) => { finishSession = resolve; });
        await intent('embed_ready', home);
        host.close();
        host.open('sugar-orchard', 'https://orange.example/games/slot');
        finishSession(json(200, session));
        await new Promise((resolve) => setTimeout(resolve, 0));
        expect(ready).not.toHaveBeenCalled();
        expect(frame.contentWindow.posted).toEqual([]);

        sessionResponse = json(200, session);
        await intent('embed_ready', home);
        expect(ready).toHaveBeenCalledExactlyOnceWith(home);
    });

    it('lets a lobby open games, bet on the open one, and come back', async () => {
        const back = vi.fn();
        const host = startPlayHost({
            gameCode: null, frameUrl: null, bearer: 'ps_lobby', storageKey: 'orange.lobby',
            outerOrigins: [], playApi: 'https://orange.example/play-api', frame, onExpired: expired, onBackToLobby: back,
        });
        expect(frame.src).toBe('');

        host.open('dice', 'https://orange.example/games/dice');
        expect(frame.src).toBe('https://orange.example/games/dice');
        expect(host.gameCode).toBe('dice');

        fetchMock.mockReturnValueOnce(json(201, { bet_id: 1, status: 'lost', outcome: { roll: '90.00', win: false } }));
        await intent('place_bet', { amount: '1.00', params: { target: '50', direction: 'under' } });
        expect(fetchMock.mock.calls[0][0]).toBe('https://orange.example/play-api/games/dice/bets');

        const event = new MessageEvent('message', { data: { type: 'back_to_lobby', payload: {} }, origin: window.location.origin });
        Object.defineProperty(event, 'source', { value: frame.contentWindow });
        window.dispatchEvent(event);
        expect(back).toHaveBeenCalled();

        host.close();
        expect(host.gameCode).toBeNull();
        expect(frame.src).toBe('about:blank');
    });

    it('tells a lobby when the operator page expands or shrinks the game, and passes it on', () => {
        const onFullscreen = vi.fn();
        startPlayHost({
            gameCode: null, frameUrl: null, bearer: 'ps_lobby', storageKey: 'orange.lobby',
            outerOrigins: ['https://op.example'], playApi: 'https://orange.example/play-api', frame, onExpired: expired, onFullscreen,
        });

        for (const active of [true, false]) {
            const event = new MessageEvent('message', { data: { type: 'fullscreen_state', payload: { active } }, origin: 'https://op.example' });
            Object.defineProperty(event, 'source', { value: window.parent });
            window.dispatchEvent(event);
        }

        expect(onFullscreen.mock.calls).toEqual([[true], [false]]);
        expect(frame.contentWindow.posted.filter((p) => p.data.type === 'fullscreen_state').map((p) => p.data.payload.active))
            .toEqual([true, false]);
    });

    it('ends the page when the session is no longer accepted', async () => {
        fetchMock.mockReturnValueOnce(json(401, { error: 'session_expired' }));
        start();

        await intent('embed_ready');

        expect(expired).toHaveBeenCalledWith('expired');
        expect(sessionStorage.getItem('orange.play.crash')).toBeNull();
    });

    describe('idle timeout', () => {
        beforeEach(() => {
            vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'Date'] });
        });

        afterEach(() => {
            vi.useRealTimers();
        });

        /** A lobby whose /session gives this idle time; every other call answers 200. */
        function lobby(idle) {
            fetchMock.mockImplementation((url) => json(200, url.endsWith('/session')
                ? { player: { id: 'p-1', currency: 'EUR' }, balance: '5.00', idle_timeout_seconds: idle }
                : { bets: [] }));
            return startPlayHost({
                gameCode: null, frameUrl: null, bearer: 'ps_lobby', storageKey: 'orange.lobby',
                outerOrigins: ['https://op.example'], playApi: 'https://orange.example/play-api', frame, onExpired: expired,
            });
        }

        it('ends a session the player walked away from, so a reload can\'t bring it back', async () => {
            const host = lobby(900);
            await host.session();

            vi.advanceTimersByTime(899_000);
            expect(expired).not.toHaveBeenCalled();
            vi.advanceTimersByTime(1_000);

            expect(expired).toHaveBeenCalledWith('idle');
            expect(sessionStorage.getItem('orange.lobby')).toBeNull();
            const calls = fetchMock.mock.calls.length;
            expect((await host.api('/lobby')).status).toBe(401);
            expect(fetchMock.mock.calls.length).toBe(calls);
        });

        it('counts the idle time from the last request', async () => {
            const host = lobby(900);
            await host.session();

            vi.advanceTimersByTime(600_000);
            await host.api('/games/dice/bets');
            vi.advanceTimersByTime(600_000);
            expect(expired).not.toHaveBeenCalled();

            vi.advanceTimersByTime(300_000);
            expect(expired).toHaveBeenCalledWith('idle');
        });

        it('checks the clock when a hidden tab comes back, its timers having been held back', async () => {
            const host = lobby(900);
            await host.session();
            Object.defineProperty(document, 'visibilityState', { value: 'visible', configurable: true });

            vi.setSystemTime(Date.now() + 901_000);
            document.dispatchEvent(new Event('visibilitychange'));

            expect(expired).toHaveBeenCalledWith('idle');
            delete document.visibilityState;
        });

        it('never ends a session for being idle when the operator turned it off', async () => {
            const host = lobby(null);
            await host.session();

            vi.advanceTimersByTime(24 * 3600_000);

            expect(expired).not.toHaveBeenCalled();
        });
    });
});
