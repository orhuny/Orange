"""Independent reference implementation of Orange's slot maths: the tumbling models and the
paylines model (Tutti Frutti, LinesResolver.php).

Written from the rules in SlotVerifier::describe() / app/Domain/Game/Engines/Slot/Maths/SpinResolver.php's
docblock, not from the PHP code, so the golden vectors it produces can catch mistakes in either.
Not shipped; run it to regenerate a model's fixture:

    python -I tools/slot-reference/reference.py sugar-orchard > tests/Fixtures/slot/sugar-orchard-golden.json
    python -I tools/slot-reference/reference.py trident-of-poseidon > tests/Fixtures/slot/trident-of-poseidon-golden.json
    python -I tools/slot-reference/reference.py stones-of-the-titan > tests/Fixtures/slot/stones-of-the-titan-golden.json
    python -I tools/slot-reference/reference.py tutti-frutti > tests/Fixtures/slot/tutti-frutti-golden.json
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


LETTERS = "ABCDEFGHISM"
COLS, ROWS, MAX_FREE = 6, 5, 100


# --- Sugar Orchard v1 -------------------------------------------------------------------------

def sugar_orchard():
    free = list(zip(LETTERS, (38, 52, 68, 85, 116, 134, 150, 162, 176, 12, 100)))
    mults_from_3 = [(3, 175), (4, 120), (5, 100), (6, 80), (8, 65), (10, 50), (12, 38), (15, 28), (20, 20), (25, 14), (50, 6), (100, 3)]
    variants = {
        "v1-9650": ((454, 604, 754, 904, 1117, 1277, 1397, 1497, 1617, 185), 2185, 317),
        "v1-9550": ((456, 606, 756, 906, 1115, 1275, 1395, 1495, 1615, 185), 2183, 335),
        "v1-9450": ((459, 609, 759, 909, 1112, 1272, 1392, 1492, 1612, 185), 2188, 352),
    }

    def tables(variant):
        base_w, ante_s, twos = variants[variant]
        base = list(zip(LETTERS[:10], base_w))
        ante = [(s, w * 10) for s, w in base[:9]] + [("S", ante_s)]
        return {"base": base, "ante": ante, "buy": base[:9], "free": free,
                "mults": [(2, twos)] + mults_from_3, "base_mults": None}

    return {
        "code": "sugar-orchard",
        "pay": {"A": (1500, 3750, 7500), "B": (750, 1500, 3750), "C": (450, 1050, 2700), "D": (300, 750, 1800),
                "E": (240, 480, 1500), "F": (190, 320, 1200), "G": (160, 240, 750), "H": (100, 190, 600), "I": (65, 140, 375)},
        "scatter_pay": ((6, 6000), (5, 800), (4, 250)),
        "cap": 500_000, "awarded": 10, "base_multipliers": False, "tide": False,
        "buy_scatters": [(4, 80), (5, 16), (6, 4)],
        "variants": list(variants), "tables": tables,
    }


# --- Trident of Poseidon v1 -------------------------------------------------------------------

def trident_of_poseidon():
    base_w = {"A": 4500, "B": 6000, "C": 7500, "D": 9000, "E": 11000, "F": 12500, "G": 14000, "H": 15000, "I": 16000, "S": 1700}
    free = list(zip(LETTERS, (38, 52, 68, 85, 116, 134, 150, 162, 176, 9, 13)))
    pearls_from_3 = [(3, 300), (4, 220), (5, 180), (6, 140), (8, 110), (10, 85), (12, 60), (15, 45), (20, 32), (25, 22),
                     (50, 9), (100, 4), (250, 2), (500, 1)]
    base_pearls = [(2, 500), (3, 300), (4, 200), (5, 150), (6, 100), (8, 70), (10, 50), (12, 30), (15, 20), (20, 12),
                   (25, 8), (50, 3), (100, 1)]
    variants = {"v1-9650": (650, 610), "v1-9550": (634, 643), "v1-9450": (619, 677)}

    def tables(variant):
        pearl, twos = variants[variant]
        base = [(s, base_w[s]) for s in "ABCDEFGHIS"] + [("M", pearl)]
        ante = [(s, 21000 if s == "S" else w * 10) for s, w in base]
        buy = [(s, w) for s, w in base if s != "S"]
        return {"base": base, "ante": ante, "buy": buy, "free": free,
                "mults": [(2, twos)] + pearls_from_3, "base_mults": base_pearls}

    return {
        "code": "trident-of-poseidon",
        "pay": {"A": (1000, 2500, 5000), "B": (400, 1200, 2500), "C": (300, 800, 1600), "D": (200, 600, 1400),
                "E": (150, 300, 1000), "F": (120, 220, 800), "G": (90, 160, 600), "H": (60, 120, 400), "I": (35, 90, 250)},
        "scatter_pay": ((6, 5000), (5, 1000), (4, 400)),
        "cap": 500_000, "awarded": 15, "base_multipliers": True, "tide": True,
        "buy_scatters": [(4, 80), (5, 16), (6, 4)],
        "variants": list(variants), "tables": tables,
    }


# --- Stones of the Titan v1: Trident's tables, plus the Super Eclipse buy ---------------------

def stones_of_the_titan():
    return dict(trident_of_poseidon(), code="stones-of-the-titan", super_tide=10)


# --- Tutti Frutti v1: paylines ----------------------------------------------------------------
# Written from SlotVerifier::describeLines(): 5 reels x 3 rows, cells drawn reel by reel, top to
# bottom; a wild (W, never on reel 0) draws its value next; 20 lines pay left to right from
# reel 0, times the sum of the wild multipliers in the run (1 when none); scatters anywhere; in
# free spins the amore starts at 1 and every landed wild adds 1 before the spin pays.

def tutti_frutti():
    fruit = [400, 500, 600, 700, 1100, 1200, 1300, 1400, 1500]
    variants = {  # base scatter, base kiss (reels 1-4), bought scatters
        "v1-9650": (215, 430, [(3, 561), (4, 329), (5, 110)]),
        "v1-9550": (215, 421, [(3, 573), (4, 324), (5, 103)]),
        "v1-9450": (215, 411, [(3, 586), (4, 317), (5, 97)]),
    }

    def reel(scatter, kiss, first):
        table = list(zip("ABCDEFGHI", fruit))
        if scatter:
            table.append(("S", scatter))
        if not first:
            table.append(("W", kiss))
        return table

    def tables(variant):
        scatter, kiss, bought = variants[variant]
        return {
            "base": [reel(scatter, kiss, r == 0) for r in range(5)],
            "buy": [reel(0, kiss, r == 0) for r in range(5)],
            "free": [reel(250, 635, r == 0) for r in range(5)],
            "base_wilds": [(1, 80), (2, 12), (3, 6), (5, 2)],
            "free_wilds": [(1, 70), (2, 16), (3, 9), (5, 5)],
            "buy_scatters": bought,
        }

    return {
        "code": "tutti-frutti", "kind": "lines", "reels": 5, "rows": 3, "cap": 500_000,
        "lines": [(1, 1, 1, 1, 1), (0, 0, 0, 0, 0), (2, 2, 2, 2, 2), (0, 1, 2, 1, 0), (2, 1, 0, 1, 2),
                  (0, 0, 1, 2, 2), (2, 2, 1, 0, 0), (1, 0, 0, 0, 1), (1, 2, 2, 2, 1), (0, 1, 1, 1, 0),
                  (2, 1, 1, 1, 2), (1, 0, 1, 2, 1), (1, 2, 1, 0, 1), (0, 1, 0, 1, 0), (2, 1, 2, 1, 2),
                  (1, 1, 0, 1, 1), (1, 1, 2, 1, 1), (0, 0, 2, 0, 0), (2, 2, 0, 2, 2), (0, 2, 2, 2, 0)],
        # x the line bet (the bet / 20)
        "line_pays": {"A": {2: 5, 3: 50, 4: 200, 5: 1000}, "B": {3: 40, 4: 150, 5: 500}, "C": {3: 30, 4: 100, 5: 400},
                      "D": {3: 25, 4: 80, 5: 300}, "E": {3: 15, 4: 40, 5: 150}, "F": {3: 10, 4: 30, 5: 120},
                      "G": {3: 10, 4: 25, 5: 100}, "H": {3: 5, 4: 20, 5: 80}, "I": {3: 5, 4: 15, 5: 60}},
        "scatter_pay": ((5, 5000), (4, 1000), (3, 200)),
        "free_spins": ((5, 15), (4, 12), (3, 10)),
        "variants": list(variants), "tables": tables,
    }


MODELS = {"sugar-orchard": sugar_orchard, "trident-of-poseidon": trident_of_poseidon, "stones-of-the-titan": stones_of_the_titan,
          "tutti-frutti": tutti_frutti}


# --- The rules --------------------------------------------------------------------------------

def x(h):
    return f"{h // 100}.{h % 100:02d}"


def pay(m, symbol, count):
    a, b, c = m["pay"][symbol]
    return c if count >= 12 else b if count >= 10 else a if count >= 8 else 0


def scatter_pay(m, count):
    for at, amount in m["scatter_pay"]:
        if count >= at:
            return amount
    return 0


def new_cell(table, mults, rng):
    symbol = draw(table, rng)
    return symbol, (draw(mults, rng) if symbol == "M" else 0)


def tumble(m, cols, table, mults, rng):
    """cols: list of columns, each a list of (symbol, value) top to bottom."""
    record = {"grid": "".join(s for col in cols for s, _ in col),
              "mults": [v for col in cols for s, v in col if s == "M"], "tumbles": []}
    win = 0
    while True:
        counts = {}
        for col in cols:
            for s, _ in col:
                counts[s] = counts.get(s, 0) + 1
        wins = [(s, counts[s], pay(m, s, counts[s])) for s in "ABCDEFGHI" if counts.get(s, 0) >= 8]
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


def bought(m, t, rng):
    count = draw(m["buy_scatters"], rng)
    cells = list(range(COLS * ROWS))
    for i in range(count):
        j = i + rng.next_int(COLS * ROWS - i)
        cells[i], cells[j] = cells[j], cells[i]
    scatters = set(cells[:count])
    flat = [("S", 0) if c in scatters else new_cell(t["buy"], t["base_mults"], rng) for c in range(COLS * ROWS)]
    return [flat[c * ROWS:(c + 1) * ROWS] for c in range(COLS)]


def play(m, variant, mode, rng, cap=None):
    cap = cap or m["cap"]
    t = m["tables"](variant)
    # A super buy draws exactly like a buy; only its tide starts above 0.
    is_bought = mode in ("buy", "super")
    table = t["buy" if is_bought else mode]
    cols = bought(m, t, rng) if is_bought else fresh(table, t["base_mults"], rng)
    win, scatters, mult, record = tumble(m, cols, table, t["base_mults"], rng)
    base_sum = mult if win > 0 else 0
    line_win = win * base_sum if base_sum > 0 else win
    sw = scatter_pay(m, scatters)
    base_win = min(line_win + sw, cap)
    maxed = base_win >= cap
    awarded = m["awarded"] if not maxed and scatters >= 4 else 0
    first = dict(record)
    if m["base_multipliers"]:
        first["multiplier"] = base_sum
    spins = [dict(first, scatter_win_x=x(sw), win_x=x(base_win), free_spins_awarded=awarded)]
    total, free_win, played, left = base_win, 0, 0, awarded
    tide = m["super_tide"] if mode == "super" else 0
    while left > 0 and not maxed and played < MAX_FREE:
        win, scatters, mult, record = tumble(m, fresh(t["free"], t["mults"], rng), t["free"], t["mults"], rng)
        spin_sum = mult if win > 0 else 0
        tide += spin_sum
        factor = tide if m["tide"] else spin_sum
        spin_win = win * factor if win > 0 and factor > 0 else win
        if total + spin_win >= cap:
            spin_win, maxed = cap - total, True
        total += spin_win
        free_win += spin_win
        retrigger = 5 if scatters >= 3 else 0
        left += retrigger - 1
        played += 1
        spin = dict(record, multiplier=spin_sum, win_x=x(spin_win), retrigger=retrigger)
        if m["tide"]:
            spin["tide"] = tide
        spins.append(spin)
    out = {"model": m["code"], "variant": variant, "mode": mode}
    if mode == "super":
        out["tide_start"] = m["super_tide"]
    return dict(out, spins=spins, base_win_x=x(base_win), free_spins=played, free_spins_win_x=x(free_win),
                total_win_x=x(total), max_win_reached=maxed)


# --- Paylines --------------------------------------------------------------------------------

def screen_of(m, reels, wilds, rng):
    """A fresh screen: a list of (symbol, value), cell = reel * rows + row."""
    out = []
    for r in range(m["reels"]):
        for _ in range(m["rows"]):
            symbol = draw(reels[r], rng)
            out.append((symbol, draw(wilds, rng) if symbol == "W" else 0))
    return out


def bought_screen(m, t, rng):
    cells_n = m["reels"] * m["rows"]
    count = draw(t["buy_scatters"], rng)
    order = list(range(cells_n))
    for i in range(count):
        j = i + rng.next_int(cells_n - i)
        order[i], order[j] = order[j], order[i]
    scatters = set(order[:count])
    out = []
    for c in range(cells_n):
        if c in scatters:
            out.append(("S", 0))
        else:
            symbol = draw(t["buy"][c // m["rows"]], rng)
            out.append((symbol, draw(t["base_wilds"], rng) if symbol == "W" else 0))
    return out


def lines_pay(m, screen):
    """(line win, scatters, wilds, record) of one screen."""
    n_lines = len(m["lines"])
    wins, total = [], 0
    for number, rows in enumerate(m["lines"], start=1):
        cells = [screen[r * m["rows"] + row] for r, row in enumerate(rows)]
        first = cells[0][0]
        if first not in m["line_pays"]:
            continue
        run, mult = 0, 0
        for symbol, value in cells:
            if symbol == first or symbol == "W":
                run += 1
                if symbol == "W" and value > 1:
                    mult += value
            else:
                break
        line_pay = m["line_pays"][first].get(run)
        if not line_pay:
            continue
        amount = line_pay * 100 // n_lines * max(1, mult)
        total += amount
        wins.append({"line": number, "symbol": first, "count": run, "multiplier": max(1, mult), "win_x": x(amount)})
    scatters = sum(1 for s, _ in screen if s == "S")
    wilds = sum(1 for s, _ in screen if s == "W")
    record = {"grid": "".join(s for s, _ in screen), "wilds": [v for s, v in screen if s == "W"],
              "lines": wins, "line_win_x": x(total), "scatters": scatters}
    return total, scatters, wilds, record


def tier_of(tiers, count):
    for at, value in tiers:
        if count >= at:
            return value
    return 0


def play_lines(m, variant, mode, rng, cap=None):
    cap = cap or m["cap"]
    t = m["tables"](variant)
    screen = bought_screen(m, t, rng) if mode == "buy" else screen_of(m, t["base"], t["base_wilds"], rng)
    line_win, scatters, _, record = lines_pay(m, screen)
    sw = tier_of(m["scatter_pay"], scatters)
    base_win = min(line_win + sw, cap)
    maxed = base_win >= cap
    awarded = 0 if maxed else tier_of(m["free_spins"], scatters)
    spins = [dict(record, scatter_win_x=x(sw), win_x=x(base_win), free_spins_awarded=awarded)]
    total, free_win, played, left, amore = base_win, 0, 0, awarded, 1
    while left > 0 and not maxed and played < MAX_FREE:
        line_win, scatters, wilds, record = lines_pay(m, screen_of(m, t["free"], t["free_wilds"], rng))
        amore += wilds
        sw = tier_of(m["scatter_pay"], scatters)
        spin_win = line_win * amore + sw
        if total + spin_win >= cap:
            spin_win, maxed = cap - total, True
        total += spin_win
        free_win += spin_win
        retrigger = 5 if scatters >= 3 else 0
        left += retrigger - 1
        played += 1
        spins.append(dict(record, amore=amore, scatter_win_x=x(sw), win_x=x(spin_win), retrigger=retrigger))
    return {"model": m["code"], "variant": variant, "mode": mode, "spins": spins, "base_win_x": x(base_win),
            "free_spins": played, "free_spins_win_x": x(free_win), "total_win_x": x(total), "max_win_reached": maxed}


def cases(m):
    if m["code"] == "sugar-orchard":
        return [("golden-seed-0001", "player-a", n, "v1-9650", "base", None) for n in range(0, 40)] \
            + [("golden-seed-0002", "player-b", n, v, "ante", None) for n in range(0, 10) for v in m["variants"]] \
            + [("golden-seed-0003", "player-c", n, v, "buy", None) for n in range(0, 4) for v in m["variants"]]
    if m["code"] == "tutti-frutti":
        # Lines pay about one spin in three, so plenty of base cases; buys for every variant; and
        # buys with caps (hundredths of the bet: 30x and 100x) small enough to bite.
        return [("tutti-seed-0001", "player-a", n, "v1-9650", "base", None) for n in range(0, 60)] \
            + [("tutti-seed-0002", "player-b", n, v, "base", None) for n in range(0, 6) for v in ("v1-9550", "v1-9450")] \
            + [("tutti-seed-0003", "player-c", n, v, "buy", None) for n in range(0, 3) for v in m["variants"]] \
            + [("tutti-seed-0004", "player-d", n, "v1-9650", "buy", cap) for n in range(0, 4) for cap in (3_000, 10_000)]
    if m["code"] == "stones-of-the-titan":
        # Trident's draws, so fewer base cases; the super buy gets its own, with caps small enough to bite.
        return [("titan-seed-0001", "player-a", n, "v1-9650", "base", None) for n in range(0, 30)] \
            + [("titan-seed-0002", "player-b", n, v, "ante", None) for n in range(0, 4) for v in m["variants"]] \
            + [("titan-seed-0003", "player-c", n, v, "buy", None) for n in range(0, 3) for v in m["variants"]] \
            + [("titan-seed-0004", "player-d", n, v, "super", None) for n in range(0, 4) for v in m["variants"]] \
            + [("titan-seed-0005", "player-e", n, "v1-9650", "super", cap) for n in range(0, 4) for cap in (5_000, 20_000)]
    # Base pearls show up about once in five spins, so more base cases. The caps are hundredths of
    # the bet (20× and 50×), small enough to bite.
    return [("trident-seed-0001", "player-a", n, "v1-9650", "base", None) for n in range(0, 60)] \
        + [("trident-seed-0002", "player-b", n, v, "ante", None) for n in range(0, 10) for v in m["variants"]] \
        + [("trident-seed-0003", "player-c", n, v, "buy", None) for n in range(0, 4) for v in m["variants"]] \
        + [("trident-seed-0004", "player-d", n, "v1-9650", "buy", cap) for n in range(0, 6) for cap in (2_000, 5_000)]


if __name__ == "__main__":
    model = MODELS[sys.argv[1] if len(sys.argv) > 1 else "sugar-orchard"]()
    out = []
    for server, client, nonce, variant, mode, cap in cases(model):
        case = {"server_seed": server, "client_seed": client, "nonce": nonce, "variant": variant, "mode": mode}
        if cap is not None:
            case["cap"] = cap  # hundredths of the bet
        player = play_lines if model.get("kind") == "lines" else play
        case["outcome"] = player(model, variant, mode, RandomStream(server, client, nonce), cap)
        out.append(case)
    json.dump(out, sys.stdout, separators=(",", ":"))
    sys.stdout.write("\n")
