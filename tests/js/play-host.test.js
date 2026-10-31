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
    fetchMock = vi.fn();
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
            payload: { id: '12*3456', username: '12*3456', currency: 'EUR', balance: '98.00' },
        });
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
                status: undefined, outcome: null, multiplier: null, payout_amount: null, fairness: null,
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
        expect(frame.contentWindow.posted.at(-1).data.payload).toEqual({ success: true, bet_id: 9, multiplier: 1.85, payout: '3.70000000' });

        fetchMock.mockClear();
        fetchMock.mockReturnValueOnce(json(409, { error: 'round_crashed', bet_status: 'lost' }));
        await intent('cashout', { bet_id: 9 });
        expect(frame.contentWindow.posted.at(-1).data.payload).toMatchObject({ success: false, bet_id: 9, error: 'round_crashed' });
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

    it('ends the page when the session is no longer accepted', async () => {
        fetchMock.mockReturnValueOnce(json(401, { error: 'session_expired' }));
        start();

        await intent('embed_ready');

        expect(expired).toHaveBeenCalled();
    });
});
