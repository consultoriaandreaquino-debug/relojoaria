"""
Ensaio "Fim de semana": três cenas de estilo de vida com relógios AQUINO.

    python tools/render_ensaio.py            # as três cenas
    python tools/render_ensaio.py xadrez     # uma cena (xadrez | clube | campo)

Usa o mesmo motor de render_watches.py (numpy + Pillow). Nenhuma foto externa,
nenhuma marca real. Saída em assets/img/editorial/ensaio-*.webp.
"""
from __future__ import annotations

import math
import sys

import numpy as np
from PIL import Image, ImageDraw, ImageFilter

from render_watches import (
    BRAND, OUT_EDITORIAL, SPEC_BY_SLUG, SS, Layer, add_shadow, fbm, font, mat_rgb,
    noise, render_watch, save, spaced_text,
)


# ---------------------------------------------------------------- utilidades
def smooth(e0, e1, x):
    t = np.clip((x - e0) / (e1 - e0), 0, 1)
    return t * t * (3 - 2 * t)


def gauss(arr: np.ndarray, r: float) -> np.ndarray:
    """Desfoque gaussiano em float (2D ou RGB) via Pillow, com precisão de 16 bits simulada em 2 passes."""
    if r <= 0.3:
        return arr
    if arr.ndim == 2:
        img = Image.fromarray((np.clip(arr, 0, 1) * 255).astype(np.uint8))
        return np.asarray(img.filter(ImageFilter.GaussianBlur(r)), np.float32) / 255
    return np.dstack([gauss(arr[..., i], r) for i in range(arr.shape[2])])


def blur_premult(C: np.ndarray, A: np.ndarray, r: float):
    """Desfoca uma camada pré-multiplicada (C já multiplicado por A)."""
    if r <= 0.3:
        return C, A
    hi = max(1.0, float(C.max()))
    return gauss(C / hi, r) * hi, gauss(A, r)


def put(bg: np.ndarray, C: np.ndarray, A: np.ndarray, x0: int, y0: int):
    """Compõe uma camada pré-multiplicada (recorte) sobre bg com canto superior esquerdo em (x0, y0)."""
    H, W = bg.shape[:2]
    h, w = A.shape
    xa, ya, xb, yb = max(0, x0), max(0, y0), min(W, x0 + w), min(H, y0 + h)
    if xa >= xb or ya >= yb:
        return bg
    c = C[ya - y0:yb - y0, xa - x0:xb - x0]
    a = A[ya - y0:yb - y0, xa - x0:xb - x0, None]
    bg[ya:yb, xa:xb] = c + bg[ya:yb, xa:xb] * (1 - a)
    return bg


def grain(arr: np.ndarray, amount: float, seed: int) -> np.ndarray:
    h, w = arr.shape[:2]
    return np.clip(arr + (noise(w, h, 1.0, seed) - 0.5)[..., None] * amount, 0, 1)


def vignette(arr: np.ndarray, strength=0.35, cx=0.5, cy=0.5) -> np.ndarray:
    h, w = arr.shape[:2]
    yy, xx = np.mgrid[0:h, 0:w].astype(np.float32)
    d = np.hypot((xx / w - cx) * 1.1, yy / h - cy)
    return arr * (1 - strength * smooth(0.35, 0.85, d))[..., None]


def watch_upright(slug: str, R: float, strap_up=1.75, strap_down=1.75, curl=0.55):
    """Relógio de frente, em pé, com a pulseira fechada curvando para trás (pontas escurecem)."""
    s = SPEC_BY_SLUG[slug]
    w = int(R * 2.9)
    h = int(R * (strap_up + strap_down + 0.4))
    cx, cy = w / 2, R * (strap_up + 0.2)
    L = render_watch(s, w, h, cx, cy, R, strap_top=cy - strap_up * R, strap_bottom=cy + strap_down * R)
    # a pulseira se afasta da câmera: escurece e estreita nas pontas
    d = np.where(L.Y < cy, (cy - L.Y) / (strap_up * R), (L.Y - cy) / (strap_down * R))
    shade = smooth(0.62, 1.0, d)
    L.C *= (1 - curl * shade)[..., None]
    return L, cx, cy


# ---------------------------------------------------------------- peças de xadrez (sólidos de revolução)
PAWN = [(0, 1.0), (.14, 1.0), (.20, .90), (.30, .84), (.33, .68), (.42, .64), (.48, .56),
        (1.25, .33), (1.32, .33), (1.37, .58), (1.47, .58), (1.52, .37), (1.58, .35)]
PAWN_HEAD = (2.05, .52)                     # centro (altura) e raio da cabeça
ROOK = [(0, 1.0), (.14, 1.0), (.20, .88), (.30, .82), (.33, .66), (.44, .62), (.50, .55),
        (1.70, .47), (1.76, .70), (1.86, .70), (1.90, .64), (2.42, .66)]
