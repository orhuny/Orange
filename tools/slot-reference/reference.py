"""Independent reference implementation of Orange's tumbling slot maths (Sugar Orchard).

Written from the rules in SlotVerifier::describe() / app/Domain/Game/Engines/Slot/Maths/SpinResolver.php's
docblock, not from the PHP code, so the golden vectors it produces can catch mistakes in either.
Not shipped; run it to regenerate tests/Fixtures/slot/sugar-orchard-golden.json:

    python -I tools/slot-reference/reference.py > tests/Fixtures/slot/sugar-orchard-golden.json
"""

import hashlib
import hmac
import json
import struct
import sys


class RandomStream:
    """block_k = HMAC_SHA256(key=server_seed, msg="{client_seed}:{nonce}:{k}"); u32 big-endian in order."""

    def __init__(self, server_seed, client_seed, nonce):
        self.key = server_seed.encode()
        self.prefix = f"{client_seed}:{nonce}:"
        self.block = 0
        self.buf = b""

    def u32(self):
        if len(self.buf) < 4:
            self.buf += hmac.new(self.key, (self.prefix + str(self.block)).encode(), hashlib.sha256).digest()
            self.block += 1
        value = struct.unpack(">I", self.buf[:4])[0]
        self.buf = self.buf[4:]
        return value

    def next_int(self, n):
        limit = (2 ** 32 // n) * n
        while True:
            value = self.u32()
            if value < limit:
                return value % n


def pick(table, r):
    """table: list of (value, weight); r in [0, total)."""
    upto = 0
    for value, weight in table:
        upto += weight
        if r < upto:
            return value
    raise ValueError(r)


def draw(table, rng):
    return pick(table, rng.next_int(sum(w for _, w in table)))


# --- Sugar Orchard v1 -------------------------------------------------------------------------

LETTERS = "ABCDEFGHISM"
PAY = {"A": (1500, 3750, 7500), "B": (750, 1500, 3750), "C": (450, 1050, 2700), "D": (300, 750, 1800),
       "E": (240, 480, 1500), "F": (190, 320, 1200), "G": (160, 240, 750), "H": (100, 190, 600), "I": (65, 140, 375)}
SCATTER_PAY = ((6, 6000), (5, 800), (4, 250))
COLS, ROWS, CAP, MAX_FREE = 6, 5, 500_000, 100
FREE = list(zip(LETTERS, (38, 52, 68, 85, 116, 134, 150, 162, 176, 12, 100)))
MULTS_FROM_3 = [(3, 175), (4, 120), (5, 100), (6, 80), (8, 65), (10, 50), (12, 38), (15, 28), (20, 20), (25, 14), (50, 6), (100, 3)]
BUY_SCATTERS = [(4, 80), (5, 16), (6, 4)]
VARIANTS = {
    "v1-9650": ((454, 604, 754, 904, 1117, 1277, 1397, 1497, 1617, 185), 2185, 317),
    "v1-9550": ((456, 606, 756, 906, 1115, 1275, 1395, 1495, 1615, 185), 2183, 335),
    "v1-9450": ((459, 609, 759, 909, 1112, 1272, 1392, 1492, 1612, 185), 2188, 352),
}


def tables(variant):
    base_w, ante_s, twos = VARIANTS[variant]
    base = list(zip(LETTERS[:10], base_w))
    ante = [(s, w * 10) for s, w in base[:9]] + [("S", ante_s)]
    buy = base[:9]
    return {"base": base, "ante": ante, "buy": buy, "free": FREE, "mults": [(2, twos)] + MULTS_FROM_3}


def x(h):
    return f"{h // 100}.{h % 100:02d}"


def pay(symbol, count):
    a, b, c = PAY[symbol]
    return c if count >= 12 else b if count >= 10 else a if count >= 8 else 0


def scatter_pay(count):
    for at, amount in SCATTER_PAY:
        if count >= at:
            return amount
    return 0


def new_cell(table, mults, rng):
    symbol = draw(table, rng)
    return symbol, (draw(mults, rng) if symbol == "M" else 0)


def tumble(cols, table, mults, rng):
    """cols: list of columns, each a list of (symbol, value) top to bottom."""
    record = {"grid": "".join(s for col in cols for s, _ in col),
              "mults": [v for col in cols for s, v in col if s == "M"], "tumbles": []}
    win = 0
    while True:
        counts = {}
        for col in cols:
            for s, _ in col:
                counts[s] = counts.get(s, 0) + 1
        wins = [(s, counts[s], pay(s, counts[s])) for s in "ABCDEFGHI" if counts.get(s, 0) >= 8]
        if not wins:
            return win, counts.get("S", 0), sum(v for col in cols for _, v in col), record
        win += sum(p for _, _, p in wins)
        gone = {s for s, _, _ in wins}
        fill, fill_mults, new_cols = [], [], []
        for col in cols:
            kept = [c for c in col if c[0] not in gone]
            added = [new_cell(table, mults, rng) for _ in range(ROWS - len(kept))]
            fill.append("".join(s for s, _ in added))
            fill_mults += [v for s, v in added if s == "M"]
            new_cols.append(added + kept)
        cols = new_cols
        record["tumbles"].append({"wins": [{"symbol": s, "count": c, "win_x": x(p)} for s, c, p in wins],
                                  "fill": fill, "fill_mults": fill_mults})


def fresh(table, mults, rng):
    return [[new_cell(table, mults, rng) for _ in range(ROWS)] for _ in range(COLS)]


def bought(t, rng):
    count = draw(BUY_SCATTERS, rng)
    cells = list(range(COLS * ROWS))
    for i in range(count):
        j = i + rng.next_int(COLS * ROWS - i)
        cells[i], cells[j] = cells[j], cells[i]
    scatters = set(cells[:count])
    flat = [("S", 0) if c in scatters else (draw(t["buy"], rng), 0) for c in range(COLS * ROWS)]
    return [flat[c * ROWS:(c + 1) * ROWS] for c in range(COLS)]


def play(variant, mode, rng):
    t = tables(variant)
    table = t["buy"] if mode == "buy" else t[mode]
    cols = bought(t, rng) if mode == "buy" else fresh(table, t["mults"], rng)
    win, scatters, _, record = tumble(cols, table, t["mults"], rng)
    sw = scatter_pay(scatters)
    base_win = min(win + sw, CAP)
    maxed = base_win >= CAP
    awarded = 10 if not maxed and scatters >= 4 else 0
    spins = [dict(record, scatter_win_x=x(sw), win_x=x(base_win), free_spins_awarded=awarded)]
    total, free_win, played, left = base_win, 0, 0, awarded
    while left > 0 and not maxed and played < MAX_FREE:
        win, scatters, mult, record = tumble(fresh(t["free"], t["mults"], rng), t["free"], t["mults"], rng)
        spin_win = win * mult if win > 0 and mult > 0 else win
        if total + spin_win >= CAP:
            spin_win, maxed = CAP - total, True
        total += spin_win
        free_win += spin_win
        retrigger = 5 if scatters >= 3 else 0
        left += retrigger - 1
        played += 1
        spins.append(dict(record, multiplier=mult if win > 0 else 0, win_x=x(spin_win), retrigger=retrigger))
    return {"model": "sugar-orchard", "variant": variant, "mode": mode, "spins": spins, "base_win_x": x(base_win),
            "free_spins": played, "free_spins_win_x": x(free_win), "total_win_x": x(total), "max_win_reached": maxed}


CASES = [
    ("golden-seed-0001", "player-a", n, "v1-9650", "base") for n in range(0, 40)
] + [
    ("golden-seed-0002", "player-b", n, v, "ante") for n in range(0, 10) for v in VARIANTS
] + [
    ("golden-seed-0003", "player-c", n, v, "buy") for n in range(0, 4) for v in VARIANTS
]

if __name__ == "__main__":
    out = []
    for server, client, nonce, variant, mode in CASES:
        out.append({"server_seed": server, "client_seed": client, "nonce": nonce, "variant": variant, "mode": mode,
                    "outcome": play(variant, mode, RandomStream(server, client, nonce))})
    json.dump(out, sys.stdout, separators=(",", ":"))
    sys.stdout.write("\n")
