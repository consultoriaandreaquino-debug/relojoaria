"""
Gera as fotografias de estúdio dos relógios Morvane (sem marcas reais).

Uso (opcional, só em desenvolvimento):
    python tools/render_watches.py            # todas as peças + editoriais
    python tools/render_watches.py ondina-39  # uma peça

Requer: Python 3.10+, numpy, Pillow (com suporte a WebP).
O site não depende deste script: as imagens geradas já ficam em assets/img/.
"""
from __future__ import annotations

import math
import sys
from dataclasses import dataclass, field
from pathlib import Path

import numpy as np
from PIL import Image, ImageDraw, ImageFilter, ImageFont

ROOT = Path(__file__).resolve().parents[1]
OUT_PRODUCTS = ROOT / "assets" / "img" / "products"
OUT_EDITORIAL = ROOT / "assets" / "img" / "editorial"
FONTS = Path("C:/Windows/Fonts")

SS = 2  # supersampling


def font(name: str, size: int) -> ImageFont.FreeTypeFont:
    for candidate in (FONTS / name, Path("/usr/share/fonts/truetype/dejavu/DejaVuSerif.ttf")):
        if candidate.exists():
            return ImageFont.truetype(str(candidate), size)
    return ImageFont.load_default()


# ---------------------------------------------------------------- materiais
MATS = {
    "steel": ((0.93, 0.94, 0.95), (0.30, 0.31, 0.33)),
    "steel_brushed": ((0.80, 0.81, 0.82), (0.42, 0.43, 0.45)),
    "gold": ((0.98, 0.86, 0.56), (0.45, 0.31, 0.12)),
    "rose": ((0.97, 0.78, 0.66), (0.48, 0.27, 0.20)),
    "dlc": ((0.36, 0.36, 0.38), (0.05, 0.05, 0.06)),
    "pvd": ((0.30, 0.30, 0.31), (0.04, 0.04, 0.05)),
    "blued": ((0.25, 0.38, 0.75), (0.05, 0.08, 0.25)),
    "black": ((0.20, 0.20, 0.21), (0.03, 0.03, 0.03)),
}


def mat_rgb(mat: str, t: np.ndarray) -> np.ndarray:
    light, dark = (np.array(c, dtype=np.float32) for c in MATS[mat])
    t = np.clip(t, 0, 1)[..., None]
    return dark + (light - dark) * t


def mat_color(mat: str, t: float) -> tuple[int, int, int]:
    light, dark = MATS[mat]
    return tuple(int(255 * (d + (l - d) * t)) for l, d in zip(light, dark))


def rgb255(c) -> tuple[int, int, int]:
    return tuple(int(max(0, min(1, v)) * 255) for v in c)


# ---------------------------------------------------------------- ruído
def noise(w: int, h: int, scale: float, seed: int) -> np.ndarray:
    r = np.random.default_rng(seed)
    sw, sh = max(2, int(w / scale)), max(2, int(h / scale))
    a = (r.random((sh, sw)) * 255).astype(np.uint8)
    return np.asarray(Image.fromarray(a).resize((w, h), Image.BICUBIC), dtype=np.float32) / 255


def fbm(w: int, h: int, scale: float, seed: int, octaves: int = 4) -> np.ndarray:
    total = np.zeros((h, w), np.float32)
    amp, norm = 1.0, 0.0
    for i in range(octaves):
        total += amp * noise(w, h, max(1.5, scale / (2 ** i)), seed + i * 17)
        norm += amp
        amp *= 0.5
    return total / norm


# ---------------------------------------------------------------- camada RGBA (pré-multiplicada)
class Layer:
    def __init__(self, w: int, h: int):
        self.w, self.h = w, h
        self.C = np.zeros((h, w, 3), np.float32)
        self.A = np.zeros((h, w), np.float32)
        yy, xx = np.mgrid[0:h, 0:w].astype(np.float32)
        self.X, self.Y = xx, yy

    def over(self, rgb, alpha: np.ndarray) -> None:
        a = np.clip(alpha, 0, 1)
        rgb = np.broadcast_to(np.asarray(rgb, np.float32), self.C.shape) if np.ndim(rgb) == 1 else rgb
        self.C = rgb * a[..., None] + self.C * (1 - a[..., None])
        self.A = a + self.A * (1 - a)

    def darken(self, amount: np.ndarray) -> None:
        self.C *= (1 - np.clip(amount, 0, 1))[..., None]

    def lighten(self, amount: np.ndarray, color=(1, 1, 1)) -> None:
        a = np.clip(amount, 0, 1)[..., None] * self.A[..., None]
        self.C = self.C * (1 - a) + np.asarray(color, np.float32) * a

    def to_image(self, size: tuple[int, int] | None = None) -> Image.Image:
        alpha = np.clip(self.A, 0, 1)
        safe = np.where(alpha > 1e-4, alpha, 1)[..., None]
        rgb = np.clip(self.C / safe, 0, 1)
        arr = np.dstack([rgb, alpha[..., None]])
        img = Image.fromarray((arr * 255 + 0.5).astype(np.uint8), "RGBA")
        return img.resize(size, Image.LANCZOS) if size else img


def mask_from(draw_fn, w: int, h: int, blur: float = 0.6) -> np.ndarray:
    m = Image.new("L", (w, h), 0)
    draw_fn(ImageDraw.Draw(m))
    if blur:
        m = m.filter(ImageFilter.GaussianBlur(blur))
    return np.asarray(m, np.float32) / 255


def disc(L: Layer, cx, cy, r, blur=0.8) -> np.ndarray:
    d = np.sqrt((L.X - cx) ** 2 + (L.Y - cy) ** 2)
    return np.clip((r - d) / blur + 0.5, 0, 1)


def ring(L: Layer, cx, cy, r_in, r_out, blur=0.8) -> np.ndarray:
    return disc(L, cx, cy, r_out, blur) * (1 - disc(L, cx, cy, r_in, blur))


def polar(L: Layer, cx, cy):
    dx, dy = L.X - cx, L.Y - cy
    return np.sqrt(dx * dx + dy * dy), np.arctan2(dy, dx)


def rot(cx, cy, ang_deg, r):
    """Ponto a partir do centro, ângulo em graus no sentido horário a partir das 12h."""
    a = math.radians(ang_deg)
    return cx + r * math.sin(a), cy - r * math.cos(a)


# ---------------------------------------------------------------- especificação
@dataclass
class Spec:
    slug: str
    case: str = "steel"
    bezel: str = "polished"          # polished | fluted | diver | gmt | graduated | thin
    bezel_mat: str | None = None
    dial: tuple = (0.9, 0.9, 0.9)
    finish: str = "matte"            # sunburst | matte | guilloche | lacquer | opaline
    indices: str = "baton"           # baton | dot | arabic | minimal
    index_mat: str = "steel"
    hands: str = "dauphine"          # dauphine | baton | sword | leaf
    hand_mat: str = "steel"
    seconds: tuple | None = (0.85, 0.85, 0.86)
    strap: str = "leather"           # leather | croc | rubber | bracelet | bracelet2 | mesh
    strap_color: tuple = (0.30, 0.18, 0.10)
    diameter: float = 40
    date: bool = False
    chrono: tuple | None = None      # cor dos contadores
    small_seconds: bool = False
    tachy: bool = False
    gmt_hand: tuple | None = None
    lume: bool = False
    label: str = "AUTOMÁTICO"
    print_color: tuple | None = None

    @property
    def light_dial(self) -> bool:
        return sum(self.dial) / 3 > 0.55

    @property
    def ink(self):
        if self.print_color:
            return self.print_color
        return (0.16, 0.16, 0.17) if self.light_dial else (0.90, 0.90, 0.88)


