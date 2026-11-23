"""
Ensaio "Fim de semana" — cenas fotorrealistas em 3D (Blender/Cycles).

Requer o Blender como módulo Python (só para gerar as imagens; o site não depende disso):
    python -m venv .venv-render && .venv-render/bin/pip install bpy numpy pillow
    .venv-render/bin/python tools/render_ensaio.py                    # as três cenas
    .venv-render/bin/python tools/render_ensaio.py xadrez             # xadrez | clube | campo
    .venv-render/bin/python tools/render_ensaio.py xadrez --preview   # rápido, baixa resolução

Os relógios são modelados em 3D (caixa, luneta, cristal, ponteiros, pulseira de elos); o mostrador
usa a arte de tools/render_watches.py como textura. Nenhuma marca real, nenhuma foto externa.
Saída: assets/img/editorial/ensaio-*.webp
"""
from __future__ import annotations

import math
import sys
import tempfile
from pathlib import Path

import bpy  # noqa: I001  (precisa vir antes de addon_utils/bmesh)
import addon_utils
import bmesh
import numpy as np
from mathutils import Euler, Vector
from PIL import Image, ImageDraw, ImageFilter

sys.path.insert(0, str(Path(__file__).resolve().parent))
import render_watches as rw  # noqa: E402

OUT = rw.OUT_EDITORIAL
CACHE = Path(tempfile.gettempdir()) / "aquino-ensaio"
CACHE.mkdir(exist_ok=True)
PREVIEW = "--preview" in sys.argv


# =====================================================================================
# cena e utilidades de malha
# =====================================================================================
def reset_scene():
    bpy.ops.wm.read_factory_settings(use_empty=True)
    addon_utils.enable("cycles", default_set=True)
    sc = bpy.context.scene
    sc.render.engine = "CYCLES"
    sc.cycles.device = "CPU"
    sc.cycles.use_denoising = True
    sc.cycles.denoiser = "OPENIMAGEDENOISE"
    sc.cycles.max_bounces = 10
    sc.cycles.glossy_bounces = 6
    sc.cycles.transmission_bounces = 8
    sc.cycles.caustics_reflective = False
    sc.cycles.caustics_refractive = False
    sc.cycles.blur_glossy = 1.0
    sc.cycles.sample_clamp_indirect = 8.0
    sc.cycles.adaptive_threshold = 0.015
    sc.view_settings.view_transform = "AgX"
    try:
        sc.view_settings.look = "AgX - Medium High Contrast"
    except TypeError:
        pass
    return sc


def link(obj, parent=None):
    bpy.context.scene.collection.objects.link(obj)
    if parent is not None:
        obj.parent = parent
    return obj


def add_split(ob, angle):
    m = ob.modifiers.new("split", "EDGE_SPLIT")
    m.split_angle = math.radians(angle)


def mesh_obj(name, verts, faces, mat=None, smooth=True, parent=None, split=None):
    me = bpy.data.meshes.new(name)
    me.from_pydata([tuple(v) for v in verts], [], [tuple(f) for f in faces])
    me.validate()
    me.update()
    ob = bpy.data.objects.new(name, me)
    if mat is not None:
        me.materials.append(mat)
    if smooth:
        for p in me.polygons:
            p.use_smooth = True
    link(ob, parent)
    if split is not None:
        add_split(ob, split)
    return ob


def bevel(ob, width, segments=3, angle=40):
    """Chanfro; o edge split (se houver) é refeito depois dele."""
    split = ob.modifiers.get("split")
    split_angle = split.split_angle if split else None
    if split:
        ob.modifiers.remove(split)
    m = ob.modifiers.new("bevel", "BEVEL")
    m.width = width
    m.segments = segments
    m.limit_method = "ANGLE"
    m.angle_limit = math.radians(angle)
    if split_angle is not None:
        add_split(ob, math.degrees(split_angle))
    return ob


def recalc_normals(ob):
    bmx = bmesh.new()
    bmx.from_mesh(ob.data)
    bmesh.ops.recalc_face_normals(bmx, faces=bmx.faces)
    bmx.to_mesh(ob.data)
    bmx.free()


def lathe(name, prof, mat, segs=128, parent=None, offset=(0, 0, 0), split=38, rfun=None, caps=True):
    """Sólido de revolução em torno de Z. prof = [(r, z), ...] de baixo para cima."""
    verts, faces = [], []
    n = len(prof)
    for i in range(segs):
        a = 2 * math.pi * i / segs
        ca, sa = math.cos(a), math.sin(a)
        k = rfun(a) if rfun else 1.0
        for r, z in prof:
            verts.append((offset[0] + r * k * ca, offset[1] + r * k * sa, offset[2] + z))
    for i in range(segs):
        j = (i + 1) % segs
        for k in range(n - 1):
            a, b = i * n + k, j * n + k
            faces.append((a, b, b + 1, a + 1))
    if caps and prof[0][0] > 1e-7:
        c = len(verts)
        verts.append((offset[0], offset[1], offset[2] + prof[0][1]))
        for i in range(segs):
            j = (i + 1) % segs
            faces.append((c, j * n, i * n))
    if caps and prof[-1][0] > 1e-7:
        c = len(verts)
        verts.append((offset[0], offset[1], offset[2] + prof[-1][1]))
        for i in range(segs):
            j = (i + 1) % segs
            faces.append((c, i * n + n - 1, j * n + n - 1))
    # perfis abertos: a ordem dos pontos define o lado da face (de baixo para cima / de fora para dentro)
    return mesh_obj(name, verts, faces, mat, parent=parent, split=split)


def box_verts(c, ax, ay, az, sx, sy, sz):
    c = Vector(c)
    ax, ay, az = Vector(ax).normalized(), Vector(ay).normalized(), Vector(az).normalized()
    out = []
    for dz in (-1, 1):
        for dy in (-1, 1):
            for dx in (-1, 1):
                out.append(c + ax * dx * sx / 2 + ay * dy * sy / 2 + az * dz * sz / 2)
    return out


BOX_FACES = [(0, 1, 3, 2), (4, 6, 7, 5), (0, 4, 5, 1), (2, 3, 7, 6), (0, 2, 6, 4), (1, 5, 7, 3)]


def boxes_obj(name, boxes, mat, parent=None, bev=0.0003, seg=2):
    verts, faces = [], []
    for b in boxes:
        base = len(verts)
        verts += b
        faces += [tuple(base + i for i in f) for f in BOX_FACES]
    ob = mesh_obj(name, verts, faces, mat, parent=parent, split=35)
    recalc_normals(ob)
    if bev:
        bevel(ob, bev, seg)
    return ob


def prism(name, poly, z0, z1, mat, parent=None, ztop=None):
    """Extruda um polígono 2D (x, y) entre z0 e z1; ztop(x, y) inclina a face de cima."""
    n = len(poly)
    verts = [(x, y, z0) for x, y in poly] + [(x, y, (ztop(x, y) if ztop else z1)) for x, y in poly]
    faces = [tuple(range(n - 1, -1, -1)), tuple(range(n, 2 * n))]
    for i in range(n):
        j = (i + 1) % n
        faces.append((i, j, n + j, n + i))
    ob = mesh_obj(name, verts, faces, mat, smooth=False, parent=parent)
    recalc_normals(ob)
    return ob


def plane(name, sx, sy, mat, loc=(0, 0, 0), parent=None):
    vs = [(-sx / 2, -sy / 2, 0), (sx / 2, -sy / 2, 0), (sx / 2, sy / 2, 0), (-sx / 2, sy / 2, 0)]
    ob = mesh_obj(name, vs, [(0, 1, 2, 3)], mat, smooth=False, parent=parent)
    ob.location = loc
    return ob


def box(name, center, size, mat, bev=0.0, parent=None):
    vs = box_verts(center, (1, 0, 0), (0, 1, 0), (0, 0, 1), *size)
    ob = mesh_obj(name, vs, BOX_FACES, mat, parent=parent, split=35)
    recalc_normals(ob)
    if bev:
        bevel(ob, bev, 3)
    return ob


def smooth_all(ob):
    for p in ob.data.polygons:
        p.use_smooth = True


# =====================================================================================
# materiais
# =====================================================================================
def node_mat(name):
    m = bpy.data.materials.new(name)
    m.use_nodes = True
    nt = m.node_tree
    return m, nt, nt.nodes["Principled BSDF"]


_NAMES = {"color": "Base Color", "metal": "Metallic", "rough": "Roughness", "coat": "Coat Weight",
          "coat_rough": "Coat Roughness", "aniso": "Anisotropic", "sheen": "Sheen Weight",
          "sheen_rough": "Sheen Roughness", "trans": "Transmission Weight", "ior": "IOR",
          "spec": "Specular IOR Level", "sss": "Subsurface Weight", "emit": "Emission Strength",
          "emit_color": "Emission Color"}