KING = [(0, 1.0), (.14, 1.0), (.20, .88), (.30, .82), (.33, .66), (.44, .62), (.50, .55),
        (2.05, .34), (2.12, .60), (2.22, .60), (2.27, .38), (2.32, .36), (2.70, .56), (2.78, .58),
        (2.84, .40), (2.90, .22), (2.98, .18)]


def profile_fn(points, head=None):
    zs = np.array([p[0] for p in points], np.float32)
    rs = np.array([p[1] for p in points], np.float32)
    top = zs[-1] if head is None else head[0] + head[1]

    def f(z):
        r = np.interp(z, zs, rs, right=0.0)
        if head is not None:
            zc, rh = head
            r = np.maximum(r, np.sqrt(np.clip(rh * rh - (z - zc) ** 2, 0, None)))
        return np.where(z <= top, r, 0.0)
    return f, top


def lathe_piece(points, R: float, k: float, head=None, crenel=False, cross=False):
    """Desenha uma peça dourada de revolução vista de cima em ângulo baixo (k = achatamento da elipse).
    Devolve (C, A, base_x, base_y) num recorte; base = centro da elipse da base."""
    f, top = profile_fn(points, head)
    pad = int(R * 0.2) + 4
    w = int(2 * R + 2 * pad)
    extra = 0.45 if cross else 0.0
    h = int((top + extra) * R + 2 * k * R + 2 * pad)
    bx, by = w / 2, h - pad - k * R
    yy, xx = np.mgrid[0:h, 0:w].astype(np.float32)
    C = np.zeros((h, w, 3), np.float32)
    A = np.zeros((h, w), np.float32)
    n = int(top * R * 1.2)
    zs = np.linspace(0, top, n)
    rs = f(zs)
    dz = zs[1] - zs[0]
    slopes = -np.gradient(rs, dz)               # >0: a superfície olha para cima
    gold_hi = np.array((1.0, 0.93, 0.70), np.float32)
    for i, (z, r) in enumerate(zip(zs, rs)):
        if r <= 0.01:
            continue
        rp = r * R
        yc = by - z * R
        y0, y1 = int(max(0, yc - k * rp - 2)), int(min(h, yc + k * rp + 3))
        sub_x = xx[y0:y1]
        sub_y = yy[y0:y1]
        nx = (sub_x - bx) / rp
        v = (sub_y - yc) / (k * rp)
        m = nx * nx + v * v <= 1.0
        if crenel and z > top - 0.32:
            m &= ~((np.abs(nx) < 0.14) | (np.abs(np.abs(nx) - 0.68) < 0.12))
        if not m.any():
            continue
        s = slopes[i]
        up = s / math.sqrt(1 + s * s)
        hf = 1 / math.sqrt(1 + s * s)
        nxx = np.clip(nx, -1, 1) * hf
        # parede (frente da elipse) x tampa (interior visível = face horizontal)
        side = smooth(0.35, 0.92, v)
        env_side = (0.06 + 0.62 * np.exp(-((nxx + 0.50) / 0.17) ** 2)       # janela/céu à esquerda
                    + 0.80 * np.exp(-((nxx + 0.33) / 0.045) ** 2)           # brilho especular
                    + 0.35 * np.exp(-((nxx - 0.30) / 0.22) ** 2)            # reflexo quente do tabuleiro
                    + 0.55 * np.exp(-((nxx - 0.84) / 0.06) ** 2))           # luz de recorte à direita
        env_side = env_side + 0.30 * max(up, 0) - 0.15 * max(-up, 0)
        env_cap = 0.42 + 0.30 * np.clip(-v, 0, 1) - 0.20 * nx
        t = env_side * side + env_cap * (1 - side)
        col = mat_rgb("gold", np.clip(t, 0, 1.0))
        hot = np.clip(t - 0.95, 0, 1)[..., None]
        col = col + (gold_hi - col) * np.clip(hot * 2.5, 0, 1)
        if crenel and abs(z - top) < dz * 1.5:  # torre oca: interior escuro
            inner = (nx * nx + v * v) <= 0.62
            col = np.where(inner[..., None], mat_rgb("gold", np.full(inner.shape, 0.05)), col)
        Cs, As = C[y0:y1], A[y0:y1]
        Cs[m] = col[m]
        As[m] = 1.0
    if cross:
        Lc = Image.new("L", (w, h), 0)
        d = ImageDraw.Draw(Lc)
        cxp, cyp = bx, by - top * R
        bw = R * 0.09
        d.rectangle([cxp - bw, cyp - R * 0.50, cxp + bw, cyp + 2], fill=255)
        d.rectangle([cxp - R * 0.22, cyp - R * 0.36, cxp + R * 0.22, cyp - R * 0.36 + 2 * bw], fill=255)
        cm = np.asarray(Lc, np.float32) / 255
        tx = 0.35 + 0.55 * np.exp(-(((xx - cxp) / (R * 0.06) + 0.4) ** 2))
        C = C * (1 - cm[..., None]) + mat_rgb("gold", tx) * cm[..., None]
        A = np.maximum(A, cm)
    A = gauss(A, 0.7)
    C = gauss(C, 0.5) * A[..., None]
    return C, A, bx, by


