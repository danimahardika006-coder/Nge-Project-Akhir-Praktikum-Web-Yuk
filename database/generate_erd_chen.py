#!/usr/bin/env python3
"""
generate_erd_chen.py — ERD notasi Chen (kotak entitas, elips atribut,
belah ketupat relasi) — gaya buku ajar.

    python3 database/generate_erd_chen.py

Output: docs/erd-chen.png
"""

import math
import os
import subprocess
import sys

from PIL import Image, ImageDraw, ImageFont

PROJECT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUTPUT = os.path.join(PROJECT, "docs", "erd-chen.png")

W, H = 1640, 1240

C_BG = (250, 250, 248)
C_TITLE = (31, 42, 38)
C_MUTED = (120, 132, 126)
C_ENTITY_FILL = (46, 107, 84)
C_ENTITY_TXT = (255, 255, 255)
C_ATTR_FILL = (255, 255, 255)
C_ATTR_LINE = (140, 152, 146)
C_ATTR_TXT = (38, 48, 44)
C_TEXT = (38, 48, 44)
C_PK_FILL = (252, 243, 220)
C_PK_LINE = (200, 142, 22)
C_REL_FILL = (214, 234, 225)
C_REL_LINE = (46, 107, 84)
C_LINK = (126, 138, 132)

# ---------------------------------------------------------------------------
# Font
# ---------------------------------------------------------------------------

def muat_font(ukuran, tebal=False):
    kandidat = [
        "/System/Library/Fonts/Supplemental/Arial Bold.ttf" if tebal
        else "/System/Library/Fonts/Supplemental/Arial.ttf",
        "/System/Library/Fonts/Helvetica.ttc",
    ]
    for path in kandidat:
        if os.path.exists(path):
            try:
                return ImageFont.truetype(path, ukuran)
            except OSError:
                continue
    return ImageFont.load_default()


F_TITLE = muat_font(30, True)
F_SUB = muat_font(15)
F_ENTITY = muat_font(17, True)
F_ATTR = muat_font(13)
F_PK = muat_font(13, True)
F_REL = muat_font(13, True)
F_CARD = muat_font(12, True)
F_LEGEND = muat_font(13)

# ---------------------------------------------------------------------------
# Data skema (dari database nyata)
# ---------------------------------------------------------------------------

def ambil_skema():
    mysql = "/Applications/XAMPP/xamppfiles/bin/mysql"
    if not os.path.exists(mysql):
        mysql = "mysql"
    perintah = [
        mysql, "--no-defaults", "-uroot", "-h127.0.0.1",
        "-P" + os.environ.get("DB_PORT", "3306"),
        "-N", "-B", "-e",
        """SELECT TABLE_NAME, COLUMN_NAME, COLUMN_KEY
           FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA='db_cuci_sepatu'
           ORDER BY TABLE_NAME, ORDINAL_POSITION""",
    ]
    hasil = subprocess.run(perintah, capture_output=True, text=True)
    if hasil.returncode != 0:
        sys.exit("Gagal membaca skema: " + hasil.stderr.strip())

    skema = {}
    for baris in hasil.stdout.strip().split("\n"):
        bagian = baris.split("\t")
        if len(bagian) < 2:
            continue
        tabel, kolom = bagian[0], bagian[1]
        kunci = bagian[2] if len(bagian) > 2 else ""
        skema.setdefault(tabel, []).append({"nama": kolom, "pk": kunci == "PRI"})
    return skema


# ---------------------------------------------------------------------------
# Geometri
# ---------------------------------------------------------------------------

def setengah_lebar_elips(d, teks, font):
    return d.textlength(teks, font=font) / 2 + 14


def elips(rx, ry):
    return (rx, ry)


def titik_di_elips(cx, cy, rx, ry, sudut):
    """Titik pada tepi elips menuju arah `sudut` (radian)."""
    return (cx + rx * math.cos(sudut), cy + ry * math.sin(sudut))


def sudut_dari(cx, cy, tx, ty):
    return math.atan2(ty - cy, tx - cx)


# ---------------------------------------------------------------------------
# Gambar primitif
# ---------------------------------------------------------------------------

def garis(d, p1, p2):
    d.line([p1, p2], fill=C_LINK, width=2)


def entitas(d, nama, x, y, w=172, h=54):
    d.rounded_rectangle([x + 3, y + 3, x + w + 3, y + h + 3], radius=8,
                        fill=(231, 233, 229))
    d.rounded_rectangle([x, y, x + w, y + h], radius=8,
                        fill=C_ENTITY_FILL, outline=C_ENTITY_FILL)
    tw = d.textlength(nama, font=F_ENTITY)
    d.text((x + (w - tw) / 2, y + 16), nama, font=F_ENTITY, fill=C_ENTITY_TXT)
    return (x + w / 2, y + h / 2), w, h