def setp(p, **kw):
    for k, v in kw.items():
        inp = p.inputs[_NAMES[k]]
        inp.default_value = (*v, 1.0) if k in ("color", "emit_color") else v


def mat_simple(name, color, **kw):
    m, nt, p = node_mat(name)
    setp(p, color=color, **kw)
    return m


def noise_rough(nt, p, lo, hi, scale=400.0):
    tex = nt.nodes.new("ShaderNodeTexNoise")
    tex.inputs["Scale"].default_value = scale
    tex.inputs["Detail"].default_value = 6
    mr = nt.nodes.new("ShaderNodeMapRange")
    mr.inputs["To Min"].default_value = lo
    mr.inputs["To Max"].default_value = hi
    nt.links.new(tex.outputs["Fac"], mr.inputs["Value"])
    nt.links.new(mr.outputs["Result"], p.inputs["Roughness"])


METALS = {
    "steel": (0.80, 0.80, 0.82), "gold": (1.0, 0.74, 0.32), "rose": (0.97, 0.62, 0.50),
    "dlc": (0.045, 0.045, 0.05), "pvd": (0.04, 0.04, 0.045), "blued": (0.10, 0.16, 0.55),
    "black": (0.03, 0.03, 0.03),
}


def mat_metal(kind, rough=0.10, brushed=False):
    kind = kind if kind in METALS else "steel"
    key = f"metal-{kind}-{rough}-{brushed}"
    if key in bpy.data.materials:
        return bpy.data.materials[key]
    m, nt, p = node_mat(key)
    setp(p, color=METALS[kind], metal=1.0, rough=rough)
    if brushed:
        setp(p, aniso=0.75)
        noise_rough(nt, p, rough * 0.8, rough * 1.4, 900)
    else:
        noise_rough(nt, p, rough * 0.7, rough * 1.5, 300)
    return m


def mat_glass_crystal():
    if "cristal" in bpy.data.materials:
        return bpy.data.materials["cristal"]
    m = bpy.data.materials.new("cristal")
    m.use_nodes = True
    nt = m.node_tree
    nt.nodes.clear()
    out = nt.nodes.new("ShaderNodeOutputMaterial")
    glass = nt.nodes.new("ShaderNodeBsdfGlass")
    glass.inputs["IOR"].default_value = 1.52
    glass.inputs["Roughness"].default_value = 0.0
    transp = nt.nodes.new("ShaderNodeBsdfTransparent")
    lp = nt.nodes.new("ShaderNodeLightPath")
    mx = nt.nodes.new("ShaderNodeMath")
    mx.operation = "MAXIMUM"
    nt.links.new(lp.outputs["Is Shadow Ray"], mx.inputs[0])
    nt.links.new(lp.outputs["Is Diffuse Ray"], mx.inputs[1])
    mix = nt.nodes.new("ShaderNodeMixShader")
    nt.links.new(mx.outputs[0], mix.inputs[0])
    nt.links.new(glass.outputs[0], mix.inputs[1])
    nt.links.new(transp.outputs[0], mix.inputs[2])
    ar = nt.nodes.new("ShaderNodeMixShader")           # safira com antirreflexo: reflete pouco
    ar.inputs[0].default_value = 0.85
    nt.links.new(mix.outputs[0], ar.inputs[1])
    nt.links.new(transp.outputs[0], ar.inputs[2])
    nt.links.new(ar.outputs[0], out.inputs["Surface"])
    return m


def image_node(nt, path, colorspace="sRGB", extension="CLIP"):
    img = bpy.data.images.load(str(path), check_existing=True)
    img.colorspace_settings.name = colorspace
    tex = nt.nodes.new("ShaderNodeTexImage")
    tex.image = img
    tex.extension = extension
    tex.interpolation = "Cubic"
    return tex


def planar_vector(nt, half_size):
    """Coordenadas do objeto (x, y) -> UV de uma textura quadrada de lado 2*half_size."""
    tc = nt.nodes.new("ShaderNodeTexCoord")
    mp = nt.nodes.new("ShaderNodeMapping")
    mp.inputs["Scale"].default_value = (1 / (2 * half_size), 1 / (2 * half_size), 1)
    mp.inputs["Location"].default_value = (0.5, 0.5, 0)
    nt.links.new(tc.outputs["Object"], mp.inputs["Vector"])
    return mp.outputs["Vector"]


def mat_dial(tex_path, half, rough=0.32, coat=0.0, name="mostrador", sunburst=False, gain=1.8):
    m, nt, p = node_mat(name)
    vec = planar_vector(nt, half)
    tex = image_node(nt, tex_path)
    nt.links.new(vec, tex.inputs["Vector"])
    # a arte 2D tem tons escuros pensados para tela; sob luz física ela precisa de mais refletância
    bc = nt.nodes.new("ShaderNodeMix")
    bc.data_type = "RGBA"
    bc.blend_type = "MULTIPLY"
    bc.inputs["Factor"].default_value = 1.0
    bc.inputs["B"].default_value = (gain, gain, gain, 1)
    nt.links.new(tex.outputs["Color"], bc.inputs["A"])
    nt.links.new(bc.outputs["Result"], p.inputs["Base Color"])
    setp(p, rough=rough, coat=coat, coat_rough=0.03)
    if sunburst:
        # escovado radial: brilho em "raios" que muda com a luz
        tg = nt.nodes.new("ShaderNodeTangent")
        tg.direction_type = "RADIAL"
        tg.axis = "Z"
        nt.links.new(tg.outputs["Tangent"], p.inputs["Tangent"])
        setp(p, aniso=0.9)
        p.inputs["Anisotropic Rotation"].default_value = 0.25
    bump = nt.nodes.new("ShaderNodeBump")
    bump.inputs["Strength"].default_value = 0.35
    bump.inputs["Distance"].default_value = 0.00012
    nt.links.new(tex.outputs["Color"], bump.inputs["Height"])
    nt.links.new(bump.outputs["Normal"], p.inputs["Normal"])
    # índices aplicados e impressões claras ficam metálicos
    sep = nt.nodes.new("ShaderNodeSeparateColor")
    nt.links.new(tex.outputs["Color"], sep.inputs["Color"])
    mr = nt.nodes.new("ShaderNodeMapRange")
    mr.inputs["From Min"].default_value = 0.45
    mr.inputs["From Max"].default_value = 0.75
    mr.inputs["To Min"].default_value = 0.45 if sunburst else 0.0
    mr.inputs["To Max"].default_value = 0.9
    nt.links.new(sep.outputs["Red"], mr.inputs["Value"])
    nt.links.new(mr.outputs["Result"], p.inputs["Metallic"])
    return m


# =====================================================================================
# relógio 3D
# =====================================================================================
def dial_texture(slug: str, T=2048):
    s = rw.SPEC_BY_SLUG[slug]
    path = CACHE / f"dial-{slug}-{T}.png"
    L = rw.Layer(T, T)
    c = R = T / 2
    Rd = rw.draw_case(L, s, c, c, R) * 0.985
    if not path.exists():
        rw.draw_dial(L, s, c, c, Rd)
        rw.draw_subdials(L, s, c, c, Rd)
        rw.draw_print(L, s, c, c, Rd)
        rw.draw_indices(L, s, c, c, Rd)
        if s.date:
            rw.draw_date(L, s, c, c, Rd)
        L.to_image().convert("RGB").save(path)
    return path, Rd / R


def strap_path(pose, R3, side=1):
    """Lista de (y, z, ty, tz) ao longo da pulseira, a partir da ponta da alça."""
    y0, z0 = 1.19 * R3, -0.0048
    if pose == "loop":
        rl = 0.0290 * R3 / 0.020
        zc = z0 - math.sqrt(max(rl * rl - y0 * y0, 1e-9))
        phi0 = math.atan2(y0, z0 - zc)
        phi1 = 2 * math.pi - phi0
        n = 700
        pts = []
        for i in range(n + 1):
            ph = phi0 + (phi1 - phi0) * i / n
            pts.append((rl * math.sin(ph), zc + rl * math.cos(ph), math.cos(ph), -math.sin(ph)))
        return pts, rl * (phi1 - phi0)
    zg = -0.0105 + 0.0017
    length = 0.11
    n = 500
    pts = []
    for i in range(n + 1):
        s = length * i / n
        pts.append([side * (y0 + s), zg + (z0 - zg) * math.exp(-s / 0.006), 0.0, 0.0])
    for i in range(len(pts)):
        a, b = pts[max(0, i - 1)], pts[min(len(pts) - 1, i + 1)]
        d = Vector((0, b[0] - a[0], b[1] - a[1])).normalized()
        pts[i][2], pts[i][3] = d.y, d.z
    return [tuple(p) for p in pts], length