SPECS = [
    Spec("ondina-39", dial=(0.07, 0.17, 0.40), finish="sunburst", strap="bracelet", diameter=39),
    Spec("serra-38-ouro-rose", case="rose", bezel="thin", dial=(0.96, 0.95, 0.93), finish="lacquer",
         index_mat="rose", hand_mat="rose", seconds=(0.75, 0.50, 0.40), strap="leather",
         strap_color=(0.33, 0.19, 0.11), diameter=38),
    Spec("varanda-40", dial=(0.30, 0.32, 0.34), finish="sunburst", strap="leather",
         strap_color=(0.36, 0.36, 0.37), date=True, diameter=40),
    Spec("telemetro-42", dial=(0.93, 0.92, 0.89), finish="matte", chrono=(0.07, 0.07, 0.08), hands="baton",
         hand_mat="black", seconds=(0.10, 0.10, 0.10), strap="bracelet", diameter=42, label="CRONÓGRAFO"),
    Spec("interlagos-41", case="dlc", dial=(0.07, 0.07, 0.08), finish="matte", chrono=(0.17, 0.17, 0.18),
         hands="baton", hand_mat="steel", seconds=(0.73, 0.60, 0.38), strap="rubber",
         strap_color=(0.06, 0.06, 0.07), lume=True, diameter=41, label="CRONÓGRAFO"),
    Spec("mares-39-cronografo", dial=(0.92, 0.87, 0.76), finish="matte", chrono=(0.11, 0.20, 0.40),
         hands="leaf", hand_mat="blued", seconds=(0.11, 0.20, 0.40), strap="leather",
         strap_color=(0.45, 0.27, 0.13), tachy=True, diameter=39, label="CORDA MANUAL"),
    Spec("tabeliao-38", case="gold", bezel="thin", dial=(0.94, 0.90, 0.80), finish="opaline",
         index_mat="gold", hands="leaf", hand_mat="gold", seconds=None, strap="croc",
         strap_color=(0.05, 0.05, 0.06), diameter=38, label="CORDA MANUAL"),
    Spec("aurora-34", dial=(0.83, 0.84, 0.85), finish="guilloche", strap="bracelet", diameter=34),
    Spec("vigilia-39", dial=(0.96, 0.96, 0.94), finish="opaline", indices="arabic", hands="leaf",
         hand_mat="blued", seconds=None, small_seconds=True, strap="leather",
         strap_color=(0.34, 0.20, 0.11), diameter=39, label="CORDA MANUAL"),
    Spec("abrolhos-300", bezel="diver", dial=(0.03, 0.03, 0.04), finish="lacquer", indices="dot",
         hands="sword", lume=True, seconds=(0.90, 0.90, 0.88), strap="rubber",
         strap_color=(0.06, 0.06, 0.07), date=True, diameter=42),
    Spec("altitude-gmt-40", bezel="gmt", dial=(0.04, 0.04, 0.05), finish="lacquer", indices="dot",
         hands="sword", lume=True, gmt_hand=(0.73, 0.60, 0.38), strap="bracelet", date=True, diameter=40),
    Spec("pedra-azul-36", bezel="graduated", dial=(0.78, 0.79, 0.80), finish="sunburst", hands="sword",
         lume=True, strap="bracelet", diameter=36),
    Spec("linha-38", bezel="thin", dial=(0.95, 0.95, 0.94), finish="matte", indices="minimal", hands="baton",
         hand_mat="black", seconds=None, strap="mesh", diameter=38),
    Spec("linha-38-noite", case="pvd", bezel="thin", dial=(0.06, 0.06, 0.07), finish="matte",
         indices="minimal", index_mat="gold", hands="baton", hand_mat="gold", seconds=None, strap="leather",
         strap_color=(0.05, 0.05, 0.05), diameter=38, print_color=(0.73, 0.60, 0.38)),
    Spec("sereno-33", bezel="thin", dial=(0.87, 0.80, 0.69), finish="matte", indices="minimal",
         hands="baton", hand_mat="gold", seconds=None, strap="leather", strap_color=(0.76, 0.66, 0.53),
         diameter=33, label="CORDA MANUAL", print_color=(0.42, 0.34, 0.24)),
    Spec("heritage-verde-40", bezel="fluted", bezel_mat="gold", dial=(0.05, 0.27, 0.17), finish="sunburst",
         index_mat="gold", hand_mat="gold", seconds=(0.95, 0.83, 0.55), strap="bracelet2", date=True,
         diameter=40),
    Spec("heritage-noite-40", case="gold", bezel="fluted", dial=(0.04, 0.08, 0.20), finish="sunburst",
         index_mat="gold", hand_mat="gold", seconds=(0.95, 0.83, 0.55), strap="croc",
         strap_color=(0.05, 0.08, 0.17), diameter=40),
]
SPEC_BY_SLUG = {s.slug: s for s in SPECS}


# ---------------------------------------------------------------- partes do relógio
def draw_strap(L: Layer, s: Spec, cx, cy, R, top_y, bottom_y):
    sw = R * 1.02 * 2 * 0.5
    x0, x1 = cx - sw / 2, cx + sw / 2
    for direction in (-1, 1):
        y_a, y_b = (top_y, cy) if direction == -1 else (cy, bottom_y)
        taper = 0.10 * sw

        def shape(d, y_a=y_a, y_b=y_b, direction=direction):
            if direction == -1:
                d.polygon([(x0 + taper, y_a), (x1 - taper, y_a), (x1, y_b), (x0, y_b)], fill=255)
            else:
                d.polygon([(x0, y_a), (x1, y_a), (x1 - taper, y_b), (x0 + taper, y_b)], fill=255)

        m = mask_from(shape, L.w, L.h, 1.0)
        u = np.clip((L.X - x0) / sw, 0, 1)  # posição transversal 0..1
        cyl = 1 - (2 * u - 1) ** 2  # sombreamento cilíndrico

        if s.strap in ("leather", "croc", "rubber"):
            base = np.array(s.strap_color, np.float32)
            shade = 0.55 + 0.45 * cyl ** 0.5
            rgb = base * shade[..., None]
            if s.strap == "leather":
                g = fbm(L.w, L.h, 6 * SS, 11)
                rgb *= (0.90 + 0.18 * g)[..., None]
            elif s.strap == "croc":
                cells = croc_pattern(L.w, L.h, sw, x0, seed=3)
                rgb *= (0.55 + 0.55 * cells)[..., None]
                g = fbm(L.w, L.h, 3 * SS, 5)
                rgb *= (0.92 + 0.12 * g)[..., None]
                rgb += 0.05 * (cells ** 6)[..., None]
            else:  # borracha
                grooves = 0.5 + 0.5 * np.cos((L.Y - cy) / (R * 0.06) * math.pi)
                near = np.clip(1 - np.abs(L.Y - cy) / (R * 2.2), 0, 1)
                rgb *= (1 - 0.18 * grooves * near)[..., None]
                rgb += 0.06 * (cyl ** 8)[..., None]
            L.over(rgb, m)
            if s.strap == "leather":
                stitch = mask_from(lambda d: stitch_lines(d, x0, x1, y_a, y_b, sw, taper, direction), L.w, L.h, 0.7)
                lc = np.clip(base * 1.9 + 0.15, 0, 0.92)
                L.over(lc, stitch * m * 0.85)
        elif s.strap in ("bracelet", "bracelet2"):
            draw_bracelet(L, s, x0, x1, y_a, y_b, sw, m, direction)
        else:  # malha
            k = 2 * math.pi / (2.6 * SS)
            mesh = (np.sin((L.X + L.Y) * k) * np.sin((L.X - L.Y) * k)) * 0.5 + 0.5
            t = 0.18 + 0.40 * cyl + 0.35 * mesh
            L.over(mat_rgb("steel_brushed", t), m)


def stitch_lines(d: ImageDraw.ImageDraw, x0, x1, y_a, y_b, sw, taper, direction):
    inset = sw * 0.07
    step = 9 * SS
    for side in (0, 1):
        y = y_a
        while y < y_b:
            frac = (y - y_a) / max(1, (y_b - y_a))
            tp = taper * (1 - frac) if direction == -1 else taper * frac
            x = (x0 + tp + inset) if side == 0 else (x1 - tp - inset)
            d.line([(x, y), (x, y + step * 0.55)], fill=255, width=max(1, int(1.6 * SS)))
            y += step


def croc_pattern(w, h, sw, x0, seed=1) -> np.ndarray:
    rng = np.random.default_rng(seed)
    img = Image.new("L", (w, h), 255)
    d = ImageDraw.Draw(img)
    y = 0.0
    while y < h:
        hh = sw * rng.uniform(0.14, 0.22)
        x = x0 - rng.uniform(0, sw * 0.2)
        while x < x0 + sw * 1.2:
            ww = sw * rng.uniform(0.16, 0.30)
            d.rounded_rectangle([x + 2 * SS, y + 2 * SS, x + ww - 2 * SS, y + hh - 2 * SS],
                                radius=6 * SS, fill=int(rng.uniform(150, 230)))
            x += ww
        d.line([(0, y), (w, y)], fill=40, width=2 * SS)
        y += hh
    img = img.filter(ImageFilter.GaussianBlur(1.6 * SS))
    return np.asarray(img, np.float32) / 255