def belah_ketupat(d, teks, cx, cy, w=132, h=66):
    d.polygon([
        (cx, cy - h / 2), (cx + w / 2, cy), (cx, cy + h / 2), (cx - w / 2, cy),
    ], fill=C_REL_FILL, outline=C_REL_LINE, width=2)
    tw = d.textlength(teks, font=F_REL)
    d.text((cx - tw / 2, cy - 8), teks, font=F_REL, fill=(30, 82, 64))


def atribut(d, teks, cx, cy, pk=False):
    rx = d.textlength(teks, font=F_PK if pk else F_ATTR) / 2 + 12
    ry = 16
    if pk:
        d.ellipse([cx - rx, cy - ry, cx + rx, cy + ry],
                  fill=C_PK_FILL, outline=C_PK_LINE, width=2)
        garis_bawah = True
    else:
        d.ellipse([cx - rx, cy - ry, cx + rx, cy + ry],
                  fill=C_ATTR_FILL, outline=C_ATTR_LINE, width=1)
        garis_bawah = False

    font = F_PK if pk else F_ATTR
    tw = d.textlength(teks, font=font)
    d.text((cx - tw / 2, cy - 8), teks, font=font, fill=C_ATTR_TXT)

    if garis_bawah:  # penanda primary key: garis bawah pada teks
        d.line([(cx - tw / 2, cy + 9), (cx + tw / 2, cy + 9)],
               fill=C_PK_LINE, width=2)
    return rx, ry


def hubungkan(d, dari_pusat, dari_ukuran, ke_pusat, ke_ukuran, sama_sisi="auto"):
    """Garis dari tepi elips entitas ke tepi elips atribut."""
    (ax, ay), (aw, ah) = dari_pusat, dari_ukuran
    (bx, by), (bw, bh) = ke_pusat, ke_ukuran
    a = sudut_dari(ax, ay, bx, by)
    b = sudut_dari(bx, by, ax, ay)
    p1 = titik_di_elips(ax, ay, aw, ah, a)
    p2 = titik_di_elips(bx, by, bw, bh, b)
    d.line([p1, p2], fill=C_LINK, width=1)


def kartu_kardinal(d, titik, teks):
    x, y = titik[0] - 9, titik[1] - 9
    d.rectangle([x, y, x + 18, y + 18], fill=(255, 255, 255))
    tw = d.textlength(teks, font=F_CARD)
    d.text((x + (18 - tw) / 2, y + 2), teks, font=F_CARD, fill=C_TITLE)


# ---------------------------------------------------------------------------
# Render
# ---------------------------------------------------------------------------