def sample_path(pts, total, s):
    k = min(len(pts) - 1, max(0, int(round(s / total * (len(pts) - 1)))))
    return pts[k]


def build_bracelet(s, R3, pose, parent):
    W0 = 1.04 * R3
    th = 0.0034 * R3 / 0.020
    outer_m = mat_metal(s.case, 0.20, brushed=True)
    center_m = mat_metal("gold", 0.07) if s.strap == "bracelet2" else mat_metal(s.case, 0.06)
    paths = [strap_path("loop", R3)] if pose == "loop" else [strap_path("flat", R3, 1), strap_path("flat", R3, -1)]
    outer, center = [], []
    Lk, gap = 0.0055 * R3 / 0.020, 0.00035
    for pi, (pts, total) in enumerate(paths):
        sign = 1 if (pose == "loop" or pi == 0) else -1
        s_ = Lk / 2 + 0.0003
        while s_ < total - Lk / 2:
            y, z, ty, tz = sample_path(pts, total, s_)
            t = Vector((0, ty, tz))
            nrm = Vector((0, -tz, ty)) * sign
            frac = s_ / total
            taper = 1.0 - 0.16 * (min(frac, 1 - frac) * 2 if pose == "loop" else frac)
            W = W0 * taper
            c = Vector((0, y, z))
            wc, wo = W * 0.30, W * 0.35 - 0.00025
            center.append(box_verts(c + nrm * 0.00025, (1, 0, 0), t, nrm, wc, Lk, th))
            for sx in (-1, 1):
                outer.append(box_verts(c + Vector((sx * (wc / 2 + wo / 2 + 0.00012), 0, 0)), (1, 0, 0), t, nrm, wo, Lk, th * 0.92))
            s_ += Lk + gap
    boxes_obj("elos-laterais", outer, outer_m, parent, bev=0.00045, seg=3)
    boxes_obj("elos-centrais", center, center_m, parent, bev=0.00055, seg=3)


def build_band(s, R3, pose, parent):
    """Pulseira contínua (couro, jacaré, borracha)."""
    W0 = 1.0 * R3
    th = 0.0036 * R3 / 0.020
    m, nt, p = node_mat(f"pulseira-{s.slug}")
    col = tuple(c ** 2.2 for c in s.strap_color)
    croc = s.strap == "croc"
    setp(p, color=col, rough=0.42 if croc else 0.6, coat=0.3 if croc else 0.0, coat_rough=0.2)
    if croc or s.strap == "leather":
        vor = nt.nodes.new("ShaderNodeTexVoronoi")
        vor.feature = "DISTANCE_TO_EDGE"
        vor.inputs["Scale"].default_value = 260 if croc else 1400
        bump = nt.nodes.new("ShaderNodeBump")
        bump.inputs["Strength"].default_value = 0.8 if croc else 0.25
        bump.inputs["Distance"].default_value = 0.0004
        nt.links.new(vor.outputs["Distance"], bump.inputs["Height"])
        nt.links.new(bump.outputs["Normal"], p.inputs["Normal"])
    paths = [strap_path("loop", R3)] if pose == "loop" else [strap_path("flat", R3, 1), strap_path("flat", R3, -1)]
    for pi, (pts, total) in enumerate(paths):
        sign = 1 if (pose == "loop" or pi == 0) else -1
        verts, faces = [], []
        step = 0.0008
        ns = int(total / step)
        for i in range(ns + 1):
            y, z, ty, tz = sample_path(pts, total, i * step)
            nrm = Vector((0, -tz, ty)) * sign
            frac = i / ns
            W = W0 * (1 - 0.18 * (min(frac, 1 - frac) * 2 if pose == "loop" else frac))
            c = Vector((0, y, z))
            for dx, dn in ((-1, -1), (1, -1), (1, 1), (-1, 1)):
                verts.append(c + Vector((dx * W / 2, 0, 0)) + nrm * dn * th / 2)
        for i in range(ns):
            a, b = i * 4, (i + 1) * 4
            for k in range(4):
                k2 = (k + 1) % 4
                faces.append((a + k, a + k2, b + k2, b + k))
        faces.append((0, 3, 2, 1))
        e = ns * 4
        faces.append((e, e + 1, e + 2, e + 3))
        ob = mesh_obj(f"pulseira-{pi}", verts, faces, m, parent=parent, split=50)
        recalc_normals(ob)
        bevel(ob, th * 0.35, 3, angle=50)