def draw_bracelet(L: Layer, s: Spec, x0, x1, y_a, y_b, sw, m, direction):
    row_h = sw * 0.26
    center_mat = "gold" if s.strap == "bracelet2" else "steel"
    side_mat = "steel_brushed"
    cw = sw * 0.36
    cxm = (x0 + x1) / 2
    yrel = (L.Y - y_a) % row_h / row_h
    rows = np.clip(1 - np.abs(yrel - 0.5) * 2, 0, 1)  # perfil arredondado do elo
    u_side = np.clip((L.X - x0) / sw, 0, 1)
    in_center = np.abs(L.X - cxm) < cw / 2
    uc = np.clip((L.X - (cxm - cw / 2)) / cw, 0, 1)
    # elo central polido: reflexo estreito no meio, bordas escuras
    t_center = 0.12 + 0.95 * np.exp(-((uc - 0.42) ** 2) / 0.05) * (0.55 + 0.45 * rows ** 0.5) + 0.12 * rows
    streak = noise(L.w, L.h, 30 * SS, 21)
    hair = noise(L.w, 1, 0.8, 5)[0][None, :]
    cyl = 1 - (2 * u_side - 1) ** 2
    t_side = 0.30 + 0.25 * rows ** 0.6 + 0.10 * (streak - 0.5) + 0.25 * cyl + 0.08 * (hair - 0.5)
    rgb = np.where(in_center[..., None], mat_rgb(center_mat, t_center), mat_rgb(side_mat, t_side))
    gap = np.clip(1 - np.minimum(yrel, 1 - yrel) * row_h / (2.2 * SS), 0, 1)
    vgap = np.clip(1 - np.abs(np.abs(L.X - cxm) - cw / 2) / (1.8 * SS), 0, 1)
    rgb = rgb * (1 - 0.80 * np.maximum(gap, vgap))[..., None]
    L.over(rgb, m)


def draw_lugs(L: Layer, s: Spec, cx, cy, R):
    sw = R * 1.02
    lw = R * 0.15
    for sx in (-1, 1):
        for sy in (-1, 1):
            xa = cx + sx * sw / 2          # face interna (encosta na pulseira)
            xb = xa + sx * lw              # face externa
            ya = cy + sy * R * 0.62
            yb = cy + sy * R * 1.10

            def shape(d, xa=xa, xb=xb, ya=ya, yb=yb, sx=sx, sy=sy):
                # mais larga junto da caixa, afinando e arredondando na ponta
                pts = [(xa, ya), (xb + sx * lw * 0.35, ya), (xb + sx * lw * 0.05, ya + (yb - ya) * 0.6),
                       (xb - sx * lw * 0.05, yb - sy * lw * 0.3), (xa + sx * lw * 0.2, yb), (xa, yb - sy * lw * 0.2)]
                d.polygon(pts, fill=255)

            m = mask_from(shape, L.w, L.h, 1.2)
            u = np.clip((L.X - min(xa, xb)) / (lw * 1.35), 0, 1)
            if sx < 0:
                u = 1 - u
            along = np.clip(np.abs(L.Y - cy) / (R * 1.1), 0, 1)
            t = 0.30 + 0.55 * np.clip(np.sin(u * math.pi * 0.9 + 0.15), 0, 1) ** 1.2 + 0.15 * along
            L.over(mat_rgb(s.case, t), m)
            # sombra da alça sobre a pulseira
            L.darken(np.clip(1 - np.abs(L.X - xa) / (lw * 0.35), 0, 1) * (np.abs(L.Y - cy) > R * 0.6) * (np.abs(L.Y - cy) < R * 1.12) * 0.25 * (1 - m))


def draw_crown(L: Layer, s: Spec, cx, cy, R):
    x0, x1 = cx + R * 0.93, cx + R * 1.13
    h = R * 0.30
    m = mask_from(lambda d: d.rounded_rectangle([x0, cy - h / 2, x1, cy + h / 2], radius=4 * SS, fill=255), L.w, L.h)
    v = np.clip((L.Y - (cy - h / 2)) / h, 0, 1)
    knurl = 0.5 + 0.5 * np.cos((L.Y - cy) / (h / 14) * math.pi)
    t = 0.15 + 0.85 * np.clip(np.sin(v * math.pi), 0, 1) ** 0.8 * (0.65 + 0.35 * knurl)
    L.over(mat_rgb(s.case, t), m)
    if s.chrono:
        for ang in (60, 120):
            px, py = rot(cx, cy, ang, R * 1.04)
            pm = mask_from(lambda d, px=px, py=py, ang=ang: d.polygon(rect_poly(px, py, R * 0.20, R * 0.15, ang), fill=255), L.w, L.h)
            L.over(mat_rgb(s.case, np.full_like(L.A, 0.6) + 0.3 * np.sin(np.clip((L.Y - py) / (R * 0.15) + 0.5, 0, 1) * math.pi)), pm)


def rect_poly(cx, cy, length, width, ang_deg):
    a = math.radians(ang_deg)
    ux, uy = math.sin(a), -math.cos(a)
    vx, vy = -uy, ux
    hl, hw = length / 2, width / 2
    return [(cx + ux * hl + vx * hw, cy + uy * hl + vy * hw), (cx + ux * hl - vx * hw, cy + uy * hl - vy * hw),
            (cx - ux * hl - vx * hw, cy - uy * hl - vy * hw), (cx - ux * hl + vx * hw, cy - uy * hl + vy * hw)]


def conic(theta, a0=-2.3, lobes=2, sharp=1.6):
    v = 0.5 + 0.5 * np.cos(lobes * (theta - a0))
    return v ** sharp


def draw_case(L: Layer, s: Spec, cx, cy, R):
    r, th = polar(L, cx, cy)
    m = disc(L, cx, cy, R, 1.2)
    t = 0.30 + 0.60 * conic(th) + 0.10 * conic(th, 0.6, 1, 3)
    L.over(mat_rgb(s.case, t), m)
    # filete escuro na borda externa
    L.darken(ring(L, cx, cy, R - 2.5 * SS, R) * 0.45)

    bm = s.bezel_mat or s.case
    if s.bezel == "fluted":
        rin, rout = R * 0.80, R * 0.975
        mm = ring(L, cx, cy, rin, rout)
        flutes = 0.5 + 0.5 * np.cos(th * 96)
        rad = np.clip((r - rin) / (rout - rin), 0, 1)
        t = 0.25 + 0.45 * conic(th) + 0.35 * flutes * (0.4 + 0.6 * np.sin(rad * math.pi))
        L.over(mat_rgb(bm, t), mm)
    elif s.bezel in ("diver", "gmt", "graduated"):
        rin, rout = R * 0.78, R * 0.975
        mm = ring(L, cx, cy, rin, rout)
        if s.bezel == "diver":
            rgb = np.array((0.035, 0.035, 0.04), np.float32) + 0.10 * conic(th, -2.0, 2, 3)[..., None]
        elif s.bezel == "gmt":
            rgb = np.array((0.07, 0.15, 0.36), np.float32) * (0.75 + 0.5 * conic(th, -2.0, 2, 2))[..., None]
        else:
            rgb = mat_rgb("steel_brushed", 0.45 + 0.35 * conic(th))
        # serrilhado externo
        edge = ring(L, cx, cy, R * 0.955, R * 0.99) * (0.5 + 0.5 * np.cos(th * 120))
        L.over(rgb, mm)
        L.over(mat_rgb(s.case, 0.5 + 0.4 * conic(th)), edge * 0.9)
        draw_bezel_print(L, s, cx, cy, rin, rout)
    else:
        rin = R * (0.90 if s.bezel == "thin" else 0.86)
        mm = ring(L, cx, cy, rin, R * 0.985)
        rad = np.clip((r - rin) / (R * 0.985 - rin), 0, 1)
        t = 0.20 + 0.75 * conic(th, -2.0, 2, 2.2) * (0.5 + 0.5 * np.sin(rad * math.pi))
        L.over(mat_rgb(bm, t), mm)
    return (R * 0.78 if s.bezel in ("diver", "gmt", "graduated") else R * (0.80 if s.bezel == "fluted" else (0.90 if s.bezel == "thin" else 0.86)))


