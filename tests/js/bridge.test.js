import { describe, expect, it, vi } from 'vitest';
import { createGameBridge, createHostBridge, FROM_GAME, TO_GAME } from '../../public/js/orange/bridge.js';

/** A window stand-in: collects listeners and records what is posted to it. */
function fakeWindow() {
    const listeners = new Set();
    return {
        posted: [],
        addEventListener: (type, fn) => type === 'message' && listeners.add(fn),
        removeEventListener: (type, fn) => listeners.delete(fn),
        postMessage(data, targetOrigin) {
            this.posted.push({ data, targetOrigin });
        },
        receive: (event) => listeners.forEach((fn) => fn(event)),
        get listenerCount() {
            return listeners.size;
        },
    };
}

describe('message names', () => {
    it('keep every name the live integrations use', () => {
        for (const name of ['embed_ready', 'place_bet', 'cashout', 'enter_fullscreen', 'exit_fullscreen']) {
            expect(FROM_GAME).toContain(name);
        }
        for (const name of ['player_info', 'bet_placed_result', 'cashout_result', 'fullscreen_state', 'balance_update', 'round_state', 'auto_cashout_fired']) {
            expect(TO_GAME).toContain(name);
        }
        expect(FROM_GAME).toContain('launch_game');
        expect(FROM_GAME).toContain('back_to_lobby');
        expect(FROM_GAME).toContain('bet_history');
        expect(TO_GAME).toContain('bet_history_result');
    });
});

describe('createGameBridge', () => {
    function setup() {
        const self = fakeWindow();
        const parent = fakeWindow();
        const onMessage = vi.fn();
        const bridge = createGameBridge({ allowedOrigins: ['https://op.example', 'https://other.example'], onMessage, parent, self });
        return { self, parent, onMessage, bridge };
    }

    it('announces itself to any origin, and sends nothing until a parent speaks', () => {
        const { parent, bridge } = setup();

        bridge.ready();
        expect(parent.posted).toEqual([{ data: { type: 'embed_ready' }, targetOrigin: '*' }]);
        expect(bridge.send('place_bet', { amount: '1' })).toBe(false);
    });

    it('hears only allow-listed origins from its parent, then locks onto the first', () => {
        const { self, parent, onMessage, bridge } = setup();
        const stranger = fakeWindow();

        self.receive({ source: parent, origin: 'https://evil.example', data: { type: 'player_info', payload: {} } });
        self.receive({ source: stranger, origin: 'https://op.example', data: { type: 'player_info', payload: {} } });
        expect(onMessage).not.toHaveBeenCalled();

        self.receive({ source: parent, origin: 'https://op.example', data: { type: 'player_info', payload: { id: 7 } } });
        self.receive({ source: parent, origin: 'https://other.example', data: { type: 'balance_update', payload: {} } });
        expect(onMessage).toHaveBeenCalledTimes(1);
        expect(onMessage).toHaveBeenCalledWith('player_info', { id: 7 });
        expect(bridge.parentOrigin).toBe('https://op.example');

        bridge.send('place_bet', { amount: '2.00' });
        expect(parent.posted.at(-1)).toEqual({ data: { type: 'place_bet', payload: { amount: '2.00' } }, targetOrigin: 'https://op.example' });
    });

    it('ignores unknown or malformed messages and refuses to send unknown ones', () => {
        const { self, parent, onMessage, bridge } = setup();

        for (const data of [null, 'player_info', { type: 'delete_everything' }, { payload: {} }]) {
            self.receive({ source: parent, origin: 'https://op.example', data });
        }
        expect(onMessage).not.toHaveBeenCalled();
        expect(() => bridge.send('steal_balance')).toThrow();
    });

    it('stops listening when destroyed', () => {
        const { self, bridge } = setup();

        bridge.destroy();
        expect(self.listenerCount).toBe(0);
    });
});

describe('createHostBridge', () => {
    it('hears only its own frame on its own origin, and answers it there', () => {
        const self = fakeWindow();
        const frame = { contentWindow: fakeWindow() };
        const onIntent = vi.fn();
        const host = createHostBridge({ frame, onIntent, origin: 'https://orange.example', self });

        self.receive({ source: fakeWindow(), origin: 'https://orange.example', data: { type: 'place_bet', payload: {} } });
        self.receive({ source: frame.contentWindow, origin: 'https://evil.example', data: { type: 'place_bet', payload: {} } });
        self.receive({ source: frame.contentWindow, origin: 'https://orange.example', data: { type: 'player_info', payload: {} } });
        expect(onIntent).not.toHaveBeenCalled();

        self.receive({ source: frame.contentWindow, origin: 'https://orange.example', data: { type: 'cashout', payload: { bet_id: 5 } } });
        expect(onIntent).toHaveBeenCalledWith('cashout', { bet_id: 5 });

        host.send('cashout_result', { success: true });
        expect(frame.contentWindow.posted).toEqual([
            { data: { type: 'cashout_result', payload: { success: true } }, targetOrigin: 'https://orange.example' },
        ]);
        expect(() => host.send('place_bet')).toThrow();
    });
});