def build_watch(slug: str, pose="flat", name="relogio"):
    s = rw.SPEC_BY_SLUG[slug]
    R3 = s.diameter / 2000.0
    root = bpy.data.objects.new(name, None)
    link(root)
    tex, rd = dial_texture(slug)
    case_m = mat_metal(s.case, 0.08)
    case_b = mat_metal(s.case, 0.22, brushed=True)

    prof = [(0.0, -0.0105), (0.78, -0.0105), (0.84, -0.0100), (0.88, -0.0090), (0.95, -0.0080),
            (0.99, -0.0068), (1.0, -0.0055), (1.0, -0.0006), (0.985, 0.0006), (0.955, 0.0011)]
    lathe("caixa", [(r * R3, z) for r, z in prof] + [(0.80 * R3, 0.0006)], case_m, 160, root, caps=False)

    bm = s.bezel_mat or s.case
    if s.bezel == "fluted":
        rin, rout, segs, nr = 0.80, 0.975, 96 * 8, 14
        verts, faces = [], []
        for i in range(segs):
            a = 2 * math.pi * i / segs
            fl = 0.5 + 0.5 * math.cos(a * 96)
            for k in range(nr + 1):
                u = k / nr
                r = (rin + (rout - rin) * u) * R3
                z = 0.0012 + 0.0019 * math.sin(math.pi * (0.25 + 0.75 * u)) * (0.84 + 0.16 * fl) + 0.0002 * fl * math.sin(math.pi * u)
                if k == 0:
                    z = 0.0006
                if k == nr:
                    z = 0.0010
                verts.append((r * math.cos(a), r * math.sin(a), z))
        for i in range(segs):
            j = (i + 1) % segs
            for k in range(nr):
                faces.append((i * (nr + 1) + k + 1, j * (nr + 1) + k + 1, j * (nr + 1) + k, i * (nr + 1) + k))
        mesh_obj("luneta", verts, faces, mat_metal(bm, 0.06), parent=root, split=70)
        crys_r, ztop = rin * R3 * 0.995, 0.0029
    elif s.bezel in ("diver", "gmt", "graduated"):
        rin, rout = 0.78, 0.975
        lathe("luneta-aro", [(rout * R3 * 0.99, 0.0008), (rout * R3, 0.0012), (rout * R3, 0.0026), (rout * R3 * 0.985, 0.0030),
                             (rout * R3 * 0.955, 0.0030)], case_m, 360, root,
              rfun=lambda a: 1 + 0.006 * math.cos(a * 120), split=60, caps=False)
        ins_m = mat_dial(tex, R3, rough=0.10, coat=0.6, name=f"inserto-{slug}", gain=1.4)
        lathe("inserto", [(rout * R3 * 0.96, 0.0027), (rin * R3, 0.0026)], ins_m, 256, root, split=80, caps=False)
        lathe("rehaut", [(rin * R3, 0.0026), (rd * R3 * 0.985, 0.0)], mat_simple("rehaut", (0.02, 0.02, 0.02), rough=0.4), 256, root, caps=False)
        crys_r, ztop = rin * R3 * 0.995, 0.0031
    else:
        rin = 0.90 if s.bezel == "thin" else 0.86
        prof_b = [(rin * R3, 0.0004)]
        for k in range(1, 9):
            u = k / 8
            prof_b.append(((rin + (0.985 - rin) * u) * R3, 0.0011 + 0.0015 * math.sin(math.pi * (0.3 + 0.7 * u))))
        lathe("luneta", prof_b[::-1], mat_metal(bm, 0.06), 200, root, split=80, caps=False)
        crys_r, ztop = rin * R3 * 0.995, 0.0027

    dial_m = mat_dial(tex, R3, rough={"lacquer": 0.12, "sunburst": 0.22}.get(s.finish, 0.32),
                      coat=0.8 if s.finish == "lacquer" else 0.0, name=f"mostrador-{slug}", sunburst=s.finish == "sunburst")
    lathe("mostrador", [(rd * R3 * 0.999, 0.0), (0, 0.0)], dial_m, 256, root, split=None, caps=False)

    # ponteiros 3D com facetas inclinadas
    Rd = rd * R3
    hour_a, min_a, sec_a = 304.8, 57.6, 216.0
    hm = mat_metal(s.hand_mat, 0.05)
    lume_m = mat_simple("luminova", (0.85, 0.84, 0.76), rough=0.5)
    wid = (0.085, 0.065) if s.hands != "baton" else (0.050, 0.040)
    for (lab, ang, ln, w), zz in zip((("hora", hour_a, Rd * 0.55, Rd * wid[0]), ("minuto", min_a, Rd * 0.86, Rd * wid[1])), (0.0007, 0.0011)):
        left, right, lume = rw.hand_polys(s.hands, 0, 0, ang, ln, w, Rd * 0.10)
        ax = (math.sin(math.radians(ang)), math.cos(math.radians(ang)))
        for half in (left, right):
            poly = [(x, -y) for x, y in half]

            def ztop_fn(x, y, ax=ax, zz=zz):
                d = abs(x * ax[1] - y * ax[0])
                return zz + 0.00022 - d * 0.30
            prism(f"ponteiro-{lab}", poly, zz - 0.00012, zz, hm, root, ztop=ztop_fn)
        if s.lume and lume:
            prism(f"lume-{lab}", [(x, -y) for x, y in lume], zz, zz + 0.00018, lume_m, root)

    def stick(name, mat, ang, a0, a1, w0, w1, z0, z1):
        a = math.radians(ang)
        u = (math.sin(a), math.cos(a))
        v = (-u[1], u[0])
        P = lambda al, ac: (u[0] * al + v[0] * ac, u[1] * al + v[1] * ac)
        return prism(name, [P(a0, -w0), P(a1, -w1), P(a1, w1), P(a0, w0)], z0, z1, mat, root)

    if s.gmt_hand:
        gm = mat_simple("gmt", tuple(c ** 2.2 for c in s.gmt_hand), rough=0.35)
        stick("gmt-haste", gm, 160, -Rd * 0.1, Rd * 0.80, 0.00016, 0.00016, 0.00135, 0.0015)
        a = math.radians(160)
        u = (math.sin(a), math.cos(a))
        v = (-u[1], u[0])
        P = lambda al, ac: (u[0] * al + v[0] * ac, u[1] * al + v[1] * ac)
        prism("gmt-seta", [P(Rd * 0.78, -Rd * 0.05), P(Rd * 0.94, 0), P(Rd * 0.78, Rd * 0.05)], 0.00135, 0.0015, gm, root)
    if s.seconds:
        sm = mat_simple("segundos", tuple(c ** 2.2 for c in s.seconds), metal=0.6, rough=0.25)
        stick("segundos", sm, sec_a, -Rd * 0.22, Rd * 0.93, 0.00012, 0.00006, 0.00155, 0.00168)
    lathe("pino", [(0.00075, 0.0), (0.00075, 0.0017), (0.0005, 0.00185), (0.0, 0.0019)], hm, 48, root)

    lathe("cristal", [(0.0, 0.0012), (crys_r, 0.0012), (crys_r, ztop), (crys_r * 0.7, ztop + 0.00025), (0.0, ztop + 0.00035)],
          mat_glass_crystal(), 200, root, split=60)

    # alças
    lugs = []
    for sx in (-1, 1):
        for sy in (-1, 1):
            x0, x1 = 0.53 * R3, 0.72 * R3
            y0, y1 = 0.62 * R3, 1.24 * R3
            lugs.append([Vector((sx * x0, sy * y0, -0.0072)), Vector((sx * x1, sy * y0, -0.0072)),
                         Vector((sx * x0, sy * y1, -0.0064)), Vector((sx * (x1 - 0.03 * R3), sy * y1, -0.0064)),
                         Vector((sx * x0, sy * y0, 0.0002)), Vector((sx * x1, sy * y0, 0.0002)),
                         Vector((sx * x0, sy * y1, -0.0034)), Vector((sx * (x1 - 0.03 * R3), sy * y1, -0.0034))])
    boxes_obj("alcas", lugs, case_m, root, bev=0.0006, seg=3)

    # coroa serrilhada (eixo X)
    cr_r, cr_l = 0.0034 * R3 / 0.020, 0.0034
    crown = lathe("coroa", [(0, 0), (cr_r * 0.9, 0), (cr_r, 0.0004), (cr_r, cr_l - 0.0004), (cr_r * 0.9, cr_l), (0, cr_l)],
                  mat_metal(s.case, 0.12), 60, root, rfun=lambda a: 1 + 0.05 * (0.5 + 0.5 * math.cos(a * 30)), split=50)
    crown.rotation_euler = (0, math.radians(90), 0)
    crown.location = (R3 * 0.985, 0, -0.0030)
    tube = lathe("tubo-coroa", [(0, 0), (cr_r * 0.5, 0), (cr_r * 0.5, 0.0012), (0, 0.0012)], case_b, 32, root)
    tube.rotation_euler = (0, math.radians(90), 0)
    tube.location = (R3 * 0.96, 0, -0.0030)

    if s.strap in ("bracelet", "bracelet2"):
        build_bracelet(s, R3, pose, root)
    else:
        build_band(s, R3, pose, root)
    return root, R3


def rest_on(root, z_ground):
    """Desloca o objeto para que o ponto mais baixo toque z_ground."""
    bpy.context.view_layer.update()
    dg = bpy.context.evaluated_depsgraph_get()
    zmin = 1e9
    for ob in root.children_recursive:
        if ob.type != "MESH":
            continue
        ev = ob.evaluated_get(dg)
        mw = ev.matrix_world
        for v in ev.data.vertices:
            zmin = min(zmin, (mw @ v.co).z)
    root.location.z += z_ground - zmin


# =====================================================================================
# câmera, céu, luz, render
# =====================================================================================
def camera(loc, target, lens=50, fstop=2.0, focus=None, sensor=36):
    cd = bpy.data.cameras.new("cam")
    cd.lens = lens
    cd.sensor_width = sensor
    cam = bpy.data.objects.new("cam", cd)
    link(cam)
    cam.location = loc
    d = Vector(target) - Vector(loc)
    cam.rotation_euler = d.to_track_quat("-Z", "Y").to_euler()
    cd.dof.use_dof = True
    cd.dof.aperture_fstop = fstop
    cd.dof.focus_distance = focus if focus else d.length
    cd.dof.aperture_blades = 9
    bpy.context.scene.camera = cam
    return cam


def sky_world(elev_deg, rot_deg, strength=1.0, air=1.0, dust=1.0, sun_size=0.545, sun_energy=4.0,
              sun_color=(1.0, 0.96, 0.9), sun_angle=0.6):
    """Céu físico para o ambiente + uma lâmpada solar alinhada (luz direta bem amostrada)."""
    w = bpy.data.worlds.new("ceu")
    w.use_nodes = True
    nt = w.node_tree
    nt.nodes.clear()
    out = nt.nodes.new("ShaderNodeOutputWorld")
    bg = nt.nodes.new("ShaderNodeBackground")
    sky = nt.nodes.new("ShaderNodeTexSky")
    sky.sky_type = "MULTIPLE_SCATTERING"
    sky.sun_elevation = math.radians(elev_deg)
    sky.sun_rotation = math.radians(rot_deg)
    sky.sun_size = math.radians(sun_size)
    sky.air_density = air
    sky.aerosol_density = dust
    sky.sun_disc = False
    bg.inputs["Strength"].default_value = strength
    nt.links.new(sky.outputs["Color"], bg.inputs["Color"])
    nt.links.new(bg.outputs[0], out.inputs["Surface"])
    bpy.context.scene.world = w
    if sun_energy:
        ld = bpy.data.lights.new("sol", "SUN")
        ld.energy = sun_energy
        ld.angle = math.radians(sun_angle)
        ld.color = sun_color
        ob = bpy.data.objects.new("sol", ld)
        link(ob)
        ob.rotation_euler = (-sun_dir(elev_deg, rot_deg)).to_track_quat("-Z", "Y").to_euler()
    return w


def sun_dir(elev_deg, rot_deg):
    """Direção para o sol, na convenção do nó Sky do Blender."""
    e, r = math.radians(elev_deg), math.radians(rot_deg)
    return Vector((math.sin(r) * math.cos(e), math.cos(r) * math.cos(e), math.sin(e)))


def area(loc, target, size, energy, color=(1, 1, 1)):
    ld = bpy.data.lights.new("area", "AREA")
    ld.energy = energy
    ld.size = size
    ld.color = color
    ob = bpy.data.objects.new("area", ld)
    link(ob)
    ob.location = loc
    ob.rotation_euler = (Vector(target) - Vector(loc)).to_track_quat("-Z", "Y").to_euler()
    ob.visible_camera = False
    ob.visible_glossy = True
    return ob