def draw_bezel_print(L: Layer, s: Spec, cx, cy, rin, rout):
    img = Image.new("L", (L.w, L.h), 0)
    d = ImageDraw.Draw(img)
    mid_out = rin + (rout - rin) * 0.88
    fsz = int((rout - rin) * 0.42)
    f = font("arialbd.ttf", fsz)
    if s.bezel == "diver":
        for i in range(60):
            ang = i * 6
            if i == 0:
                continue
            if i % 10 == 0:
                text_at(img, str(i), f, *rot(cx, cy, ang, (rin + rout) / 2), ang)
            elif i < 15 or i % 5 == 0:
                ln = 0.35 if i % 5 else 0.5
                d.line([rot(cx, cy, ang, mid_out), rot(cx, cy, ang, mid_out - (rout - rin) * ln)], fill=255, width=int(2.2 * SS))
        tri = [rot(cx, cy, 0, mid_out), rot(cx, cy, -5, rin + (rout - rin) * 0.22), rot(cx, cy, 5, rin + (rout - rin) * 0.22)]
        d.polygon(tri, fill=255)
    elif s.bezel == "gmt":
        for h in range(1, 24):
            ang = h * 15
            if h % 2 == 0:
                text_at(img, str(h), f, *rot(cx, cy, ang, (rin + rout) / 2), ang)
            else:
                d.ellipse([*(np.array(rot(cx, cy, ang, (rin + rout) / 2)) - 2.5 * SS), *(np.array(rot(cx, cy, ang, (rin + rout) / 2)) + 2.5 * SS)], fill=255)
        tri = [rot(cx, cy, 0, mid_out), rot(cx, cy, -5, rin + (rout - rin) * 0.22), rot(cx, cy, 5, rin + (rout - rin) * 0.22)]
        d.polygon(tri, fill=255)
    else:
        for i in range(0, 60, 5):
            ang = i * 6
            if i in (15, 30, 45):
                text_at(img, str(i), f, *rot(cx, cy, ang, (rin + rout) / 2), ang)
            elif i == 0:
                d.polygon([rot(cx, cy, 0, mid_out), rot(cx, cy, -5, rin + (rout - rin) * 0.25), rot(cx, cy, 5, rin + (rout - rin) * 0.25)], fill=255)
            else:
                d.line([rot(cx, cy, ang, mid_out), rot(cx, cy, ang, rin + (rout - rin) * 0.25)], fill=255, width=int(2.5 * SS))
    m = np.asarray(img.filter(ImageFilter.GaussianBlur(0.6)), np.float32) / 255
    color = (0.08, 0.08, 0.09) if s.bezel == "graduated" else (0.88, 0.88, 0.86)
    L.over(np.array(color, np.float32), m * 0.95)


def text_at(img: Image.Image, text: str, f, x, y, ang, fill=255):
    """Escreve texto centrado em (x, y), girado para acompanhar o aro."""
    bbox = f.getbbox(text)
    tw, th = bbox[2] - bbox[0], bbox[3] - bbox[1]
    t = Image.new("L", (tw + 8, th + 8), 0)
    ImageDraw.Draw(t).text((4 - bbox[0], 4 - bbox[1]), text, font=f, fill=fill)
    if ang:
        t = t.rotate(-ang if ang <= 90 or ang >= 270 else 180 - ang, resample=Image.BICUBIC, expand=True)
    img.paste(255, (int(x - t.width / 2), int(y - t.height / 2)), t)


def draw_dial(L: Layer, s: Spec, cx, cy, Rd):
    r, th = polar(L, cx, cy)
    m = disc(L, cx, cy, Rd, 1.0)
    base = np.array(s.dial, np.float32)
    if s.finish == "sunburst":
        k = 0.55 + 0.75 * conic(th, -2.2, 2, 1.4)
        streaks = noise(4096, 1, 1.5, 9)[0]
        idx = ((th + math.pi) / (2 * math.pi) * 4095).astype(np.int32)
        k = k * (0.94 + 0.12 * streaks[idx])
        rgb = base * k[..., None] + 0.05 * conic(th, -2.2, 2, 6)[..., None]
    elif s.finish == "guilloche":
        rings = 0.5 + 0.5 * np.cos(r / (2.2 * SS) * math.pi)
        rgb = base * (0.86 + 0.16 * rings + 0.10 * conic(th, -2.2, 2, 1))[..., None]
    elif s.finish == "lacquer":
        gloss = np.clip(1 - np.hypot(L.X - (cx - Rd * 0.35), L.Y - (cy - Rd * 0.45)) / (Rd * 0.9), 0, 1)
        rgb = base * (0.92 + 0.10 * conic(th, -2.2, 2, 1))[..., None] + 0.09 * (gloss ** 2)[..., None]
    else:  # fosco / opalino
        g = fbm(L.w, L.h, 2.0 * SS, 33, 2)
        amt = 0.05 if s.finish == "matte" else 0.03
        rgb = base * (0.97 + amt * (g - 0.5) * 2)[..., None] * (0.96 + 0.06 * (1 - r / max(Rd, 1)))[..., None]
    L.over(rgb, m)
    # sombra interna do aro (rehaut)
    L.darken(np.clip(1 - (Rd - r) / (Rd * 0.06), 0, 1) * m * 0.35)


def draw_print(L: Layer, s: Spec, cx, cy, Rd):
    img = Image.new("L", (L.w, L.h), 0)
    d = ImageDraw.Draw(img)
    ink = s.ink
    if s.indices != "minimal":
        for i in range(60):
            ang = i * 6
            r_out = Rd * 0.965
            ln = Rd * (0.05 if i % 5 == 0 else 0.03)
            if s.tachy:
                r_out = Rd * 0.93
            d.line([rot(cx, cy, ang, r_out), rot(cx, cy, ang, r_out - ln)], fill=255,
                   width=max(1, int((1.6 if i % 5 == 0 else 1.0) * SS)))
    if s.tachy:
        f = font("arial.ttf", int(Rd * 0.045))
        for val, sec in ((60, 60), (80, 45), (100, 36), (120, 30), (150, 24), (200, 18), (300, 12), (500, 7.2)):
            text_at(img, str(val), f, *rot(cx, cy, sec * 6, Rd * 0.965), sec * 6)
    # marca e linha do calibre
    f_brand = font("times.ttf", int(Rd * 0.105))
    spaced_text(img, "MORVANE", f_brand, cx, cy - Rd * 0.42, spacing=Rd * 0.018)
    f_small = font("arial.ttf", int(Rd * 0.043))
    y_label = cy - Rd * 0.27 if s.small_seconds else cy + Rd * 0.40
    if not s.chrono:
        spaced_text(img, s.label, f_small, cx, y_label, spacing=Rd * 0.012)
    m = np.asarray(img.filter(ImageFilter.GaussianBlur(0.55)), np.float32) / 255
    L.over(np.array(ink, np.float32), m * 0.92)
    # símbolo da casa: aro fino com marcação às 12h
    sym_y = cy - Rd * 0.58
    sr = Rd * 0.045
    sym = ring(L, cx, sym_y, sr - 1.2 * SS, sr + 0.6 * SS, 0.8)
    L.over(np.array(mat_color(s.index_mat, 0.8), np.float32) / 255, sym)


def spaced_text(img: Image.Image, text: str, f, cx, cy, spacing=0.0, fill=255):
    d = ImageDraw.Draw(img)
    widths = [f.getlength(ch) for ch in text]
    total = sum(widths) + spacing * (len(text) - 1)
    x = cx - total / 2
    asc = f.getbbox("M")
    y = cy - (asc[3] + asc[1]) / 2
    for ch, w in zip(text, widths):
        d.text((x, y), ch, font=f, fill=fill)
        x += w + spacing


