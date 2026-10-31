/**
 * Orange postMessage bridge, shared by every game page and by Orange's host pages
 * (/play/{code}, /lobby). The message names are the contract with operators
 * (docs/operator-integration.md §6) and must not change; new ones are only added.
 *
 * A plain ES module (no build step), so pages load it directly and tests import it as is.
 */

/** Messages a game sends to the page that embeds it. */
export const FROM_GAME = Object.freeze([
    'embed_ready',
    'place_bet',
    'cashout',
    'enter_fullscreen',
    'exit_fullscreen',
    'launch_game',
    'back_to_lobby',
]);

/** Messages the embedding page sends to a game. */
export const TO_GAME = Object.freeze([
    'player_info',
    'bet_placed_result',
    'cashout_result',
    'fullscreen_state',
    'balance_update',
    'round_state',
    'auto_cashout_fired',
]);

function isMessage(data, names) {
    return data !== null && typeof data === 'object' && typeof data.type === 'string' && names.includes(data.type);
}

/**
 * The game side: talks to the page that embeds it (an operator page, or an Orange host
 * page). Only allow-listed origins are heard, and the first one that speaks becomes the
 * only origin messages are sent to. `embed_ready` is the one beacon sent to '*', because
 * the parent's origin is not known yet; the parent must check the game's origin itself.
 *
 * @param {{ allowedOrigins: string[], onMessage: (type: string, payload: object) => void,
 *           parent?: Window, self?: Window }} options
 */
export function createGameBridge({ allowedOrigins, onMessage, parent = window.parent, self = window }) {
    const allowed = new Set(allowedOrigins);
    let parentOrigin = null;

    const listener = (event) => {
        if (event.source !== parent || !allowed.has(event.origin) || !isMessage(event.data, TO_GAME)) {
            return;
        }
        if (parentOrigin === null) {
            parentOrigin = event.origin;
        }
        if (event.origin === parentOrigin) {
            onMessage(event.data.type, event.data.payload ?? {});
        }
    };
    self.addEventListener('message', listener);

    return {
        get parentOrigin() {
            return parentOrigin;
        },
        /** Announce the game; the parent answers with player_info. */
        ready() {
            if (parent && parent !== self) {
                parent.postMessage({ type: 'embed_ready' }, '*');
            }
        },
        /** @returns {boolean} false while no parent has spoken yet */
        send(type, payload = {}) {
            if (!FROM_GAME.includes(type)) {
                throw new Error(`Unknown game message: ${type}`);
            }
            if (parentOrigin === null || !parent || parent === self) {
                return false;
            }
            parent.postMessage({ type, payload }, parentOrigin);
            return true;
        },
        destroy() {
            self.removeEventListener('message', listener);
        },
    };
}

/**
 * The host side: an Orange page that embeds a game frame and answers its intents.
 * Only messages from that frame and from the given origin (Orange's own) are heard.
 *
 * @param {{ frame: HTMLIFrameElement, onIntent: (type: string, payload: object) => void,
 *           origin?: string, self?: Window }} options
 */
export function createHostBridge({ frame, onIntent, origin = window.location.origin, self = window }) {
    const listener = (event) => {
        if (event.source !== frame.contentWindow || event.origin !== origin || !isMessage(event.data, FROM_GAME)) {
            return;
        }
        onIntent(event.data.type, event.data.payload ?? {});
    };
    self.addEventListener('message', listener);

    return {
        send(type, payload = {}) {
            if (!TO_GAME.includes(type)) {
                throw new Error(`Unknown host message: ${type}`);
            }
            frame.contentWindow?.postMessage({ type, payload }, origin);
        },
        destroy() {
            self.removeEventListener('message', listener);
        },
    };
}