def render(name, w, h, samples, exposure=0.0):
    sc = bpy.context.scene
    if PREVIEW:
        w, h, samples = w // 3, h // 3, max(16, samples // 8)
    sc.render.resolution_x, sc.render.resolution_y = w, h
    sc.render.resolution_percentage = 100
    sc.cycles.samples = samples
    sc.view_settings.exposure = exposure
    png = CACHE / f"{name}{'-preview' if PREVIEW else ''}.png"
    sc.render.filepath = str(png)
    sc.render.image_settings.file_format = "PNG"
    bpy.ops.render.render(write_still=True)
    img = Image.open(png).convert("RGB")
    arr = np.asarray(img, np.float32) / 255
    rng = np.random.default_rng(7)
    arr = arr + (rng.standard_normal(arr.shape[:2]) * 0.007)[..., None]       # grão de filme leve
    img = Image.fromarray((np.clip(arr, 0, 1) * 255 + 0.5).astype(np.uint8))
    img = img.filter(ImageFilter.UnsharpMask(radius=1.2, percent=30, threshold=2))
    dest = OUT / f"ensaio-{name}.webp" if not PREVIEW else CACHE / f"ensaio-{name}-preview.jpg"
    if PREVIEW:
        img.save(dest, quality=90)
    else:
        img.save(dest, "WEBP", quality=86, method=6)
        small = img.resize((img.width // 2, img.height // 2), Image.LANCZOS)          # versão para celular (srcset)
        small.save(OUT / f"ensaio-{name}-sm.webp", "WEBP", quality=84, method=6)
    print("  ->", dest, img.size)


# =====================================================================================
# peças de xadrez
# =====================================================================================
def _sphere(prof, zc, r, a0=30):
    for a in np.linspace(a0, 180, 24):
        a = math.radians(a)
        prof.append((r * math.sin(a), zc - r * math.cos(a)))
    return prof


BASE = [(0, 0), (17, 0), (17.6, 0.6), (17.6, 3.2), (16.4, 4.2), (16.8, 5.6), (15.6, 6.8), (13.2, 7.6), (12.4, 8.4),
        (13.2, 9.6), (12.6, 10.8), (10.6, 11.6)]


def piece_profile(kind):
    if kind == "peao":
        p = BASE + [(8.2, 13.5), (6.6, 20), (6.0, 30), (6.0, 32.5), (10.2, 33.6), (10.8, 35), (10.2, 36.4), (6.2, 37.4), (5.8, 38.6)]
        return _sphere(p, 46.5, 8.8, 40)
    if kind == "rei":
        return BASE + [(9.0, 14), (7.4, 24), (6.2, 44), (6.2, 47), (11.4, 48.2), (12.0, 50), (11.4, 51.6), (7.0, 52.6), (7.6, 55),
                       (11.8, 66), (12.6, 68), (12.6, 69.4), (11.0, 70.6), (7.0, 71.6), (4.4, 73), (0, 73.6)]
    if kind == "dama":
        p = BASE + [(9.0, 14), (7.4, 24), (6.2, 42), (6.2, 45), (11.0, 46.2), (11.6, 48), (11.0, 49.6), (7.0, 50.6), (7.6, 53),
                    (12.4, 64), (13.0, 66), (12.0, 67.2), (8.0, 68.4)]
        return _sphere(p, 71.5, 4.4, 50)
    if kind == "bispo":
        p = BASE + [(8.6, 14), (7.0, 22), (5.8, 38), (5.8, 40.5), (10.4, 41.6), (11.0, 43), (10.4, 44.4), (6.0, 45.4), (6.2, 46.4)]
        for a in np.linspace(-60, 80, 22):
            a = math.radians(a)
            p.append((8.6 * math.cos(a), 54 + 10.5 * math.sin(a)))
        p += [(2.2, 64.6), (1.6, 65.2)]
        return _sphere(p, 68, 3.0, 40)
    if kind == "torre":
        return BASE + [(10.8, 13), (10.0, 20), (9.4, 34), (9.4, 36), (13.4, 37.2), (13.8, 38.8), (12.8, 40), (12.2, 41),
                       (12.6, 52), (13.8, 53), (13.8, 56), (9.2, 56), (9.2, 53.5), (0, 53.5)]
    raise ValueError(kind)


def cutter_box(parent, center, ax, ay, size, rot=None):
    ob = bpy.data.objects.new("corte", bpy.data.meshes.new("corte"))
    ob.data.from_pydata([tuple(v) for v in box_verts(center, ax, ay, (0, 0, 1), *size)], [], BOX_FACES)
    link(ob, parent)
    if rot:
        ob.rotation_euler = rot
    ob.hide_render = True
    ob.hide_viewport = True
    m = parent.modifiers.new("corte", "BOOLEAN")
    m.object = ob
    m.operation = "DIFFERENCE"
    m.solver = "EXACT"
    return ob


def chess_piece(kind, loc, mat, scale=1.0, rot=0.0):
    prof = [(r * 0.001 * scale, z * 0.001 * scale) for r, z in piece_profile(kind)]
    ob = lathe(f"peca-{kind}", prof, mat, 96, None, split=None)
    ob.location = loc
    ob.rotation_euler = (0, 0, rot)
    if kind == "torre":
        for i in range(4):
            a = i * math.pi / 4
            cutter_box(ob, (0, 0, 0.0565 * scale), (math.cos(a), math.sin(a), 0), (-math.sin(a), math.cos(a), 0),
                       (0.034 * scale, 0.0042 * scale, 0.008 * scale))
    if kind == "bispo":
        cutter_box(ob, (0.004 * scale, 0, 0.058 * scale), (1, 0, 0), (0, 1, 0), (0.012 * scale, 0.03 * scale, 0.0016 * scale),
                   rot=(0, math.radians(-35), 0))
    add_split(ob, 42)
    if kind == "rei":
        z = 0.0736 * scale
        vb = box_verts((0, 0, z + 0.0075 * scale), (1, 0, 0), (0, 1, 0), (0, 0, 1), 0.0034 * scale, 0.0034 * scale, 0.016 * scale)
        hb = box_verts((0, 0, z + 0.0095 * scale), (1, 0, 0), (0, 1, 0), (0, 0, 1), 0.011 * scale, 0.0034 * scale, 0.0034 * scale)
        boxes_obj("cruz", [vb, hb], mat, ob, bev=0.0005 * scale, seg=3)
    if kind == "dama":
        for i in range(8):
            a = i * math.pi / 4
            r = 0.0122 * scale
            lathe("perola", _sphere([], 0.0, 0.0018 * scale, 0), mat, 24, ob, offset=(r * math.cos(a), r * math.sin(a), 0.0675 * scale))
    return ob


def mat_gold_piece():
    m, nt, p = node_mat("ouro-xadrez")
    setp(p, color=(1.0, 0.70, 0.28), metal=1.0, rough=0.10)
    noise_rough(nt, p, 0.07, 0.18, 60)
    return m


# =====================================================================================
# madeira
# =====================================================================================
def mat_wood(name, dark, light, scale=6.0, coat=0.7, coat_rough=0.14, rough=0.45, stretch=(1, 14, 1), seed=0.0):
    m, nt, p = node_mat(name)
    tc = nt.nodes.new("ShaderNodeTexCoord")
    mp = nt.nodes.new("ShaderNodeMapping")
    mp.inputs["Scale"].default_value = stretch
    mp.inputs["Location"].default_value = (seed, seed * 0.7, 0)
    nt.links.new(tc.outputs["Object"], mp.inputs["Vector"])
    wave = nt.nodes.new("ShaderNodeTexWave")
    wave.wave_type = "BANDS"
    wave.bands_direction = "X"
    wave.inputs["Scale"].default_value = scale
    wave.inputs["Distortion"].default_value = 9.0
    wave.inputs["Detail"].default_value = 6
    wave.inputs["Detail Scale"].default_value = 1.8
    nt.links.new(mp.outputs["Vector"], wave.inputs["Vector"])
    noise = nt.nodes.new("ShaderNodeTexNoise")
    noise.inputs["Scale"].default_value = 30
    noise.inputs["Detail"].default_value = 8
    nt.links.new(mp.outputs["Vector"], noise.inputs["Vector"])
    mixf = nt.nodes.new("ShaderNodeMath")
    mixf.operation = "MULTIPLY_ADD"
    nt.links.new(wave.outputs["Fac"], mixf.inputs[0])
    mixf.inputs[1].default_value = 0.75
    nt.links.new(noise.outputs["Fac"], mixf.inputs[2])
    ramp = nt.nodes.new("ShaderNodeValToRGB")
    ramp.color_ramp.elements[0].position = 0.35
    ramp.color_ramp.elements[0].color = (*dark, 1)
    ramp.color_ramp.elements[1].position = 1.0
    ramp.color_ramp.elements[1].color = (*light, 1)
    nt.links.new(mixf.outputs[0], ramp.inputs["Fac"])
    nt.links.new(ramp.outputs["Color"], p.inputs["Base Color"])
    setp(p, rough=rough, coat=coat, coat_rough=coat_rough)
    bump = nt.nodes.new("ShaderNodeBump")
    bump.inputs["Strength"].default_value = 0.04
    nt.links.new(noise.outputs["Fac"], bump.inputs["Height"])
    nt.links.new(bump.outputs["Normal"], p.inputs["Normal"])
    return m


# =====================================================================================
# CENA 1 — xadrez à beira-mar
# =====================================================================================
def scene_xadrez():
    reset_scene()
    sq = 0.055
    for i in range(8):
        for j in range(8):
            light = (i + j) % 2 == 1
            m = (mat_wood(f"nogueira{i}{j}", (0.050, 0.020, 0.008), (0.23, 0.11, 0.042), scale=5.5, seed=i * 3.1 + j)
                 if light else mat_wood(f"ebano{i}{j}", (0.005, 0.004, 0.0035), (0.026, 0.019, 0.014), scale=9, stretch=(1, 18, 1), seed=i + j * 2.3))
            box(f"casa{i}{j}", ((i - 3.5) * sq, (j - 3.5) * sq, -0.005), (sq - 0.0002, sq - 0.0002, 0.010), m)
    ebony = mat_wood("ebano-moldura", (0.005, 0.004, 0.0035), (0.026, 0.019, 0.014), scale=9, stretch=(14, 1, 1))
    walnut = mat_wood("nogueira-filete", (0.050, 0.020, 0.008), (0.23, 0.11, 0.042), scale=5.5)
    fw = 0.034
    for (cx, cy, sx, sy) in ((0, -4 * sq - fw / 2, 8 * sq + 2 * fw, fw), (0, 4 * sq + fw / 2, 8 * sq + 2 * fw, fw),
                             (-4 * sq - fw / 2, 0, fw, 8 * sq), (4 * sq + fw / 2, 0, fw, 8 * sq)):
        box("moldura", (cx, cy, -0.0095), (sx, sy, 0.019), ebony, bev=0.0015)
    for (cx, cy, sx, sy) in ((0, -4 * sq - 0.005, 8 * sq + 0.010, 0.0018), (0, 4 * sq + 0.005, 8 * sq + 0.010, 0.0018),
                             (-4 * sq - 0.005, 0, 0.0018, 8 * sq + 0.010), (4 * sq + 0.005, 0, 0.0018, 8 * sq + 0.010)):
        box("filete", (cx, cy, 0.0001), (sx, sy, 0.0004), walnut)
    table = mat_wood("mesa", (0.010, 0.006, 0.004), (0.045, 0.025, 0.013), scale=2.0, coat=0.6, coat_rough=0.12, stretch=(1, 6, 1))
    box("mesa", (0, 0.3, -0.04), (4.0, 2.0, 0.04), table)
    # mar até o horizonte
    sea, nt, p = node_mat("mar")
    setp(p, color=(0.004, 0.025, 0.05), rough=0.08, spec=0.7)
    wv = nt.nodes.new("ShaderNodeTexNoise")
    wv.inputs["Scale"].default_value = 0.6
    wv.inputs["Detail"].default_value = 12
    bump = nt.nodes.new("ShaderNodeBump")
    bump.inputs["Strength"].default_value = 0.3
    nt.links.new(wv.outputs["Fac"], bump.inputs["Height"])
    nt.links.new(bump.outputs["Normal"], p.inputs["Normal"])
    plane("mar", 20000, 20000, sea, (0, 9990, -14))

    sky_world(26, 205, strength=0.05)
    area((-0.75, -0.30, 0.42), (0, 0.05, 0.03), 0.9, 30, (1.0, 0.95, 0.88))      # luz de janela à esquerda
    area((0.55, 0.40, 0.22), (0, 0, 0.04), 0.35, 6, (0.85, 0.90, 1.0))           # recorte frio atrás

    gold = mat_gold_piece()
    for kind, (ix, iy) in (("rei", (-0.9, 2.6)), ("dama", (1.3, 3.2)), ("torre", (2.7, 3.4)), ("bispo", (-2.2, 1.3)),
                           ("peao", (-1.35, -0.35)), ("peao", (1.35, 0.35)), ("peao", (0.35, 1.9)), ("peao", (1.75, -3.0)),
                           ("peao", (-2.3, -2.7))):
        chess_piece(kind, (ix * sq, iy * sq, 0), gold, 1.0, rot=ix)

    watch, R3 = build_watch("heritage-noite-40", pose="loop")
    watch.rotation_euler = Euler((math.radians(90 - 12), 0, math.radians(14)), "XYZ")
    watch.location = (-0.010, -0.3 * sq, 0)
    rest_on(watch, 0.0)
    target = Vector((-0.010, -0.3 * sq - 0.01, 0.028))
    cam_loc = Vector((0.11, -0.42, 0.09))
    camera(cam_loc, target + Vector((0, 0.0, 0.004)), lens=95, fstop=2.2, focus=(target - cam_loc).length)
    render("xadrez", 2400, 1500, 224, exposure=-0.4)


# =====================================================================================
# CENA 2 — clube (vista de cima, sol de meio-dia)
# =====================================================================================
def newspaper_texture(path: Path, w=2400, h=1650):
    img = Image.new("RGB", (w, h), (236, 231, 218))
    d = ImageDraw.Draw(img)
    green, ivory, champ = (18, 61, 50), (240, 234, 220), (185, 154, 98)
    head_h = int(h * 0.47)
    d.rectangle([0, 0, w, head_h], fill=green)
    m = int(w * 0.045)
    d.rectangle([m, m, w - m, head_h - m // 2], outline=champ, width=4)
    f = lambda n, sz: rw.font(n, int(sz))
    rw.spaced_text(img, "O CLUBE", f("times.ttf", h * 0.055), w / 2, head_h * 0.24, spacing=w * 0.012, fill=ivory)
    rw.spaced_text(img, rw.BRAND, f("times.ttf", h * 0.15), w / 2, head_h * 0.53, spacing=w * 0.016, fill=ivory)
    rw.spaced_text(img, "EDIÇÃO DE DOMINGO   ·   PARA LER SEM PRESSA", f("arial.ttf", h * 0.024), w / 2, head_h * 0.84,
                   spacing=w * 0.004, fill=champ)
    sans = f("arial.ttf", h * 0.024)
    d.text((m * 1.6, m * 1.4), "Nº 01", font=sans, fill=ivory)
    d.text((w - m * 1.6 - sans.getlength("SÃO PAULO"), m * 1.4), "SÃO PAULO", font=sans, fill=ivory)
    y = head_h + int(h * 0.05)
    d.text((m, y), "Tempo para não ter pressa", font=f("times.ttf", h * 0.052), fill=(24, 24, 22))
    d.text((m, y + h * 0.07), "Um domingo no clube, entre a piscina, o jornal e o almoço que não termina.",
           font=f("times.ttf", h * 0.026), fill=(70, 68, 62))
    rng = np.random.default_rng(3)
    body = f("times.ttf", h * 0.017)
    words = ("o relógio fica no pulso enquanto a tarde passa devagar sobre a água do clube e ninguém olha as horas "
             "a não ser para lembrar que ainda é cedo para voltar caminhar até a quadra ler mais uma página pedir outro café").split()
    col_w = (w - 2 * m - 2 * int(w * 0.03)) / 3
    for c in range(3):
        x0 = m + c * (col_w + w * 0.03)
        yy = y + h * 0.13
        while yy < h - m:
            line, ln = [], 0
            while True:
                wd = words[rng.integers(len(words))]
                lw = body.getlength(wd + " ")
                if ln + lw > col_w:
                    break
                line.append(wd)
                ln += lw
            d.text((x0, yy), " ".join(line), font=body, fill=(60, 58, 54))
            yy += h * 0.026
    arr = np.asarray(img, np.float32) / 255
    arr *= (0.97 + 0.04 * rw.fbm(w, h, 30, 61, 3))[..., None]
    Image.fromarray((np.clip(arr, 0, 1) * 255).astype(np.uint8)).save(path)


def palm_mask(path: Path, n=1600):
    img = Image.new("L", (n, n), 0)
    d = ImageDraw.Draw(img)
    for x0, y0, ang0, L in ((0.02, 0.15, 25, 0.95), (0.70, -0.02, 118, 0.75)):
        stem = []
        for t in np.linspace(0, 1, 60):
            a = math.radians(ang0 + 25 * t * t)
            stem.append(((x0 + L * t * math.cos(a)) * n, (y0 + L * t * math.sin(a)) * n))
        d.line(stem, fill=255, width=int(n * 0.008))
        for i, (sx, sy) in enumerate(stem[3:-3]):
            t = i / len(stem)
            ln = n * (0.08 + 0.18 * math.sin(math.pi * min(1, t * 1.1)))
            a_st = math.atan2(stem[i + 5][1] - stem[i + 2][1], stem[i + 5][0] - stem[i + 2][0])
            for side in (-1, 1):
                a = a_st + side * math.radians(60)
                ex, ey = sx + ln * math.cos(a), sy + ln * math.sin(a)
                mx, my = (sx + ex) / 2 + side * 6, (sy + ey) / 2 + 10
                d.line([(sx, sy), (mx, my), (ex, ey + ln * 0.08)], fill=255, width=int(n * 0.010))
    img.filter(ImageFilter.GaussianBlur(1.5)).save(path)


def scene_clube():
    reset_scene()
    terry, nt, p = node_mat("atoalhado")
    setp(p, color=(0.78, 0.76, 0.71), rough=1.0, sheen=0.8, sheen_rough=0.6)
    loops = nt.nodes.new("ShaderNodeTexNoise")
    loops.inputs["Scale"].default_value = 1100
    loops.inputs["Detail"].default_value = 3
    loops.inputs["Distortion"].default_value = 2.0
    bump = nt.nodes.new("ShaderNodeBump")
    bump.inputs["Strength"].default_value = 0.9
    bump.inputs["Distance"].default_value = 0.0012
    nt.links.new(loops.outputs["Fac"], bump.inputs["Height"])
    nt.links.new(bump.outputs["Normal"], p.inputs["Normal"])
    tx = bpy.data.textures.new("dobras", "CLOUDS")
    tx.noise_scale = 0.16
    tx.noise_depth = 3

    def cloth(name, sx, sy, loc, strength, thick=0.0, rot=(0, 0, 0)):
        ob = plane(name, sx, sy, terry, loc)
        ob.rotation_euler = rot
        sub = ob.modifiers.new("sub", "SUBSURF")
        sub.subdivision_type = "SIMPLE"
        sub.levels = sub.render_levels = 7
        dsp = ob.modifiers.new("dobras", "DISPLACE")
        dsp.texture = tx
        dsp.strength = strength
        if thick:
            so = ob.modifiers.new("esp", "SOLIDIFY")
            so.thickness = thick
        smooth_all(ob)
        return ob

    cloth("roupao", 1.4, 1.4, (0, 0, 0), 0.008)
    cloth("lapela", 0.5, 1.2, (-0.41, 0, 0.010), 0.006, thick=0.010, rot=(0, math.radians(-2), math.radians(-6)))
    cu = bpy.data.curves.new("vivo", "CURVE")
    cu.dimensions = "3D"
    cu.bevel_depth = 0.0045
    cu.bevel_resolution = 4
    spl = cu.splines.new("BEZIER")
    pts = [(-0.225, -0.45, 0.012), (-0.185, -0.12, 0.014), (-0.162, 0.10, 0.013), (-0.13, 0.45, 0.012)]
    spl.bezier_points.add(len(pts) - 1)
    for bp, co in zip(spl.bezier_points, pts):
        bp.co = co
        bp.handle_left_type = bp.handle_right_type = "AUTO"
    gm, _, gp = node_mat("vivo-verde")
    setp(gp, color=(0.010, 0.060, 0.040), rough=0.9, sheen=0.6)
    cu.materials.append(gm)
    link(bpy.data.objects.new("vivo", cu))

    # jornal dobrado
    ntex = CACHE / "jornal.png"
    newspaper_texture(ntex)
    paper, nt3, p3 = node_mat("jornal")
    tex = image_node(nt3, ntex)
    uvn = nt3.nodes.new("ShaderNodeUVMap")
    nt3.links.new(uvn.outputs["UV"], tex.inputs["Vector"])
    nt3.links.new(tex.outputs["Color"], p3.inputs["Base Color"])
    setp(p3, rough=0.75)
    pw, ph = 0.34, 0.234
    nx, ny = 40, 28
    verts, faces, uvs = [], [], []
    for j in range(ny + 1):
        for i in range(nx + 1):
            u, v = i / nx, j / ny
            z = 0.0065 + 0.0025 * math.sin(math.pi * u) - 0.0012 * abs(u - 0.5) * 2 + 0.0008 * math.sin(v * 6)
            verts.append(((u - 0.5) * pw, (v - 0.5) * ph, z))
            uvs.append((u, v))
    for j in range(ny):
        for i in range(nx):
            a = j * (nx + 1) + i
            faces.append((a, a + 1, a + nx + 2, a + nx + 1))
    news = mesh_obj("jornal", verts, faces, paper)
    uvl = news.data.uv_layers.new(name="UVMap")
    for poly in news.data.polygons:
        for li in poly.loop_indices:
            uvl.data[li].uv = uvs[news.data.loops[li].vertex_index]
    so = news.modifiers.new("folhas", "SOLIDIFY")
    so.thickness = 0.0025
    news.location = (0.012, 0.004, 0)
    news.rotation_euler = (0, 0, math.radians(-11))

    # óculos de sol
    acet = mat_simple("acetato", (0.012, 0.010, 0.009), rough=0.15, coat=1.0, coat_rough=0.02)
    lens_m = mat_simple("lente", (0.02, 0.035, 0.028), rough=0.02, trans=0.8, ior=1.5)
    glasses = bpy.data.objects.new("oculos", None)
    link(glasses)
    for sx in (-1, 1):
        cx = sx * 0.034
        ring = [(cx + 0.027 * math.copysign(abs(math.cos(a)) ** 0.7, math.cos(a)), 0.021 * math.copysign(abs(math.sin(a)) ** 0.8, math.sin(a)))
                for a in np.linspace(0, 2 * math.pi, 64, endpoint=False)]
        prism("lente", ring, 0.0050, 0.0072, lens_m, glasses)
        outer = [(cx + (x - cx) * 1.17, y * 1.20) for x, y in ring]
        vs, fs = [], []
        n = len(ring)
        for (xo, yo), (xi, yi) in zip(outer, ring):
            vs += [(xo, yo, 0.0038), (xi, yi, 0.0038), (xi, yi, 0.0092), (xo, yo, 0.0092)]
        for i in range(n):
            j = (i + 1) % n
            for k in range(4):
                k2 = (k + 1) % 4
                fs.append((i * 4 + k, j * 4 + k, j * 4 + k2, i * 4 + k2))
        ob = mesh_obj("aro", vs, fs, acet, parent=glasses, split=50)
        recalc_normals(ob)
        bevel(ob, 0.0007, 2, 50)
    boxes_obj("ponte", [box_verts((0, 0.009, 0.0075), (1, 0, 0), (0, 1, 0), (0, 0, 1), 0.017, 0.0045, 0.0035)], acet, glasses, bev=0.0008)
    for sx in (-1, 1):
        a = math.radians(sx * 9)
        boxes_obj("haste", [box_verts((sx * 0.024, -0.006, 0.0016), (math.cos(a), math.sin(a), 0), (-math.sin(a), math.cos(a), 0),
                                      (0, 0, 1), 0.112, 0.0042, 0.0028)], acet, glasses, bev=0.0007)
    glasses.location = (-0.070, -0.150, 0.0025)
    glasses.rotation_euler = (0, 0, math.radians(22))

    watch, R3 = build_watch("heritage-verde-40", pose="flat")
    watch.rotation_euler = (0, 0, math.radians(-118))
    watch.location = (0.020, -0.048, 0)
    rest_on(watch, 0.0085)

    # sombra de palmeira: plano invisível para a câmera, no caminho do sol
    sd = sun_dir(52, 150)
    pm = CACHE / "palmeira.png"
    palm_mask(pm)
    pal, nt6, _ = node_mat("folhagem")
    nt6.nodes.clear()
    o6 = nt6.nodes.new("ShaderNodeOutputMaterial")
    tx6 = image_node(nt6, pm, "Non-Color")
    uv6 = nt6.nodes.new("ShaderNodeUVMap")
    nt6.links.new(uv6.outputs["UV"], tx6.inputs["Vector"])
    diff = nt6.nodes.new("ShaderNodeBsdfDiffuse")
    tr = nt6.nodes.new("ShaderNodeBsdfTransparent")
    mx = nt6.nodes.new("ShaderNodeMixShader")
    nt6.links.new(tx6.outputs["Color"], mx.inputs[0])
    nt6.links.new(tr.outputs[0], mx.inputs[1])
    nt6.links.new(diff.outputs[0], mx.inputs[2])
    nt6.links.new(mx.outputs[0], o6.inputs["Surface"])
    hgt = 1.2
    leaf_c = Vector((-0.22, 0.30, 0)) + sd * (hgt / sd.z)
    leaf = plane("palmeira", 0.9, 0.9, pal, leaf_c)
    uvp = leaf.data.uv_layers.new(name="UVMap")
    for poly in leaf.data.polygons:
        for li, (uu, vv) in zip(poly.loop_indices, ((0, 0), (1, 0), (1, 1), (0, 1))):
            uvp.data[li].uv = (uu, vv)
    leaf.visible_camera = False
    leaf.visible_glossy = False
    leaf.visible_diffuse = False

    area((0.25, -0.45, 0.75), (0.02, -0.02, 0), 0.9, 10, (1.0, 0.98, 0.95))
    sky_world(52, 150, strength=0.03, sun_energy=7.0, sun_angle=0.5)
    camera((0.004, -0.026, 0.60), (0.004, -0.010, 0.0), lens=75, fstop=8.0)
    render("clube", 1600, 2000, 192, exposure=-0.3)


# =====================================================================================
# CENA 3 — campo de golfe ao pôr do sol
# =====================================================================================
def mat_turf(name, a, b):
    m, nt, p = node_mat(name)
    tc = nt.nodes.new("ShaderNodeTexCoord")
    mp = nt.nodes.new("ShaderNodeMapping")
    mp.inputs["Rotation"].default_value = (0, 0, math.radians(8))
    nt.links.new(tc.outputs["Object"], mp.inputs["Vector"])
    sep = nt.nodes.new("ShaderNodeSeparateXYZ")
    nt.links.new(mp.outputs["Vector"], sep.inputs["Vector"])
    band = nt.nodes.new("ShaderNodeMath")
    band.operation = "PINGPONG"
    band.inputs[1].default_value = 1.6
    nt.links.new(sep.outputs["X"], band.inputs[0])
    gt = nt.nodes.new("ShaderNodeMath")
    gt.operation = "GREATER_THAN"
    gt.inputs[1].default_value = 0.8
    nt.links.new(band.outputs[0], gt.inputs[0])
    nz = nt.nodes.new("ShaderNodeTexNoise")
    nz.inputs["Scale"].default_value = 2.5
    nz.inputs["Detail"].default_value = 12
    nt.links.new(tc.outputs["Object"], nz.inputs["Vector"])
    fac = nt.nodes.new("ShaderNodeMath")
    fac.operation = "MULTIPLY_ADD"
    fac.inputs[1].default_value = 0.55
    nt.links.new(gt.outputs[0], fac.inputs[0])
    nt.links.new(nz.outputs["Fac"], fac.inputs[2])
    mix = nt.nodes.new("ShaderNodeMix")
    mix.data_type = "RGBA"
    mix.inputs["A"].default_value = (*a, 1)
    mix.inputs["B"].default_value = (*b, 1)
    nt.links.new(fac.outputs[0], mix.inputs["Factor"])
    nt.links.new(mix.outputs["Result"], p.inputs["Base Color"])
    setp(p, rough=0.9, sheen=0.5, sheen_rough=0.4)
    return m


def scene_campo():
    reset_scene()
    turf = mat_turf("gramado", (0.030, 0.070, 0.012), (0.060, 0.120, 0.020))
    plane("campo", 6000, 6000, turf, (0, 2990, 0))

    grass, ntg, pg = node_mat("grama")
    hi = ntg.nodes.new("ShaderNodeHairInfo")
    gmx = ntg.nodes.new("ShaderNodeMix")
    gmx.data_type = "RGBA"
    gmx.inputs["A"].default_value = (0.015, 0.040, 0.006, 1)
    gmx.inputs["B"].default_value = (0.16, 0.24, 0.040, 1)
    ntg.links.new(hi.outputs["Intercept"], gmx.inputs["Factor"])
    rnd = ntg.nodes.new("ShaderNodeMix")
    rnd.data_type = "RGBA"
    rnd.inputs["B"].default_value = (0.18, 0.17, 0.05, 1)
    ntg.links.new(gmx.outputs["Result"], rnd.inputs["A"])
    rr = ntg.nodes.new("ShaderNodeMath")
    rr.operation = "MULTIPLY"
    rr.inputs[1].default_value = 0.35
    ntg.links.new(hi.outputs["Random"], rr.inputs[0])
    ntg.links.new(rr.outputs[0], rnd.inputs["Factor"])
    ntg.links.new(rnd.outputs["Result"], pg.inputs["Base Color"])
    setp(pg, rough=0.5, sss=0.25)
    patch = plane("patch", 1.0, 1.2, turf, (0, 0.30, 0.0004))
    patch.data.materials.append(grass)
    ps = patch.modifiers.new("grama", "PARTICLE_SYSTEM").particle_system
    st = ps.settings
    st.type = "HAIR"
    st.count = 12000 if PREVIEW else 40000
    st.use_advanced_hair = False
    st.hair_length = 0.008
    st.material = 2
    st.radius_scale = 1.0
    st.root_radius = 0.00045
    st.tip_radius = 0.00004
    st.display_step = 3
    st.render_step = 3
    # filhos com aspereza: fios com altura e inclinação variadas
    st.child_type = "SIMPLE"
    st.child_percent = 2
    st.rendered_child_count = 9
    st.child_radius = 0.012
    st.child_length = 1.0
    st.child_length_threshold = 0.5
    st.roughness_1 = 0.0012
    st.roughness_1_size = 1.0
    st.roughness_endpoint = 0.0025
    st.roughness_end_shape = 1.0
    st.roughness_2 = 0.0015
    st.roughness_2_size = 1.0

    ball, ntb, pb = node_mat("bola")
    setp(pb, color=(0.86, 0.86, 0.84), rough=0.30, coat=0.6, coat_rough=0.1)
    vor = ntb.nodes.new("ShaderNodeTexVoronoi")
    vor.inputs["Scale"].default_value = 420
    bb = ntb.nodes.new("ShaderNodeBump")
    bb.inputs["Strength"].default_value = 0.6
    bb.inputs["Distance"].default_value = 0.0004
    ntb.links.new(vor.outputs["Distance"], bb.inputs["Height"])
    ntb.links.new(bb.outputs["Normal"], pb.inputs["Normal"])
    lathe("bola", _sphere([], 0.0, 0.02135, 0), ball, 64, offset=(0.075, 0.07, 0.0205), split=None)

    white = mat_simple("haste", (0.75, 0.74, 0.70), rough=0.3)
    lathe("haste", [(0.0065, 0), (0.0065, 2.1), (0, 2.11)], white, 24, offset=(-0.60, 6.0, 0))
    flag = mat_simple("bandeira", (0.006, 0.05, 0.034), rough=0.8)
    fl = plane("bandeira", 0.5, 0.34, flag, (-0.85, 6.0, 1.92))
    fl.rotation_euler = (math.radians(90), 0, math.radians(8))
    lathe("buraco", [(0.054, 0.001), (0, 0.001)], mat_simple("buraco", (0.004, 0.004, 0.004), rough=0.9), 48, offset=(-0.60, 6.0, 0), caps=False)

    hills = mat_simple("morros", (0.03, 0.045, 0.025), rough=1.0)
    for x, y, h in ((-300, 900, 80), (500, 1300, 130), (-900, 1900, 190)):
        o = lathe("morro", _sphere([], 0.0, 1.0, 0), hills, 64, split=None)
        o.location = (x, y, -h * 0.35)
        o.scale = (h * 7, h * 1.6, h)
        smooth_all(o)
    tree = mat_simple("arvore", (0.016, 0.028, 0.009), rough=0.9)
    rng = np.random.default_rng(4)
    for _ in range(60):
        x, y = rng.uniform(-320, 320), rng.uniform(60, 480)
        r = rng.uniform(2.5, 6.0)
        o = lathe("arvore", _sphere([], 0.0, r, 0), tree, 24, split=None)
        o.location = (x, y, r * 1.3)
        o.scale = (1, 1, 1.25)

    watch, R3 = build_watch("altitude-gmt-40", pose="loop")
    watch.rotation_euler = Euler((math.radians(90 - 10), 0, math.radians(-14)), "XYZ")
    rest_on(watch, 0.002)

    sky_world(3.0, 14, strength=0.30, dust=3.5, sun_energy=4.5, sun_color=(1.0, 0.58, 0.30), sun_angle=1.0)
    area((-0.50, -0.10, 0.16), (0, 0, 0.03), 0.5, 14, (1.0, 0.80, 0.62))          # rebatedor quente lateral
    cam_loc = Vector((0.035, -0.42, 0.07))
    target = Vector((0.0, 0.0, 0.028))
    camera(cam_loc, target + Vector((0, 0, 0.014)), lens=75, fstop=2.0, focus=(target - cam_loc).length)
    render("campo", 1600, 2000, 160, exposure=0.15)


SCENES = {"xadrez": scene_xadrez, "clube": scene_clube, "campo": scene_campo}

if __name__ == "__main__":
    names = [a for a in sys.argv[1:] if not a.startswith("--")] or list(SCENES)
    for n in names:
        print("cena", n)
        SCENES[n]()