def draw_indices(L: Layer, s: Spec, cx, cy, Rd):
    shadow = Image.new("L", (L.w, L.h), 0)
    sd = ImageDraw.Draw(shadow)
    light = Image.new("L", (L.w, L.h), 0)
    dark = Image.new("L", (L.w, L.h), 0)
    lume = Image.new("L", (L.w, L.h), 0)
    dl, dd, dlu = ImageDraw.Draw(light), ImageDraw.Draw(dark), ImageDraw.Draw(lume)
    skip = set()
    if s.date:
        skip.add(3)
    if s.chrono:
        skip.update({3, 9})
    if s.small_seconds:
        skip.add(6)

    if s.indices == "arabic":
        f = font("georgia.ttf", int(Rd * 0.20))
        for h, label in ((12, "12"), (3, "3"), (9, "9")):
            img = Image.new("L", (L.w, L.h), 0)
            x, y = rot(cx, cy, h * 30, Rd * 0.72)
            bbox = f.getbbox(label)
            ImageDraw.Draw(img).text((x - (bbox[2] + bbox[0]) / 2, y - (bbox[3] + bbox[1]) / 2), label, font=f, fill=255)
            m = np.asarray(img.filter(ImageFilter.GaussianBlur(0.6)), np.float32) / 255
            L.over(np.array((0.12, 0.12, 0.14), np.float32), m)
        for h in (1, 2, 4, 5, 7, 8, 10, 11):
            a, b = rot(cx, cy, h * 30, Rd * 0.86), rot(cx, cy, h * 30, Rd * 0.80)
            ImageDraw.Draw(light).line([a, b], fill=255, width=int(2.4 * SS))
        m = np.asarray(light.filter(ImageFilter.GaussianBlur(0.6)), np.float32) / 255
        L.over(np.array((0.15, 0.15, 0.17), np.float32), m)
        return

    for h in range(12):
        if h in skip or (h == 0 and False):
            continue
        ang = h * 30
        if s.indices == "minimal":
            a, b = rot(cx, cy, ang, Rd * 0.90), rot(cx, cy, ang, Rd * (0.80 if h % 3 == 0 else 0.84))
            dl.line([a, b], fill=255, width=int((2.6 if h % 3 == 0 else 1.8) * SS))
            continue
        if s.indices == "dot" and h not in (0, 3, 6, 9):
            px, py = rot(cx, cy, ang, Rd * 0.80)
            rr = Rd * 0.060
            dl.ellipse([px - rr, py - rr, px + rr, py + rr], fill=255)
            sd.ellipse([px - rr, py - rr, px + rr, py + rr], fill=255)
            rl = rr * 0.72
            dlu.ellipse([px - rl, py - rl, px + rl, py + rl], fill=255)
            continue
        length = Rd * (0.20 if s.indices == "dot" else 0.16)
        width = Rd * (0.075 if s.indices == "dot" else 0.040)
        if h == 0 and s.indices == "dot":
            tri = [rot(cx, cy, 0, Rd * 0.70), rot(cx, cy, -5.5, Rd * 0.90), rot(cx, cy, 5.5, Rd * 0.90)]
            dl.polygon(tri, fill=255)
            sd.polygon(tri, fill=255)
            c = np.mean(tri, axis=0)
            dlu.polygon([tuple(c + (np.array(p) - c) * 0.6) for p in tri], fill=255)
            continue
        centers = [Rd * 0.80]
        offsets = [0.0]
        if h == 0:
            offsets = [-width * 0.75, width * 0.75]
        for off in offsets:
            a = math.radians(ang)
            ox, oy = math.cos(a) * off, math.sin(a) * off
            px, py = rot(cx, cy, ang, centers[0])
            px, py = px + ox, py + oy
            poly = rect_poly(px, py, length, width, ang)
            sd.polygon(poly, fill=255)
            # faceta clara/escura dividida ao longo do comprimento
            half_l = rect_poly(px - math.cos(a) * width / 4, py - math.sin(a) * width / 4, length, width / 2, ang)
            half_r = rect_poly(px + math.cos(a) * width / 4, py + math.sin(a) * width / 4, length, width / 2, ang)
            dl.polygon(half_l, fill=255)
            dd.polygon(half_r, fill=255)
            if s.lume:
                dlu.polygon(rect_poly(px, py, length * 0.78, width * 0.48, ang), fill=255)

    blur = ImageFilter.GaussianBlur(0.7)
    shadow_m = np.asarray(shadow.filter(ImageFilter.GaussianBlur(3 * SS)), np.float32) / 255
    shadow_m = np.roll(np.roll(shadow_m, int(3 * SS), axis=0), int(2 * SS), axis=1)
    L.darken(shadow_m * 0.35)
    lm = np.asarray(light.filter(blur), np.float32) / 255
    dm = np.asarray(dark.filter(blur), np.float32) / 255
    if s.indices == "minimal":
        L.over(np.array(s.print_color or mat_color(s.index_mat, 0.3), np.float32) / (255 if not s.print_color else 1), lm)
        return
    L.over(np.array(mat_color(s.index_mat, 0.95), np.float32) / 255, lm)
    L.over(np.array(mat_color(s.index_mat, 0.45), np.float32) / 255, dm)
    if s.lume or s.indices == "dot":
        lu = np.asarray(lume.filter(blur), np.float32) / 255
        L.over(np.array((0.93, 0.92, 0.85), np.float32), lu)


def draw_subdials(L: Layer, s: Spec, cx, cy, Rd):
    positions = []
    if s.chrono:
        positions = [(270, s.chrono), (90, s.chrono)]
    if s.small_seconds:
        positions = [(180, tuple(min(1, c * 0.97) for c in s.dial))]
    for ang, color in positions:
        px, py = rot(cx, cy, ang, Rd * 0.42)
        rs = Rd * 0.25
        r, th = polar(L, px, py)
        m = disc(L, px, py, rs)
        rings_ = 0.5 + 0.5 * np.cos(r / (1.8 * SS) * math.pi)
        rgb = np.array(color, np.float32) * (0.90 + 0.12 * rings_)[..., None]
        L.darken(ring(L, px, py, rs, rs + 2 * SS) * 0.3)
        L.over(rgb, m)
        light_sub = sum(color) / 3 > 0.5
        ink = (0.15, 0.15, 0.16) if light_sub else (0.88, 0.88, 0.86)
        img = Image.new("L", (L.w, L.h), 0)
        d = ImageDraw.Draw(img)
        for i in range(30 if not s.small_seconds else 60):
            step = 12 if not s.small_seconds else 6
            a = i * step
            ln = rs * (0.16 if i % 5 == 0 else 0.08)
            d.line([rot(px, py, a, rs * 0.92), rot(px, py, a, rs * 0.92 - ln)], fill=255, width=max(1, int(1.2 * SS)))
        mm = np.asarray(img.filter(ImageFilter.GaussianBlur(0.5)), np.float32) / 255
        L.over(np.array(ink, np.float32), mm)
        # ponteiro pequeno
        hand_ang = 200 if ang == 270 else 40
        hand = Image.new("L", (L.w, L.h), 0)
        hd = ImageDraw.Draw(hand)
        tip = rot(px, py, hand_ang, rs * 0.85)
        tail = rot(px, py, hand_ang + 180, rs * 0.2)
        hd.line([tail, tip], fill=255, width=int(2.2 * SS))
        hd.ellipse([px - 4 * SS, py - 4 * SS, px + 4 * SS, py + 4 * SS], fill=255)
        hm = np.asarray(hand.filter(ImageFilter.GaussianBlur(0.6)), np.float32) / 255
        hc = (0.12, 0.12, 0.13) if light_sub else (0.92, 0.92, 0.90)
        L.over(np.array(hc, np.float32), hm)


def draw_date(L: Layer, s: Spec, cx, cy, Rd):
    px, py = rot(cx, cy, 90, Rd * 0.70)
    w, h = Rd * 0.15, Rd * 0.12
    frame = mask_from(lambda d: d.rectangle([px - w / 2 - 3 * SS, py - h / 2 - 3 * SS, px + w / 2 + 3 * SS, py + h / 2 + 3 * SS], fill=255), L.w, L.h)
    L.over(np.array(mat_color(s.index_mat, 0.8), np.float32) / 255, frame)
    win = mask_from(lambda d: d.rectangle([px - w / 2, py - h / 2, px + w / 2, py + h / 2], fill=255), L.w, L.h)
    L.over(np.array((0.95, 0.94, 0.92), np.float32), win)
    img = Image.new("L", (L.w, L.h), 0)
    f = font("arial.ttf", int(h * 0.72))
    bbox = f.getbbox("14")
    ImageDraw.Draw(img).text((px - (bbox[2] + bbox[0]) / 2, py - (bbox[3] + bbox[1]) / 2), "14", font=f, fill=255)
    L.over(np.array((0.08, 0.08, 0.09), np.float32), np.asarray(img.filter(ImageFilter.GaussianBlur(0.5)), np.float32) / 255)


def hand_polys(kind, cx, cy, ang, length, width, tail):
    a = math.radians(ang)
    ux, uy = math.sin(a), -math.cos(a)
    vx, vy = -uy, ux
    P = lambda along, across: (cx + ux * along + vx * across, cy + uy * along + vy * across)
    if kind in ("dauphine", "leaf"):
        mid = length * (0.18 if kind == "dauphine" else 0.45)
        left = [P(-tail, 0), P(mid, -width / 2), P(length, 0), P(0, 0)]
        right = [P(-tail, 0), P(mid, width / 2), P(length, 0), P(0, 0)]
        if kind == "leaf":
            left = [P(-tail, 0)] + [P(length * t, -width / 2 * math.sin(math.pi * min(1, t * 1.05)) ** 0.8) for t in np.linspace(0.05, 1, 14)]
            right = [P(-tail, 0)] + [P(length * t, width / 2 * math.sin(math.pi * min(1, t * 1.05)) ** 0.8) for t in np.linspace(0.05, 1, 14)]
        return left, right, None
    if kind == "sword":
        left = [P(-tail, 0), P(-tail, -width * 0.25), P(length * 0.15, -width / 2), P(length * 0.88, -width / 2), P(length, 0), P(length * 0.5, 0)]
        right = [P(-tail, 0), P(-tail, width * 0.25), P(length * 0.15, width / 2), P(length * 0.88, width / 2), P(length, 0), P(length * 0.5, 0)]
        lume = [P(length * 0.22, -width * 0.30), P(length * 0.82, -width * 0.30), P(length * 0.92, 0), P(length * 0.82, width * 0.30), P(length * 0.22, width * 0.30)]
        return left, right, lume
    # baton
    left = [P(-tail, -width / 2), P(length, -width / 2), P(length, 0), P(-tail, 0)]
    right = [P(-tail, 0), P(length, 0), P(length, width / 2), P(-tail, width / 2)]
    lume = [P(length * 0.25, -width * 0.28), P(length * 0.95, -width * 0.28), P(length * 0.95, width * 0.28), P(length * 0.25, width * 0.28)]
    return left, right, lume