# ---------------------------------------------------------------- cena 1: xadrez
def scene_xadrez():
    W, H = 1200 * SS, 1500 * SS
    yy, xx = np.mgrid[0:H, 0:W].astype(np.float32)
    f, Hc, yh = 2600.0 * SS, 1.6, 0.315 * H
    cxs = W * 0.5
    Zn, Zf = 4.4, 12.4
    y_far = yh + f * Hc / Zf

    # céu de fim de manhã, nuvens finas, mar ao fundo (tudo fora de foco)
    v = yy / H
    sky = np.array((0.36, 0.55, 0.76), np.float32) * (1 - v[..., None]) + np.array((0.80, 0.86, 0.91), np.float32) * v[..., None]
    clouds = smooth(0.52, 0.75, fbm(W, H, W / 3.5, 11, 5)) * smooth(0.0, 0.25, v) * (1 - smooth(0.30, 0.40, v))
    sky = sky * (1 - 0.55 * clouds[..., None]) + np.array((0.95, 0.96, 0.97), np.float32) * (0.55 * clouds[..., None])
    sea_top = yh
    sea = np.array((0.17, 0.32, 0.48), np.float32) * (0.85 + 0.25 * fbm(W, H, 6 * SS, 21, 3))[..., None]
    sea = sea + np.array((0.45, 0.50, 0.52), np.float32) * (0.35 * np.exp(-(yy - yh) / (0.05 * H)))[..., None]
    sea += np.array((0.6, 0.7, 0.8), np.float32) * (0.10 * smooth(0.6, 0.9, noise(W, H, 3 * SS, 5)))[..., None]
    bg = np.where((yy >= sea_top)[..., None], sea, sky)
    bg = gauss(bg, 9 * SS)

    # tabuleiro: ébano e nogueira com moldura, plano em perspectiva
    Z = f * Hc / np.maximum(yy - yh, 1)
    X = (xx - cxs) * Z / f
    on_board = (Z >= Zn) & (Z <= Zf)
    bu = X + 4.0                                  # 0..8 nas casas
    bv = Zf - Z - 0.35                            # 0..8 em profundidade (sem a moldura)
    inside = (bu >= 0) & (bu < 8) & (bv >= 0) & (bv < 7.65)
    light_sq = ((np.floor(bu) + np.floor(bv)) % 2 == 0)
    T = 1024
    tex = fbm(T, T, 48, 31, 5)
    ti = np.clip((bv + 0.5) / 9 * (T - 1), 0, T - 1).astype(np.int32)
    tj = np.clip((bu + 0.5) / 9 * (T - 1), 0, T - 1).astype(np.int32)
    tw = tex[ti, tj]
    grain_w = fbm(W, H, 40 * SS, 33, 3)
    stripes = 0.5 + 0.5 * np.sin(bv * 7 + tw * 14)
    walnut = np.array((0.42, 0.27, 0.15), np.float32) * (0.80 + 0.22 * stripes[..., None] + 0.12 * tw[..., None])
    ebony = np.array((0.032, 0.030, 0.028), np.float32) * (0.85 + 0.35 * stripes[..., None])
    board = np.where((inside & light_sq)[..., None], walnut, ebony)
    inlay = (np.abs(bu + 0.12) < 0.025) | (np.abs(bu - 8.12) < 0.025) | (np.abs(bv + 0.12) < 0.025)
    board = np.where((inlay & on_board)[..., None], walnut * 1.1, board)
    # verniz: reflexo do céu cresce no rasante
    fres = smooth(6.5, 13.5, Z)[..., None]
    board = board + np.array((0.55, 0.65, 0.78), np.float32) * (0.10 + 0.22 * fres) * (1 - 0.5 * light_sq[..., None])
    # luz quente da esquerda sobre o tabuleiro
    board *= (1.05 - 0.35 * smooth(0.0, 1.0, xx / W))[..., None]
    bg = np.where(on_board[..., None], board, bg)
    # borda frontal do tabuleiro e mesa
    y_edge = yh + f * Hc / Zn
    y_edge2 = yh + f * (Hc + 0.22) / Zn
    edge = (yy > y_edge) & (yy <= y_edge2)
    bg = np.where(edge[..., None], np.array((0.03, 0.028, 0.026), np.float32) * (1 + 0.6 * (1 - (yy - y_edge) / (y_edge2 - y_edge)))[..., None], bg)
    table = yy > y_edge2
    bg = np.where(table[..., None], np.array((0.020, 0.018, 0.017), np.float32) * (1 + 0.3 * grain_w[..., None]), bg)
    bg[np.abs(yy - y_edge) < 1.2 * SS] = (0.42, 0.33, 0.20)   # filete de luz na quina

    def screen(xb, zb):
        return cxs + f * xb / zb, yh + f * Hc / zb, f / zb

    focus = 5.4

    def blur_for(z):
        return min(12 * SS, abs(1 / z - 1 / focus) * 180 * SS)

    items = []
    # (tipo, x no tabuleiro, profundidade, escala)
    for kind, xb, zb, sc in (("king", 0.35, 9.0, 1.0), ("pawn", -2.3, 8.4, 1.0), ("rook", 1.75, 7.6, 1.0),
                             ("pawn", -1.25, 6.4, 1.0), ("watch", -0.05, 5.4, 1.0), ("pawn", 0.95, 4.75, 1.0)):
        items.append((zb, kind, xb, sc))
    items.sort(reverse=True)                      # do fundo para a frente
    for zb, kind, xb, sc in items:
        sx, sy, scale = screen(xb, zb)
        k = math.sin(math.atan(Hc / zb))
        r_blur = blur_for(zb)
        if kind == "watch":
            R = 0.34 * scale
            Lw, wcx, wcy = watch_upright("heritage-noite-40", R, strap_up=1.3, strap_down=1.2, curl=0.7)
            # sombra de contato e sombra projetada para a direita/fundo
            sh = Image.new("L", (W, H), 0)
            ImageDraw.Draw(sh).ellipse([sx - R * 0.9, sy - R * 0.30, sx + R * 1.6, sy + R * 0.12], fill=255)
            shm = gauss(np.asarray(sh, np.float32) / 255, R * 0.22)
            bg *= (1 - 0.70 * shm)[..., None]
            ct = Image.new("L", (W, H), 0)
            ImageDraw.Draw(ct).ellipse([sx - R * 0.55, sy - R * 0.06, sx + R * 0.6, sy + R * 0.05], fill=255)
            bg *= (1 - 0.85 * gauss(np.asarray(ct, np.float32) / 255, R * 0.04))[..., None]
            rc, ra = blur_premult(Lw.C[::-1] * 0.16, Lw.A[::-1] * 0.16, 5 * SS)
            wb = wcy + 1.2 * R                       # pé da pulseira no recorte
            bg = put(bg, rc, ra, int(sx - wcx), int(sy - (Lw.A.shape[0] - wb)))
            Cw = Lw.C * 1.0
            # luz quente pela esquerda
            hh, ww = Lw.A.shape
            lx = np.linspace(1.12, 0.80, ww)[None, :, None]
            Cw = Cw * lx
            bg = put(bg, Cw, Lw.A, int(sx - wcx), int(sy - wb))
            continue
        R = (0.30 if kind == "pawn" else 0.33) * scale * sc
        pts, head = {"pawn": (PAWN, PAWN_HEAD), "rook": (ROOK, None), "king": (KING, None)}[kind]
        C, A, bx, by = lathe_piece(pts, R, k, head=head, crenel=kind == "rook", cross=kind == "king")
        # sombra no tabuleiro
        sh = Image.new("L", (W, H), 0)
        ImageDraw.Draw(sh).ellipse([sx - R * 0.9, sy - R * k * 1.4, sx + R * 2.4, sy + R * k * 0.9], fill=255)
        shm = gauss(np.asarray(sh, np.float32) / 255, R * 0.30 + r_blur)
        bg *= (1 - 0.55 * shm)[..., None]
        # reflexo suave no verniz
        refl_C, refl_A = C[::-1] * 0.18, A[::-1] * 0.18
        refl_C, refl_A = blur_premult(refl_C, refl_A, 6 * SS + r_blur)
        bg = put(bg, refl_C, refl_A, int(sx - bx), int(sy - (A.shape[0] - by)))
        C, A = blur_premult(C, A, r_blur)
        bg = put(bg, C, A, int(sx - bx), int(sy - by))

    out = vignette(bg, 0.30, 0.48, 0.55)
    out = out * np.array((1.03, 1.0, 0.96), np.float32)
    out = grain(out, 0.018, 404)
    save(out, OUT_EDITORIAL / "ensaio-xadrez.webp", (1200, 1500))