def main():
    skema = ambil_skema()

    # --- Posisi entitas (pusat kartu) ---
    E = {
        "users":                (880, 210),
        "bookings":             (880, 590),
        "order_status_history": (1450, 660),
        "services":             (330, 930),
        "pakets":               (880, 930),
        "tipe_sepatu":          (1300, 985),
        "transactions":         (300, 250),
    }

    img = Image.new("RGB", (W, H), C_BG)
    d = ImageDraw.Draw(img)

    d.text((60, 26), "ERD Notasi Chen — Sistem Cuci Sepatu", font=F_TITLE, fill=C_TITLE)
    d.text((60, 66), "Entitas = kotak  ·  Atribut = elips  ·  Relasi = belah ketupat",
           font=F_SUB, fill=C_MUTED)

    UKURAN_ENTITAS = (172, 54)

    # --- Gambar garis dulu, bentuk di atasnya ---
    # (urutan: atribut paling jauh digambar lebih dulu tidak diperlukan,
    #  semua garis digambar sebelum semua bentuk agar tidak menutupi)

    # Layout atribut per entitas: (dx, dy) relatif pusat entitas
    ATRIBUT = {
        "users": [
            ("id_user", 0, -105, True),
            ("username", -172, -58, False),
            ("nama_lengkap", 172, -58, False),
            ("email", -196, 6, False),
            ("password", 196, 6, False),
            ("no_hp", -172, 66, False),
            ("role", 172, 66, False),
            ("status_akun", -95, 112, False),
        ],
        "bookings": [
            ("id_booking", -205, -40, True),
            ("kode_booking", 120, -128, False),
            ("tipe_sepatu", -215, 42, False),
            ("jumlah_sepatu", 225, -62, False),
            ("tanggal_booking", 0, 112, False),
            ("total_harga", -195, 112, False),
            ("status", 195, 112, False),
        ],
        "order_status_history": [
            ("id_history", 0, -112, True),
            ("status", 0, 112, False),
            ("catatan", -80, -132, False),
            ("dibuat_pada", 0, -210, False),
        ],
        "services": [
            ("id_service", -168, -46, True),
            ("nama_layanan", 18, -112, False),
            ("harga", 168, 8, False),
            ("aktif", -168, 50, False),
        ],
        "pakets": [
            ("id_paket", -156, -50, True),
            ("nama_paket", 20, -115, False),
            ("tambahan_harga", 172, 12, False),
            ("estimasi_hari", -158, 46, False),
        ],
        "tipe_sepatu": [
            ("id_tipe", -126, -22, True),
            ("nama_tipe", -122, 52, False),
        ],
        "transactions": [
            ("id_trx", -150, -20, True),
            ("no_pesanan", 12, -112, False),
            ("nama_pelanggan", 0, 92, False),
            ("status", -150, 62, False),
        ],
    }

    # Hitung posisi atribut
    posisi_atribut = {}
    for nama, daftar in ATRIBUT.items():
        cx, cy = E[nama]
        posisi_atribut[nama] = []
        for teks, dx, dy, pk in daftar:
            posisi_atribut[nama].append((teks, cx + dx, cy + dy, pk))

    # --- Relasi (belah ketupat) ---
    REL = [
        ("users", "bookings", "membuat", (880, 405), "1", "N"),
        ("bookings", "services", "memakai", (600, 760), "N", "1"),
        ("bookings", "pakets", "memilih", (880, 760), "N", "1"),
        ("bookings", "order_status_history", "memiliki", (1165, 625), "1", "N"),
    ]

    # Garis entitas → belah ketupat
    for a, b, label, (rx, ry), ka, kb in REL:
        ax, ay = E[a]
        bx, by = E[b]
        aw, ah = UKURAN_ENTITAS
        bw, bh = UKURAN_ENTITAS
        p1 = titik_di_elips(ax, ay, aw / 2, ah / 2, sudut_dari(ax, ay, rx, ry))
        p2 = titik_di_elips(bx, by, bw / 2, bh / 2, sudut_dari(bx, by, rx, ry))
        garis(d, p1, p2)
        kartu_kardinal(d, (p1[0] + (rx - p1[0]) * 0.22, p1[1] + (ry - p1[1]) * 0.22), ka)
        kartu_kardinal(d, (p2[0] + (rx - p2[0]) * 0.22, p2[1] + (ry - p2[1]) * 0.22), kb)

    # Garis entitas → atribut
    for nama, daftar in posisi_atribut.items():
        cx, cy = E[nama]
        for teks, ax, ay, pk in daftar:
            hubungkan(d,
                      (cx, cy),
                      (UKURAN_ENTITAS[0] / 2, UKURAN_ENTITAS[1] / 2),
                      (ax, ay),
                      (d.textlength(teks, font=F_PK if pk else F_ATTR) / 2 + 12, 16))

    # --- Bentuk di atas garis ---
    for nama, (cx, cy) in E.items():
        entitas(d, nama, cx - UKURAN_ENTITAS[0] / 2, cy - UKURAN_ENTITAS[1] / 2)

    for a, b, label, (rx, ry), ka, kb in REL:
        belah_ketupat(d, label, rx, ry)

    for nama, daftar in posisi_atribut.items():
        for teks, ax, ay, pk in daftar:
            atribut(d, teks, ax, ay, pk)

    # --- Legenda ---
    lx, ly = 60, 1105
    d.rounded_rectangle([lx, ly, lx + 1520, ly + 118], radius=10,
                        fill=(245, 246, 243), outline=(214, 218, 212))

    entitas(d, "Entitas", lx + 20, ly + 20, w=120, h=44)
    belah_ketupat(d, "Relasi", lx + 290, ly + 42, w=110, h=48)
    atribut(d, "atribut", lx + 450, ly + 42)
    atribut(d, "primary key", lx + 620, ly + 42, pk=True)

    d.text((lx + 20, ly + 82), "1 = satu  ·  N = banyak", font=F_LEGEND, fill=C_TEXT)
    d.text((lx + 260, ly + 82),
           "Panah tidak dipakai; kardinalitas ditulis di ujung garis (1 / N).",
           font=F_LEGEND, fill=C_MUTED)
    d.text((lx + 20, ly + 104),
           "transactions (kasir walk-in) & tipe_sepatu berdiri sendiri — tidak terhubung relasi FK ke pelanggan.",
           font=F_LEGEND, fill=C_MUTED)

    os.makedirs(os.path.dirname(OUTPUT), exist_ok=True)
    img.save(OUTPUT, "PNG", optimize=True)
    print("ERD Chen tersimpan:", OUTPUT, img.size)


if __name__ == "__main__":
    main()