def draw_hands(L: Layer, s: Spec, cx, cy, Rd):
    hour_a, min_a, sec_a = 304.8, 57.6, 216.0
    layers = []
    specs = [("hour", hour_a, Rd * 0.55, Rd * 0.085), ("min", min_a, Rd * 0.86, Rd * 0.065)]
    if s.hands == "baton":
        specs = [("hour", hour_a, Rd * 0.55, Rd * 0.050), ("min", min_a, Rd * 0.86, Rd * 0.040)]
    shadow = Image.new("L", (L.w, L.h), 0)
    sd = ImageDraw.Draw(shadow)
    for name, ang, length, width in specs:
        left, right, lume = hand_polys(s.hands, cx, cy, ang, length, width, Rd * 0.10)
        sd.polygon(left, fill=255)
        sd.polygon(right, fill=255)
        layers.append((left, right, lume))
    gmt = None
    if s.gmt_hand:
        a = math.radians(160)
        tip = rot(cx, cy, 160, Rd * 0.88)
        gmt = (rot(cx, cy, 160, -Rd * 0.1), tip)
        sd.line(gmt, fill=255, width=int(2.5 * SS))
    sec = None
    if s.seconds:
        sec = (rot(cx, cy, sec_a, -Rd * 0.22), rot(cx, cy, sec_a, Rd * 0.93))
        sd.line(sec, fill=255, width=int(1.8 * SS))

    sm = np.asarray(shadow.filter(ImageFilter.GaussianBlur(5 * SS)), np.float32) / 255
    sm = np.roll(np.roll(sm, int(10 * SS), axis=0), int(6 * SS), axis=1)
    L.darken(sm * 0.38)

    for left, right, lume in layers:
        ml = mask_from(lambda d, p=left: d.polygon(p, fill=255), L.w, L.h)
        mr = mask_from(lambda d, p=right: d.polygon(p, fill=255), L.w, L.h)
        L.over(np.array(mat_color(s.hand_mat, 0.95), np.float32) / 255, ml)
        L.over(np.array(mat_color(s.hand_mat, 0.40), np.float32) / 255, mr)
        if s.lume and lume:
            L.over(np.array((0.93, 0.92, 0.85), np.float32), mask_from(lambda d, p=lume: d.polygon(p, fill=255), L.w, L.h))
    if gmt:
        gm = mask_from(lambda d: (d.line(gmt, fill=255, width=int(2.5 * SS)),
                                  d.polygon([rot(cx, cy, 160, Rd * 0.92), rot(cx, cy, 155, Rd * 0.78), rot(cx, cy, 165, Rd * 0.78)], fill=255)), L.w, L.h)
        L.over(np.array(s.gmt_hand, np.float32), gm)
    if sec:
        col = np.array(s.seconds, np.float32)
        smk = mask_from(lambda d: (d.line(sec, fill=255, width=int(1.8 * SS)),
                                   d.ellipse([cx - 7 * SS, cy - 7 * SS, cx + 7 * SS, cy + 7 * SS], fill=255)), L.w, L.h)
        L.over(col, smk)
    cap = disc(L, cx, cy, 5 * SS)
    L.over(np.array(mat_color(s.hand_mat, 0.85), np.float32) / 255, cap)


def draw_crystal(L: Layer, cx, cy, R):
    r, th = polar(L, cx, cy)
    inside = disc(L, cx, cy, R * 0.96)
    spot = np.clip(1 - np.hypot(L.X - (cx - R * 0.45), L.Y - (cy - R * 0.55)) / (R * 0.75), 0, 1) ** 2
    L.lighten(spot * inside * 0.10)
    arc = ring(L, cx, cy, R * 0.90, R * 0.93, 2.0) * np.clip(np.cos(th + 2.3), 0, 1) ** 4
    L.lighten(arc * 0.22)


def render_watch(s: Spec, w: int, h: int, cx: float, cy: float, R: float, strap_top=None, strap_bottom=None) -> Layer:
    L = Layer(w, h)
    top = strap_top if strap_top is not None else -10
    bottom = strap_bottom if strap_bottom is not None else h + 10
    draw_strap(L, s, cx, cy, R, top, bottom)
    draw_lugs(L, s, cx, cy, R)
    draw_crown(L, s, cx, cy, R)
    Rd = draw_case(L, s, cx, cy, R)
    Rd *= 0.985
    draw_dial(L, s, cx, cy, Rd)
    draw_subdials(L, s, cx, cy, Rd)
    draw_print(L, s, cx, cy, Rd)
    draw_indices(L, s, cx, cy, Rd)
    if s.date:
        draw_date(L, s, cx, cy, Rd)
    draw_hands(L, s, cx, cy, Rd)
    draw_crystal(L, cx, cy, Rd)
    return L


# ---------------------------------------------------------------- fundos
def studio_background(w: int, h: int, top=(0.95, 0.93, 0.89), bottom=(0.86, 0.83, 0.78), seed=1) -> np.ndarray:
    yy, xx = np.mgrid[0:h, 0:w].astype(np.float32)
    v = yy / h
    bg = np.array(top, np.float32) * (1 - v[..., None]) + np.array(bottom, np.float32) * v[..., None]
    spot = np.clip(1 - np.hypot(xx - w * 0.45, yy - h * 0.40) / (max(w, h) * 0.75), 0, 1)
    bg *= (0.93 + 0.09 * spot)[..., None]
    grain = noise(w, h, 1.0, seed) - 0.5
    bg += (grain * 0.018)[..., None]
    return np.clip(bg, 0, 1)


def composite(bg: np.ndarray, L: Layer) -> np.ndarray:
    return L.C + bg * (1 - L.A[..., None])


def add_shadow(bg: np.ndarray, alpha: np.ndarray, dx, dy, blur, strength) -> np.ndarray:
    m = Image.fromarray((np.clip(alpha, 0, 1) * 255).astype(np.uint8)).filter(ImageFilter.GaussianBlur(blur))
    m = np.asarray(m, np.float32) / 255
    m = np.roll(np.roll(m, int(dy), axis=0), int(dx), axis=1)
    return bg * (1 - strength * m)[..., None]


def save(arr_or_img, path: Path, size=None, quality=84):
    path.parent.mkdir(parents=True, exist_ok=True)
    img = arr_or_img if isinstance(arr_or_img, Image.Image) else Image.fromarray((np.clip(arr_or_img, 0, 1) * 255 + 0.5).astype(np.uint8))
    if size:
        img = img.resize(size, Image.LANCZOS)
    img.save(path, "WEBP", quality=quality, method=6)
    print("  ", path.relative_to(ROOT), img.size)


def depth_of_field(arr: np.ndarray, fx, fy, radius, max_blur) -> np.ndarray:
    img = Image.fromarray((np.clip(arr, 0, 1) * 255).astype(np.uint8))
    blurred = np.asarray(img.filter(ImageFilter.GaussianBlur(max_blur)), np.float32) / 255
    h, w = arr.shape[:2]
    yy, xx = np.mgrid[0:h, 0:w].astype(np.float32)
    d = np.clip((np.hypot(xx - fx, yy - fy) - radius) / (radius * 1.2), 0, 1)
    return arr * (1 - d[..., None]) + blurred * d[..., None]


# ---------------------------------------------------------------- saídas por produto
FW, FH = 1000, 1250  # tamanho final das fotos de produto (4:5)