# ---------------------------------------------------------------- cena 2: clube (vista de cima)
def newspaper(w: int, h: int) -> Image.Image:
    """Jornal dobrado do clube da casa: cabeçalho verde, manchete e colunas."""
    img = Image.new("RGB", (w, h), (238, 233, 220))
    d = ImageDraw.Draw(img)
    green = (18, 61, 50)
    ivory = (240, 234, 220)
    head_h = int(h * 0.50)
    d.rectangle([0, 0, w, head_h], fill=green)
    m = int(w * 0.05)
    d.rectangle([m, m, w - m, head_h - m // 2], outline=(185, 154, 98), width=max(2, SS * 2))
    serif = font("times.ttf", int(h * 0.055))
    serif_b = font("times.ttf", int(h * 0.16))
    sans = font("arial.ttf", int(h * 0.026))
    spaced_text(img, "O CLUBE", serif, w / 2, head_h * 0.25, spacing=w * 0.012, fill=ivory)
    spaced_text(img, BRAND, serif_b, w / 2, head_h * 0.52, spacing=w * 0.02, fill=ivory)
    spaced_text(img, "EDIÇÃO DE DOMINGO   ·   PARA LER SEM PRESSA", sans, w / 2, head_h * 0.80, spacing=w * 0.004, fill=(185, 154, 98))
    d.text((m * 1.6, m * 1.5), "Nº 01", font=sans, fill=ivory)
    d.text((w - m * 1.6 - sans.getlength("SÃO PAULO"), m * 1.5), "SÃO PAULO", font=sans, fill=ivory)
    # parte de baixo: foto do clube (céu, avião) e colunas de texto
    y = head_h + int(h * 0.05)
    ph_w, ph_h = int(w * 0.42), int(h * 0.38)
    ph = np.zeros((ph_h, ph_w, 3), np.float32)
    vv = np.linspace(0, 1, ph_h)[:, None, None]
    ph[:] = np.array((0.42, 0.60, 0.78)) * (1 - vv) + np.array((0.82, 0.88, 0.92)) * vv
    phi = Image.fromarray((ph * 255).astype(np.uint8))
    pd = ImageDraw.Draw(phi)
    ax, ay = ph_w * 0.30, ph_h * 0.32
    pd.line([(ax - ph_w * 0.25, ay + ph_h * 0.20), (ax, ay)], fill=(236, 240, 244), width=max(2, SS * 2))
    pd.polygon([(ax - 12 * SS, ay + 4 * SS), (ax + 14 * SS, ay - 6 * SS), (ax + 4 * SS, ay + 1 * SS)], fill=(245, 245, 245))
    pd.line([(ax - 4 * SS, ay - 6 * SS), (ax + 6 * SS, ay + 8 * SS)], fill=(245, 245, 245), width=3 * SS)
    pd.rectangle([0, int(ph_h * 0.78), ph_w, ph_h], fill=(214, 206, 186))
    for px in (0.65, 0.80):
        pd.line([(ph_w * px, ph_h * 0.80), (ph_w * px + 6 * SS, ph_h * 0.52)], fill=(70, 60, 45), width=2 * SS)
        pd.ellipse([ph_w * px - 26 * SS, ph_h * 0.46, ph_w * px + 34 * SS, ph_h * 0.56], fill=(46, 72, 52))
    img.paste(phi.convert("L").convert("RGB").point(lambda c: int(c * 0.92 + 12)), (m, y))
    hl = font("times.ttf", int(h * 0.05))
    tx = m * 2 + ph_w
    d.text((tx, y - 6 * SS), "Tempo para", font=hl, fill=(28, 28, 26))
    d.text((tx, y + int(h * 0.055)), "não ter pressa", font=hl, fill=(28, 28, 26))
    rng = np.random.default_rng(3)
    line_h = int(h * 0.022)
    yy = y + int(h * 0.14)
    while yy < h - m:
        ln = w - m - tx
        if rng.random() < 0.12:
            ln *= rng.uniform(0.35, 0.8)
        d.rectangle([tx, yy, tx + ln, yy + max(1, line_h // 3)], fill=(120, 116, 108))
        yy += line_h
    yy = y + ph_h + int(h * 0.03)
    while yy < h - m:
        d.rectangle([m, yy, m + ph_w * rng.uniform(0.7, 1.0), yy + max(1, line_h // 3)], fill=(120, 116, 108))
        yy += line_h
    # papel: grão, vinco central e leve sombra da dobra
    arr = np.asarray(img, np.float32) / 255
    arr = arr * (0.96 + 0.05 * fbm(w, h, 4 * SS, 61, 3))[..., None]
    xs = np.linspace(-1, 1, w)[None, :, None]
    arr *= 1 - 0.10 * np.exp(-(xs / 0.015) ** 2) + 0.04 * np.exp(-((xs - 0.05) / 0.06) ** 2)
    return Image.fromarray((np.clip(arr, 0, 1) * 255).astype(np.uint8))


def scene_clube():
    W, H = 1200 * SS, 1500 * SS
    yy, xx = np.mgrid[0:H, 0:W].astype(np.float32)
    # roupão atoalhado branco: laçadas finas + dobras largas
    loops = noise(W, H, 1.4 * SS, 71) * 0.55 + noise(W, H, 2.6 * SS, 72) * 0.45
    loops = smooth(0.25, 0.85, loops)
    folds = fbm(W, H, W / 2.2, 73, 4)
    fold_shade = 0.93 + 0.07 * np.sin(folds * 8 + xx / W * 2.0) + 0.04 * folds
    terry = np.array((0.97, 0.955, 0.925), np.float32) * (0.86 + 0.14 * loops[..., None]) * fold_shade[..., None]
    # vivo verde do roupão (lapela) em curva
    curve = W * 0.20 + (yy / H) * W * 0.12 + np.sin(yy / H * 3.1) * W * 0.05
    piping = smooth(16 * SS, 11 * SS, np.abs(xx - curve))
    lapel = smooth(0, 30 * SS, xx - curve)
    terry *= (0.90 + 0.10 * lapel)[..., None]
    terry = terry * (1 - piping[..., None]) + np.array((0.07, 0.24, 0.19), np.float32) * (0.85 + 0.2 * loops[..., None]) * piping[..., None]
    bg = terry

    # jornal girado, com sombra dura de sol
    pw, ph = int(W * 0.78), int(H * 0.50)
    paper = newspaper(pw, ph).convert("RGBA")
    paper = paper.rotate(-7, resample=Image.BICUBIC, expand=True)
    pa = np.asarray(paper, np.float32) / 255
    pA = pa[..., 3]
    pC = pa[..., :3] * pA[..., None]
    px, py = int(W * 0.17), int(H * 0.14)
    shA = np.zeros((H, W), np.float32)
    hh, ww = pA.shape
    sx0, sy0 = px + 22 * SS, py + 30 * SS
    shA[max(0, sy0):min(H, sy0 + hh), max(0, sx0):min(W, sx0 + ww)] = pA[:min(H, sy0 + hh) - sy0, :min(W, sx0 + ww) - sx0]
    bg *= (1 - 0.42 * gauss(shA, 6 * SS))[..., None]
    bg = put(bg, pC, pA, px, py)

    # relógio sobre o jornal, inclinado
    R = W * 0.15
    s = SPEC_BY_SLUG["heritage-verde-40"]
    lw, lh = int(R * 3.0), int(R * 13.6)
    Lw = render_watch(s, lw, lh, lw / 2, lh / 2, R, strap_top=0, strap_bottom=lh)
    img = Lw.to_image().rotate(28, resample=Image.BICUBIC, expand=True)
    wa = np.asarray(img, np.float32) / 255
    wA = wa[..., 3]
    wC = wa[..., :3] * wA[..., None]
    wx, wy = int(W * 0.50 - wA.shape[1] / 2), int(H * 0.60 - wA.shape[0] / 2)
    shA = np.zeros((H, W), np.float32)
    sx0, sy0 = wx + int(R * 0.30), wy + int(R * 0.42)
    h2, w2 = wA.shape
    ys, xs = slice(max(0, sy0), min(H, sy0 + h2)), slice(max(0, sx0), min(W, sx0 + w2))
    shA[ys, xs] = wA[ys.start - sy0:ys.stop - sy0, xs.start - sx0:xs.stop - sx0]
    bg *= (1 - 0.55 * gauss(shA, 9 * SS))[..., None]
    bg = put(bg, wC, wA, wx, wy)

    # sombra de folha de palmeira (sol por cima, à esquerda)
    pal = Image.new("L", (W, H), 0)
    pdr = ImageDraw.Draw(pal)
    stem = [(W * (-0.05 + 0.5 * t), H * (0.02 + 0.30 * t + 0.10 * t * t)) for t in np.linspace(0, 1, 40)]
    pdr.line(stem, fill=255, width=int(7 * SS))
    for i, (sx_, sy_) in enumerate(stem[2:-1]):
        t = i / len(stem)
        ln = W * (0.16 + 0.10 * math.sin(t * math.pi))
        for side in (-1, 1):
            ang = math.radians(60 + side * 0 + (75 if side > 0 else -120))
            ex, ey = sx_ + ln * math.cos(ang) * (1 if side > 0 else 1), sy_ + ln * math.sin(ang)
            pdr.line([(sx_, sy_), ((sx_ + ex) / 2 + side * 8 * SS, (sy_ + ey) / 2), (ex, ey)], fill=255, width=int(9 * SS))
    palm = gauss(np.asarray(pal, np.float32) / 255, 5 * SS)
    bg = bg * (1 - 0.30 * palm[..., None]) * (1 - np.array((0.02, 0.0, -0.04), np.float32) * palm[..., None])

    # sol forte de meio-dia: tom quente, contraste e leve vinheta
    out = bg * np.array((1.08, 1.06, 1.0), np.float32)
    out = vignette(out, 0.18)
    out = grain(out, 0.02, 505)
    save(out, OUT_EDITORIAL / "ensaio-clube.webp", (1200, 1500))


# ---------------------------------------------------------------- cena 3: campo de golfe ao pôr do sol
def scene_campo():
    W, H = 1600 * SS, 1200 * SS
    yy, xx = np.mgrid[0:H, 0:W].astype(np.float32)
    u, v = xx / W, yy / H
    hz = 0.36                                     # horizonte
    sunx, suny = 0.70, 0.30
    dsun = np.hypot((u - sunx) * W / H, v - suny)
    # céu de fim de tarde
    t = np.clip(v / hz, 0, 1)[..., None]
    sky = np.array((0.74, 0.66, 0.70), np.float32) * (1 - t) + np.array((1.0, 0.84, 0.64), np.float32) * t
    sky += np.array((1.0, 0.78, 0.48), np.float32) * (np.exp(-dsun / 0.16) * 0.55)[..., None]
    bg = np.clip(sky, 0, 1)
    # morros em camadas, com névoa contra o sol
    for base, amp, col, seed in ((0.335, 0.035, (0.62, 0.52, 0.55), 3), (0.350, 0.022, (0.47, 0.40, 0.42), 8)):
        ridge = base - amp * (fbm(W, 1, W / 2.5, seed, 4)[0] - 0.35) * 2
        m = smooth(-1.5 / H, 1.5 / H, v - ridge[None, :])
        haze = (np.exp(-dsun / 0.35) * 0.45)[..., None]
        c = np.array(col, np.float32) * (1 - haze) + np.array((1.0, 0.84, 0.64), np.float32) * haze
        bg = bg * (1 - m[..., None]) + c * m[..., None]
    # lago com o reflexo do sol
    lake = (v > 0.357) & (v < 0.368)
    lc = np.array((0.88, 0.78, 0.70), np.float32) + np.array((0.12, 0.10, 0.02), np.float32) * np.exp(-np.abs(u - sunx) / 0.05)[..., None]
    bg = np.where(lake[..., None], np.clip(lc, 0, 1), bg)
    # gramado em perspectiva: fairway com faixas de corte, rough dourado, green e caminho
    yh = hz * H
    depth = np.maximum(yy - yh, 1.0)
    X = (xx - W * 0.46) / depth * 1.0             # unidades de solo
    Zg = 1.0 / depth * 900 * SS                     # profundidade relativa
    turf = v > 0.366
    center = 0.25 * np.sin(Zg * 0.9) - 0.4
    fx = X - center
    stripes = smooth(-0.15, 0.15, np.sin(fx * 5.0))
    fair = np.array((0.36, 0.47, 0.18), np.float32) * (0.86 + 0.16 * stripes[..., None])
    rough = np.array((0.52, 0.48, 0.26), np.float32) * (0.85 + 0.25 * fbm(W, H, 4 * SS, 18, 3))[..., None]
    rm = smooth(1.3, 1.9, np.abs(fx) + 0.25 * (fbm(W, H, 30 * SS, 17, 3) - 0.5))
    grass = fair * (1 - rm[..., None]) + rough * rm[..., None]
    gX, gZ = 1.1, 2.6
    gm = smooth(1.0, 0.92, np.hypot((X - gX) / 0.9, (Zg - gZ) / 0.55))
    grass = grass * (1 - gm[..., None]) + np.array((0.48, 0.60, 0.24), np.float32) * gm[..., None]
    path_c = -2.1 + 0.35 * np.sin(Zg * 1.3)
    pm = smooth(0.14, 0.10, np.abs(X - path_c))
    grass = grass * (1 - pm[..., None]) + np.array((0.66, 0.62, 0.58), np.float32) * pm[..., None]
    # luz rasante e dourada
    glow = np.exp(-np.abs(u - sunx) / 0.5)
    grass *= (0.78 + 0.40 * glow * (1 - 0.5 * smooth(0.37, 1.0, v)))[..., None]
    grass += np.array((0.20, 0.12, 0.0), np.float32) * (smooth(0.55, 0.366, v) * 0.6)[..., None]
    bg = np.where(turf[..., None], grass, bg)
    # árvores: grupos de copas na linha do horizonte e soltas no campo
    rng = np.random.default_rng(9)
    tl = Image.new("L", (W, H), 0)
    td = ImageDraw.Draw(tl)
    for _ in range(140):
        tx_, ty_ = rng.uniform(0, 1) * W, (0.362 + rng.uniform(0, 0.006)) * H
        sz = rng.uniform(3, 9) * SS
        td.ellipse([tx_ - sz, ty_ - sz * 1.6, tx_ + sz, ty_], fill=255)
    for _ in range(14):
        tx_, tyv = rng.uniform(0.02, 0.98), rng.uniform(0.375, 0.43)
        sz = (tyv - 0.36) * H * 0.22
        for _k in range(5):
            ox, oy = rng.normal(0, sz * 0.5), rng.normal(0, sz * 0.3)
            td.ellipse([tx_ * W + ox - sz, tyv * H + oy - sz * 2.0, tx_ * W + ox + sz, tyv * H + oy - sz * 0.2], fill=255)
    tmask = gauss(np.asarray(tl, np.float32) / 255, 1.0 * SS)
    tcol = np.array((0.16, 0.18, 0.11), np.float32) * (1 + 0.6 * np.exp(-dsun / 0.3))[..., None]
    bg = bg * (1 - tmask[..., None]) + tcol * tmask[..., None]
    # foco curto: a paisagem fica macia, mais ainda perto da câmera
    soft = gauss(bg, 4 * SS)
    softer = gauss(bg, 14 * SS)
    k = smooth(0.42, 0.75, v)[..., None]
    bg = soft * (1 - k) + softer * k
    # brilho do sol (bloom)
    bg = np.clip(bg + np.array((1.0, 0.85, 0.6), np.float32) * (np.exp(-dsun / 0.025) * 0.9 + np.exp(-dsun / 0.08) * 0.25)[..., None], 0, 1)

    # colunas do teto do carrinho, perto da lente e fora de foco
    cart = Image.new("L", (W, H), 0)
    cd = ImageDraw.Draw(cart)
    cd.polygon([(0, -H * 0.1), (W * 0.03, -H * 0.1), (W * 0.10, H), (0, H)], fill=255)
    cd.polygon([(W, -H * 0.1), (W * 0.975, -H * 0.1), (W * 0.91, H), (W, H)], fill=255)
    cm = gauss(np.asarray(cart, np.float32) / 255, 10 * SS)
    bg = bg * (1 - 0.92 * cm[..., None])
    # painel escuro embaixo
    dash = smooth(0.80, 0.84, v + 0.02 * np.cos((u - 0.5) * 3))
    dcol = np.array((0.045, 0.043, 0.045), np.float32) * (1 + 0.8 * (1 - smooth(0.82, 0.90, v)))[..., None]
    dcol += np.array((0.9, 0.6, 0.35), np.float32) * (0.25 * np.exp(-((v - 0.83) / 0.006) ** 2) * glow)[..., None]
    bg = bg * (1 - dash[..., None]) + dcol * dash[..., None]

    # volante: aro grosso em couro preto, em foco, com luz do sol na borda de cima
    wcx, wcy, wrx, wry = W * 0.50, H * 1.30, W * 0.46, H * 0.66
    th = 0.12
    rho = np.sqrt(((xx - wcx) / wrx) ** 2 + ((yy - wcy) / wry) ** 2)
    across = (rho - 1) / (th / 2)                  # -1 dentro … +1 fora
    wm = smooth(1.0, 0.94, np.abs(across))
    leather = 0.6 + 0.4 * noise(W, H, 1.2 * SS, 88)
    wcol = np.array((0.05, 0.045, 0.045), np.float32) * (0.7 + 0.6 * np.clip(1 - np.abs(across + 0.2), 0, 1) ** 2)[..., None] * leather[..., None]
    rim = np.exp(-((across - 0.70) / 0.12) ** 2) * (0.35 + 0.65 * glow)
    wcol += np.array((1.0, 0.70, 0.42), np.float32) * (0.45 * rim)[..., None]
    sh_w = gauss(wm, 12 * SS)
    bg *= (1 - 0.35 * np.roll(sh_w, int(20 * SS), axis=0))[..., None]
    bg = bg * (1 - wm[..., None]) + wcol * wm[..., None]

    # relógio afivelado no alto do volante, mostrador voltado para a câmera
    R = H * 0.15
    Lw, lcx, lcy = watch_upright("altitude-gmt-40", R, strap_up=1.40, strap_down=1.40, curl=0.85)
    Cw = Lw.C * (np.linspace(0.88, 1.12, Lw.C.shape[1])[None, :, None] * np.array((1.06, 1.0, 0.92), np.float32))
    tx, ty = wcx, wcy - wry
    ox, oy = int(tx - lcx), int(ty - lcy)
    hh, ww_ = Lw.A.shape
    sm = np.zeros((H, W), np.float32)
    dx, dy = int(-18 * SS), int(16 * SS)
    ys = slice(max(0, oy + dy), min(H, oy + dy + hh))
    xs = slice(max(0, ox + dx), min(W, ox + dx + ww_))
    sm[ys, xs] = Lw.A[ys.start - oy - dy:ys.stop - oy - dy, xs.start - ox - dx:xs.stop - ox - dx]
    bg *= (1 - 0.55 * gauss(sm, 10 * SS) * wm)[..., None]
    bg = put(bg, Cw, Lw.A, ox, oy)

    out = vignette(bg, 0.32, 0.55, 0.45)
    out = out * np.array((1.04, 1.0, 0.93), np.float32)
    out = grain(out, 0.022, 606)
    save(out, OUT_EDITORIAL / "ensaio-campo.webp", (1600, 1200))


SCENES = {"xadrez": scene_xadrez, "clube": scene_clube, "campo": scene_campo}

if __name__ == "__main__":
    OUT_EDITORIAL.mkdir(parents=True, exist_ok=True)
    for name in (sys.argv[1:] or SCENES):
        print(name)
        SCENES[name]()