def product_images(s: Spec):
    print(s.slug)
    w, h = FW * SS, FH * SS
    R = w * 0.30 * (s.diameter / 40) ** 0.55
    cx, cy = w * 0.5, h * 0.47

    # 1) frente, fundo de estúdio claro
    L = render_watch(s, w, h, cx, cy, R)
    bg = studio_background(w, h, seed=hash(s.slug) % 1000)
    bg = add_shadow(bg, L.A, 14 * SS, 26 * SS, 22 * SS, 0.30)
    front = composite(bg, L)
    save(Image.fromarray((np.clip(front, 0, 1) * 255).astype(np.uint8)), OUT_PRODUCTS / f"{s.slug}.webp", (FW, FH))
    save(Image.fromarray((np.clip(front, 0, 1) * 255).astype(np.uint8)), OUT_PRODUCTS / f"{s.slug}-thumb.webp", (480, 600), 80)

    # 2) macro do mostrador: relógio 2,6x maior, foco entre o centro e as 2h
    Rm = R * 2.6
    Lm = render_watch(s, w, h, w * 0.30, h * 0.70, Rm)
    macro = composite(studio_background(w, h, (0.2, 0.2, 0.2), (0.1, 0.1, 0.1)), Lm)
    macro = depth_of_field(macro, w * 0.52, h * 0.42, w * 0.22, 9 * SS)
    save(macro, OUT_PRODUCTS / f"{s.slug}-2.webp", (FW, FH))

    # 4) recorte transparente para o hero (pulseira esmaecendo nas pontas)
    Lh = render_watch(s, w, h, cx, h * 0.5, R * 1.05)
    fade = np.clip(np.minimum(Lh.Y / (h * 0.24), (h - Lh.Y) / (h * 0.24)), 0, 1) ** 1.4
    Lh.C *= fade[..., None]
    Lh.A *= fade
    hero_img = Lh.to_image()
    save(hero_img, OUT_PRODUCTS / f"{s.slug}-hero.webp", (FW, FH), quality=88)

    # 3) vista inclinada sobre fundo escuro quente
    tilted = perspective_tilt(hero_img, 0.20)
    bg3 = studio_background(w, h, (0.24, 0.22, 0.20), (0.11, 0.10, 0.09), seed=7)
    t_alpha = np.asarray(tilted.split()[3], np.float32) / 255
    bg3 = add_shadow(bg3, t_alpha, 30 * SS, 40 * SS, 30 * SS, 0.55)
    t_rgb = np.asarray(tilted.convert("RGB"), np.float32) / 255
    out3 = t_rgb * t_alpha[..., None] + bg3 * (1 - t_alpha[..., None])
    save(out3, OUT_PRODUCTS / f"{s.slug}-3.webp", (FW, FH))
    return L


def perspective_tilt(img: Image.Image, amount: float) -> Image.Image:
    """Inclina o topo para trás (trapézio) para simular a peça deitada."""
    w, h = img.size
    inset = w * amount
    src = [(0, 0), (w, 0), (w, h), (0, h)]
    dst = [(inset, h * 0.12), (w - inset, h * 0.12), (w + inset * 0.15, h * 0.96), (-inset * 0.15, h * 0.96)]
    coeffs = find_coeffs(dst, src)
    return img.transform((w, h), Image.PERSPECTIVE, coeffs, Image.BICUBIC)


def find_coeffs(pa, pb):
    matrix = []
    for p1, p2 in zip(pa, pb):
        matrix.append([p1[0], p1[1], 1, 0, 0, 0, -p2[0] * p1[0], -p2[0] * p1[1]])
        matrix.append([0, 0, 0, p1[0], p1[1], 1, -p2[1] * p1[0], -p2[1] * p1[1]])
    A = np.array(matrix, dtype=np.float64)
    B = np.array(pb, dtype=np.float64).reshape(8)
    return np.linalg.solve(A, B).tolist()


# ---------------------------------------------------------------- editoriais
def editorial_dial_macro():
    s = SPEC_BY_SLUG["heritage-verde-40"]
    w, h = 1200 * SS // 2 * 2, 1500 * SS // 2 * 2
    R = w * 0.95
    L = render_watch(s, w, h, w * 0.08, h * 0.80, R)
    arr = composite(np.zeros((h, w, 3), np.float32), L)
    arr = depth_of_field(arr, w * 0.48, h * 0.42, w * 0.20, 10 * SS)
    save(arr, OUT_EDITORIAL / "macro-mostrador.webp", (1200, 1500))


def editorial_crown():
    """Perfil lateral: carrura, luneta serrilhada e coroa de frente, sobre azul profundo."""
    w, h = 1200 * SS, 1500 * SS
    L = Layer(w, h)
    yy = L.Y / h
    bg = np.array((0.03, 0.07, 0.13), np.float32) * (0.45 + 0.9 * np.clip(1 - np.abs(yy - 0.42) * 1.6, 0, 1))[..., None]
    bg += (noise(w, h, 1, 3) - 0.5)[..., None] * 0.012
    cy = h * 0.52
    band_h = h * 0.21
    # carrura (corpo lateral da caixa)
    band = mask_from(lambda d: d.rounded_rectangle([-50, cy - band_h / 2, w + 50, cy + band_h / 2], radius=int(band_h * 0.4), fill=255), w, h)
    v = np.clip((L.Y - (cy - band_h / 2)) / band_h, 0, 1)
    t = 0.10 + 0.9 * np.exp(-((v - 0.32) ** 2) / 0.02) + 0.25 * np.exp(-((v - 0.85) ** 2) / 0.01)
    L.over(mat_rgb("steel", t * 0.8), band)
    # fundo (caseback) mais estreito
    back = mask_from(lambda d: d.rectangle([w * 0.08, cy + band_h * 0.5, w * 0.92, cy + band_h * 0.78], fill=255), w, h)
    vb = np.clip((L.Y - (cy + band_h * 0.5)) / (band_h * 0.28), 0, 1)
    L.over(mat_rgb("steel_brushed", 0.15 + 0.4 * (1 - vb) * (0.8 + 0.2 * np.cos(L.X / (6 * SS)))), back)
    # luneta serrilhada em ouro
    bz_top, bz_h = cy - band_h / 2 - h * 0.07, h * 0.07
    bez = mask_from(lambda d: d.rectangle([-10, bz_top, w + 10, bz_top + bz_h], fill=255), w, h)
    teeth = 0.5 + 0.5 * np.cos(L.X / (w / 44) * 2 * math.pi)
    vz = np.clip((L.Y - bz_top) / bz_h, 0, 1)
    tz = 0.12 + 0.85 * teeth ** 1.5 * np.clip(np.sin(vz * math.pi), 0, 1) ** 0.6
    L.over(mat_rgb("gold", tz), bez)
    # cristal (fino brilho acima da luneta)
    cr = mask_from(lambda d: d.rectangle([w * 0.03, bz_top - h * 0.018, w * 0.97, bz_top], fill=255), w, h)
    L.over(np.array((0.55, 0.62, 0.70), np.float32) * 0.5, cr * 0.5)
    # coroa de frente
    ccx, ccy, rc = w * 0.5, cy + band_h * 0.05, band_h * 0.62
    r, th = polar(L, ccx, ccy)
    crown = disc(L, ccx, ccy, rc * (1 + 0.035 * np.cos(th * 30)))
    t = 0.25 + 0.6 * conic(th, -2.2, 1, 1.4) + 0.15 * (0.5 + 0.5 * np.cos(th * 30))
    L.darken(disc(L, ccx + 10 * SS, ccy + 16 * SS, rc * 1.08, 30 * SS) * 0.5)
    L.over(mat_rgb("gold", t), crown)
    face = disc(L, ccx, ccy, rc * 0.78)
    tf = 0.35 + 0.45 * np.clip(1 - np.hypot(L.X - (ccx - rc * 0.3), L.Y - (ccy - rc * 0.35)) / (rc * 1.4), 0, 1)
    L.over(mat_rgb("gold", tf), face)
    # símbolo gravado: aro com marca às 12h
    L.over(mat_rgb("gold", np.full_like(L.A, 0.15)), ring(L, ccx, ccy, rc * 0.36, rc * 0.42))
    tick = mask_from(lambda d: d.rectangle([ccx - rc * 0.03, ccy - rc * 0.52, ccx + rc * 0.03, ccy - rc * 0.30], fill=255), w, h)
    L.over(mat_rgb("gold", np.full_like(L.A, 0.15)), tick)
    arr = composite(bg, L)
    arr = depth_of_field(arr, w * 0.5, cy, w * 0.30, 6 * SS)
    save(arr, OUT_EDITORIAL / "macro-coroa.webp", (1200, 1500))


def editorial_clasp():
    """Fecho da pulseira em macro, com elos polidos e escovados e foco curto."""
    s = Spec("fecho", strap="bracelet2")
    w, h = 1200 * SS, 1500 * SS
    L = Layer(w, h)
    sw = w * 0.9
    full = np.ones((h, w), np.float32)
    draw_bracelet(L, s, w * 0.05, w * 0.95, 0, h, sw, full, 1)
    # placa do fecho
    px0, px1, py0, py1 = w * 0.22, w * 0.78, h * 0.40, h * 0.60
    plate = mask_from(lambda d: d.rounded_rectangle([px0, py0, px1, py1], radius=int(30 * SS), fill=255), w, h)
    L.darken(mask_from(lambda d: d.rounded_rectangle([px0 + 20 * SS, py0 + 30 * SS, px1 + 20 * SS, py1 + 30 * SS], radius=40 * SS, fill=255), w, h, 30 * SS) * 0.7)
    v = np.clip((L.Y - py0) / (py1 - py0), 0, 1)
    u = np.clip((L.X - px0) / (px1 - px0), 0, 1)
    t = 0.25 + 0.7 * np.clip(np.sin(v * math.pi), 0, 1) ** 0.5 * (0.7 + 0.3 * np.sin(u * math.pi))
    L.over(mat_rgb("gold", t), plate)
    img = Image.new("L", (w, h), 0)
    spaced_text(img, "MORVANE", font("times.ttf", int(h * 0.05)), w / 2, (py0 + py1) / 2, spacing=w * 0.015)
    engr = np.asarray(img.filter(ImageFilter.GaussianBlur(1.2)), np.float32) / 255
    L.darken(engr * 0.55)
    arr = composite(np.zeros((h, w, 3), np.float32), L)
    arr = arr * (0.75 + 0.45 * np.clip(1 - np.abs(L.Y / h - 0.5) * 1.6, 0, 1))[..., None]
    # inclinação para dar profundidade e foco seletivo
    img = Image.fromarray((np.clip(arr, 0, 1) * 255).astype(np.uint8)).convert("RGBA")
    img = perspective_tilt(img, 0.18)
    rgb = np.asarray(img.convert("RGB"), np.float32) / 255
    a = np.asarray(img.split()[3], np.float32) / 255
    bg = studio_background(w, h, (0.10, 0.09, 0.08), (0.04, 0.04, 0.04))
    out = rgb * a[..., None] + bg * (1 - a[..., None])
    out = depth_of_field(out, w * 0.5, h * 0.52, h * 0.10, 12 * SS)
    save(out, OUT_EDITORIAL / "macro-fecho.webp", (1200, 1500))


def marble(w, h, seed=4) -> np.ndarray:
    turb = fbm(w, h, w / 3, seed, 5)
    yy, xx = np.mgrid[0:h, 0:w].astype(np.float32)
    v = np.abs(np.sin((xx * 0.9 + yy * 0.5) / (w / 5) + turb * 9))
    veins = np.clip(1 - v / 0.06, 0, 1) ** 1.5
    turb2 = fbm(w, h, w / 6, seed + 50, 4)
    v2 = np.abs(np.sin((xx * 0.3 - yy) / (w / 7) + turb2 * 11))
    veins2 = np.clip(1 - v2 / 0.03, 0, 1)
    base = np.array((0.05, 0.17, 0.12), np.float32) * (0.6 + 0.6 * fbm(w, h, w / 10, seed + 9, 4))[..., None]
    col = base + np.array((0.55, 0.60, 0.52), np.float32) * (0.75 * veins + 0.35 * veins2)[..., None]
    return np.clip(col, 0, 1)


def editorial_campaign():
    """Cena quente: peça Heritage Verde em almofada de camurça sobre mármore verde."""
    w, h = 1600 * SS, 1200 * SS
    L = Layer(w, h)
    yy, xx = L.Y / h, L.X / w
    # parede escura com luz quente lateral
    bg = np.array((0.06, 0.05, 0.04), np.float32) * (0.6 + 1.6 * np.clip(1 - np.hypot(xx - 0.18, yy - 0.30) / 0.9, 0, 1) ** 2)[..., None]
    bg += (np.array((0.50, 0.32, 0.12), np.float32) * (np.clip(1 - np.hypot(xx - 0.12, yy - 0.28) / 0.35, 0, 1) ** 3 * 0.35)[..., None])
    # brilhos de vela desfocados
    bokeh = Image.new("L", (w, h), 0)
    bd = ImageDraw.Draw(bokeh)
    for bx, by, br in ((0.10, 0.22, 0.020), (0.16, 0.30, 0.012), (0.88, 0.18, 0.016), (0.80, 0.26, 0.010)):
        bd.ellipse([w * (bx - br), h * by - w * br, w * (bx + br), h * by + w * br], fill=255)
    bk = np.asarray(bokeh.filter(ImageFilter.GaussianBlur(14 * SS)), np.float32) / 255
    bg += np.array((1.0, 0.72, 0.38), np.float32) * (bk * 0.55)[..., None]
    # mármore na parte de baixo, com perspectiva simples
    top = h * 0.62
    mb = marble(w, int(h - top) * 2, 12)
    mimg = Image.fromarray((mb * 255).astype(np.uint8)).resize((w, int(h - top)), Image.BICUBIC)
    marr = np.asarray(mimg, np.float32) / 255
    fall = np.linspace(0.45, 1.0, marr.shape[0])[:, None, None]
    bg[int(top):] = marr * fall * (0.7 + 0.5 * np.clip(1 - np.abs(np.linspace(0, 1, w) - 0.45) / 0.7, 0, 1))[None, :, None]
    # base de veludo verde
    cx = w * 0.56
    base_w, base_h = w * 0.40, h * 0.13
    by0 = h * 0.66
    vel = mask_from(lambda d: d.rounded_rectangle([cx - base_w / 2, by0, cx + base_w / 2, by0 + base_h], radius=10 * SS, fill=255), w, h)
    vn = fbm(w, h, 2 * SS, 41, 2)
    vel_rgb = np.array((0.04, 0.20, 0.13), np.float32) * (0.75 + 0.35 * vn)[..., None] * (0.7 + 0.5 * np.clip(1 - (L.X - (cx - base_w / 2)) / base_w, 0, 1))[..., None]
    bg = add_shadow(bg, vel, 40 * SS, 30 * SS, 40 * SS, 0.6)
    bg = bg * (1 - vel[..., None]) + vel_rgb * vel[..., None]
    # almofada de camurça
    cw, ch = w * 0.32, h * 0.42
    cy0 = by0 - ch * 0.82
    cush = mask_from(lambda d: d.rounded_rectangle([cx - cw / 2, cy0, cx + cw / 2, cy0 + ch], radius=int(cw * 0.22), fill=255), w, h)
    u = np.clip((L.X - (cx - cw / 2)) / cw, 0, 1)
    suede = fbm(w, h, 1.5 * SS, 77, 3)
    c_rgb = np.array((0.62, 0.50, 0.37), np.float32) * (0.45 + 0.75 * np.clip(np.sin(u * math.pi), 0, 1) ** 0.6 * (1 - 0.45 * u))[..., None] * (0.88 + 0.2 * suede)[..., None]
    bg = bg * (1 - cush[..., None]) + c_rgb * cush[..., None]
    # relógio sobre a almofada
    s = SPEC_BY_SLUG["heritage-verde-40"]
    R = h * 0.12
    Lw = render_watch(s, w, h, cx, cy0 + ch * 0.48, R, strap_top=cy0 + 6 * SS, strap_bottom=cy0 + ch - 6 * SS)
    bg = add_shadow(bg, Lw.A, 18 * SS, 14 * SS, 14 * SS, 0.55)
    out = composite(bg, Lw)
    # luz quente vinda da esquerda e vinheta
    warm = np.clip(1 - xx / 1.1, 0, 1)
    out = out * (1 + np.array((0.22, 0.10, -0.04), np.float32) * warm[..., None])
    vig = np.clip(1 - np.hypot(xx - 0.5, yy - 0.5) / 0.85, 0, 1) ** 0.6
    out *= (0.35 + 0.65 * vig)[..., None]
    out += (noise(w, h, 1, 99) - 0.5)[..., None] * 0.02
    save(out, OUT_EDITORIAL / "campanha-heritage.webp", (1600, 1200))


def placeholder_svg():
    svg = (
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 1000" width="800" height="1000">'
        '<rect width="800" height="1000" fill="#EFEBE3"/>'
        '<circle cx="400" cy="470" r="170" fill="none" stroke="#B99A62" stroke-width="1.5"/>'
        '<line x1="400" y1="300" x2="400" y2="325" stroke="#B99A62" stroke-width="1.5"/>'
        '<text x="400" y="720" text-anchor="middle" font-family="Georgia, serif" font-size="30" letter-spacing="9" fill="#6B665E">MORVANE</text>'
        '</svg>'
    )
    (ROOT / "assets" / "img" / "placeholder.svg").write_text(svg, encoding="utf-8")


if __name__ == "__main__":
    args = sys.argv[1:]
    OUT_PRODUCTS.mkdir(parents=True, exist_ok=True)
    OUT_EDITORIAL.mkdir(parents=True, exist_ok=True)
    targets = [SPEC_BY_SLUG[a] for a in args if a in SPEC_BY_SLUG] if args else SPECS
    for spec in targets:
        product_images(spec)
    if not args or "editorial" in args:
        editorial_dial_macro()
        editorial_crown()
        editorial_clasp()
        editorial_campaign()
        placeholder_svg()
