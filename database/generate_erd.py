#!/usr/bin/env python3
"""
generate_erd.py — membuat gambar ERD (PNG) dari struktur database.

Sumber kebenaran: information_schema database yang sedang dipakai, jadi
gambar selalu mengikuti skema nyata. Kalau kolom/relasi berubah, cukup
jalankan ulang skrip ini.

    python3 database/generate_erd.py

Output: docs/erd.png
"""

import math
import os
import subprocess
import sys

from PIL import Image, ImageDraw, ImageFont

# ---------------------------------------------------------------------------
# Konfigurasi
# ---------------------------------------------------------------------------

PROJECT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUTPUT = os.path.join(PROJECT, "docs", "erd.png")

W, H = 1240, 1230
ROW_H = 26
HEAD_H = 42
PAD = 10
MIN_TABLE_W = 250

# Posisi kartu tabel (x, y) — dirancang agar jalur relasi punya koridor bebas.
POSISI = {
    "users":                (60, 90),
    "bookings":             (500, 90),
    "order_status_history": (900, 90),
    "services":             (500, 660),
    "pakets":               (900, 660),
    "tipe_sepatu":          (900, 900),
    "transactions":         (60, 700),
}

# Relasi: nomor urut dipakai di legenda (menjelaskan aturan ON DELETE).
# (nomor, tabel_anak, kolom_fk, tabel_induk, y_baris_fk)
RELASI = [
    (1, "bookings", "user_id", "users", 2),
    (2, "bookings", "service_id", "services", 3),
    (3, "bookings", "paket_id", "pakets", 4),
    (4, "order_status_history", "booking_id", "bookings", 1),
    (5, "order_status_history", "diubah_oleh", "users", 5),
]

LEGENDA_RELASI = {
    1: ("bookings.user_id", "users.id", "CASCADE"),
    2: ("bookings.service_id", "services.id", "RESTRICT"),
    3: ("bookings.paket_id", "pakets.id", "SET NULL"),
    4: ("order_status_history.booking_id", "bookings.id", "CASCADE"),
    5: ("order_status_history.diubah_oleh", "users.id", "SET NULL"),
}

# Palet (mengikuti warna aplikasi)
C_BG = (250, 250, 248)
C_TITLE = (31, 42, 38)
C_HEAD = (46, 107, 84)
C_HEAD_TXT = (255, 255, 255)
C_BODY = (255, 255, 255)
C_BORDER = (46, 107, 84)
C_TEXT = (38, 48, 44)
C_MUTED = (120, 132, 126)
C_PK = (200, 142, 22)
C_FK = (63, 134, 166)
C_LINE = (126, 138, 132)
C_LEGEND_BG = (245, 246, 243)
C_LEGEND_LINE = (214, 218, 212)

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
F_TABLE = muat_font(17, True)
F_COL = muat_font(14)
F_TAG = muat_font(12, True)
F_SMALL = muat_font(11)
F_LEGEND = muat_font(13)
F_LEGEND_B = muat_font(13, True)

# ---------------------------------------------------------------------------
# Ambil skema dari database
# ---------------------------------------------------------------------------

def ambil_skema():
    mysql = "/Applications/XAMPP/xamppfiles/bin/mysql"
    if not os.path.exists(mysql):
        mysql = "mysql"

    perintah = [
        mysql, "--no-defaults", "-uroot", "-h127.0.0.1",
        "-P" + os.environ.get("DB_PORT", "3306"),
        "-N", "-B", "-e",
        """SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, COLUMN_KEY
           FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA='db_cuci_sepatu'
           ORDER BY TABLE_NAME, ORDINAL_POSITION""",
    ]
    hasil = subprocess.run(perintah, capture_output=True, text=True)
    if hasil.returncode != 0:
        sys.exit("Gagal membaca skema: " + hasil.stderr.strip())

    skema = {}
    for baris in hasil.stdout.strip().split("\n"):
        if not baris.strip():
            continue
        bagian = baris.split("\t")  # COLUMN_KEY kosong → tab terakhir hilang
        if len(bagian) < 3:
            continue
        tabel, kolom, tipe = bagian[0], bagian[1], bagian[2]
        kunci = bagian[3] if len(bagian) > 3 else ""
        skema.setdefault(tabel, []).append(
            {"nama": kolom, "tipe": tipe, "pk": kunci == "PRI"}
        )
    return skema


def rapikan_tipe(tipe):
    return tipe.split("(")[0]


# ---------------------------------------------------------------------------
# Kartu tabel
# ---------------------------------------------------------------------------

def ukuran_kartu(kolom):
    lebar = MIN_TABLE_W
    for k in kolom:
        panjang = len(k["nama"]) + len(rapikan_tipe(k["tipe"]))
        lebar = max(lebar, 150 + panjang * 7)
    return lebar, HEAD_H + len(kolom) * ROW_H + PAD


def y_baris(nama_tabel, indeks_kolom, skema):
    """Y tengah baris kolom ke-i pada kartu."""
    _, y = POSISI[nama_tabel]
    return y + HEAD_H + PAD + indeks_kolom * ROW_H + ROW_H // 2 - 2


def indeks_kolom(skema, tabel, nama_kolom):
    for i, k in enumerate(skema[tabel]):
        if k["nama"] == nama_kolom:
            return i
    return 0


def gambar_kartu(d, nama, kolom, x, y, relasi_keluar):
    lebar, tinggi = ukuran_kartu(kolom)

    d.rounded_rectangle([x + 3, y + 3, x + lebar + 3, y + tinggi + 3],
                        radius=10, fill=(231, 233, 229))
    d.rounded_rectangle([x, y, x + lebar, y + tinggi],
                        radius=10, fill=C_BODY, outline=C_BORDER, width=2)
    d.rounded_rectangle([x, y, x + lebar, y + HEAD_H], radius=10, fill=C_HEAD)
    d.rectangle([x, y + HEAD_H - 10, x + lebar, y + HEAD_H], fill=C_HEAD)
    d.text((x + 14, y + 11), nama, font=F_TABLE, fill=C_HEAD_TXT)

    fk_keluar = {(r[0], r[1]) for r in relasi_keluar}  # (tabel, kolom)

    for i, k in enumerate(kolom):
        cy = y + HEAD_H + PAD + i * ROW_H
        if k["pk"]:
            d.rounded_rectangle([x + 10, cy + 2, x + 40, cy + 19], radius=4, fill=C_PK)
            d.text((x + 17, cy + 4), "PK", font=F_TAG, fill=(255, 255, 255))
        elif (nama, k["nama"]) in fk_keluar:
            d.rounded_rectangle([x + 10, cy + 2, x + 40, cy + 19], radius=4, fill=C_FK)
            d.text((x + 16, cy + 4), "FK", font=F_TAG, fill=(255, 255, 255))

        d.text((x + 50, cy + 3), k["nama"], font=F_COL, fill=C_TEXT)
        tipe = rapikan_tipe(k["tipe"])
        tw = d.textlength(tipe, font=F_COL)
        d.text((x + lebar - 14 - tw, cy + 3), tipe, font=F_COL, fill=C_MUTED)


# ---------------------------------------------------------------------------
# Relasi
# ---------------------------------------------------------------------------

def panah(d, p_dari, p_ke):
    sudut = math.atan2(p_ke[1] - p_dari[1], p_ke[0] - p_dari[0])
    p = 11
    d.polygon([
        p_ke,
        (p_ke[0] - p * math.cos(sudut - 0.38), p_ke[1] - p * math.sin(sudut - 0.38)),
        (p_ke[0] - p * math.cos(sudut + 0.38), p_ke[1] - p * math.sin(sudut + 0.38)),
    ], fill=C_LINE)


def badge_kardinal(d, titik, teks, arah):
    """Kotak kecil '1' / 'N' — arah: 'kiri','kanan','atas','bawah' = posisi badge relatif titik."""
    off = {"kiri": (-30, -9), "kanan": (8, -9), "atas": (-9, -28), "bawah": (-9, 8)}[arah]
    x, y = titik[0] + off[0], titik[1] + off[1]
    d.rounded_rectangle([x, y, x + 22, y + 18], radius=4,
                        fill=(255, 255, 255), outline=C_LINE)
    w = d.textlength(teks, font=F_TAG)
    d.text((x + (22 - w) / 2, y + 2), teks, font=F_TAG, fill=C_TEXT)


def titik_nomor(d, titik, nomor):
    d.ellipse([titik[0] - 9, titik[1] - 9, titik[0] + 9, titik[1] + 9],
              fill=C_PK, outline=(255, 255, 255), width=2)
    teks = str(nomor)
    w = d.textlength(teks, font=F_TAG)
    d.text((titik[0] - w / 2, titik[1] - 8), teks, font=F_TAG, fill=(255, 255, 255))


def gambar_relasi(d, skema, nomor, anak, kolom_fk, induk, idx_fk):
    ax, ay = POSISI[anak]
    aw, ah = ukuran_kartu(skema[anak])
    bx, by = POSISI[induk]
    bw, bh = ukuran_kartu(skema[induk])

    y_fk = y_baris(anak, idx_fk, skema)

    # ---- Rute per pasangan tabel (koridor bebas kartu) ----
    if (anak, induk) == ("bookings", "users"):
        # keluar kiri bookings → masuk kanan users (sejajar baris user_id)
        rute = [(ax, y_fk), (bx + bw, y_fk)]
        n_pos, n_arah = (ax, y_fk), "bawah"
        s_pos, s_arah = (bx + bw, y_fk), "bawah"

    elif (anak, induk) == ("bookings", "services"):
        # keluar bawah bookings → masuk atas services (vertikal lurus)
        gx = ax + 70
        rute = [(gx, ay + ah), (gx, by)]
        n_pos, n_arah = (gx, ay + ah), "kanan"
        s_pos, s_arah = (gx, by), "kanan"

    elif (anak, induk) == ("bookings", "pakets"):
        # keluar kanan bookings → koridor x=840 → masuk kiri pakets
        koridor = 840
        y_masuk = by + 40
        rute = [(ax + aw, y_fk), (koridor, y_fk), (koridor, y_masuk), (bx, y_masuk)]
        n_pos, n_arah = (ax + aw, y_fk), "bawah"
        s_pos, s_arah = (bx, y_masuk), "bawah"

    elif (anak, induk) == ("order_status_history", "bookings"):
        # keluar kiri history → masuk kanan bookings (sejajar baris booking_id)
        rute = [(ax, y_fk), (bx + bw, y_fk)]
        n_pos, n_arah = (ax, y_fk), "bawah"
        s_pos, s_arah = (bx + bw, y_fk), "atas"

    elif (anak, induk) == ("order_status_history", "users"):
        # keluar bawah history → turun → belok kiri jauh → naik masuk bawah users
        koridor_y = 610
        gx = ax + 135
        masuk_x = bx + bw // 2
        rute = [(gx, ay + ah), (gx, koridor_y), (masuk_x, koridor_y), (masuk_x, by + bh)]
        n_pos, n_arah = (gx, ay + ah), "kanan"
        s_pos, s_arah = (masuk_x, by + bh), "kanan"
    else:
        return

    for i in range(len(rute) - 1):
        d.line([rute[i], rute[i + 1]], fill=C_LINE, width=2)
    panah(d, rute[-2], rute[-1])

    badge_kardinal(d, n_pos, "N", n_arah)   # sisi anak (banyak)
    badge_kardinal(d, s_pos, "1", s_arah)   # sisi induk (satu)
    titik_nomor(d, rute[0], nomor)          # nomor → lihat legenda


# ---------------------------------------------------------------------------
# Legenda
# ---------------------------------------------------------------------------

def gambar_legenda(d):
    lx, ly, lw, lh = 60, 1050, 1120, 140
    d.rounded_rectangle([lx, ly, lx + lw, ly + lh], radius=10,
                        fill=C_LEGEND_BG, outline=C_LEGEND_LINE, width=1)
    d.text((lx + 16, ly + 12), "KETERANGAN", font=F_LEGEND_B, fill=C_TITLE)

    # Simbol
    d.rounded_rectangle([lx + 16, ly + 42, lx + 46, ly + 59], radius=4, fill=C_PK)
    d.text((lx + 22, ly + 44), "PK", font=F_TAG, fill=(255, 255, 255))
    d.text((lx + 54, ly + 43), "Primary Key", font=F_LEGEND, fill=C_TEXT)

    d.rounded_rectangle([lx + 166, ly + 42, lx + 196, ly + 59], radius=4, fill=C_FK)
    d.text((lx + 172, ly + 44), "FK", font=F_TAG, fill=(255, 255, 255))
    d.text((lx + 204, ly + 43), "Foreign Key", font=F_LEGEND, fill=C_TEXT)

    d.line([(lx + 316, ly + 51), (lx + 346, ly + 51)], fill=C_LINE, width=2)
    d.text((lx + 354, ly + 43), "1 = satu  ·  N = banyak", font=F_LEGEND, fill=C_TEXT)

    d.text((lx + 16, ly + 76),
           "Panah menunjuk ke tabel induk (sisi 1).",
           font=F_LEGEND, fill=C_MUTED)
    d.text((lx + 16, ly + 96),
           "transactions & tipe_sepatu tanpa FK — walk-in", font=F_LEGEND, fill=C_MUTED)
    d.text((lx + 16, ly + 114),
           "tanpa akun; tipe sepatu disimpan sebagai teks.", font=F_LEGEND, fill=C_MUTED)

    # Daftar relasi bernomor
    rx = lx + 620
    d.text((rx, ly + 12), "RELASI & ATURAN HAPUS", font=F_LEGEND_B, fill=C_TITLE)
    for i in range(5):
        ry = ly + 36 + i * 19
        nomor, (fk, induk, aksi) = i + 1, LEGENDA_RELASI[i + 1]
        d.ellipse([rx, ry - 1, rx + 14, ry + 13], fill=C_PK)
        w = d.textlength(str(nomor), font=F_SMALL)
        d.text((rx + 7 - w / 2, ry), str(nomor), font=F_SMALL, fill=(255, 255, 255))
        d.text((rx + 22, ry), f"{fk}  \u2192  {induk}   ON DELETE {aksi}",
               font=F_SMALL, fill=C_TEXT)


# ---------------------------------------------------------------------------
# Render
# ---------------------------------------------------------------------------

def main():
    skema = ambil_skema()
    hilang = [t for t in POSISI if t not in skema]
    if hilang:
        sys.exit("Tabel tidak ada di database: " + ", ".join(hilang))

    img = Image.new("RGB", (W, H), C_BG)
    d = ImageDraw.Draw(img)

    d.text((60, 28), "ERD — Sistem Cuci Sepatu", font=F_TITLE, fill=C_TITLE)
    d.text((60, 66), "database: db_cuci_sepatu   ·   MySQL / MariaDB   ·   InnoDB",
           font=F_SUB, fill=C_MUTED)

    # Relasi dulu (garis menempel di tepi kartu), kartu di atasnya menutupi
    # ujung garis yang menembus — hasilnya garis selalu tampak "keluar dari tepi".
    for nomor, anak, kolom_fk, induk, idx in RELASI:
        gambar_relasi(d, skema, nomor, anak, kolom_fk, induk, idx)

    relasi_keluar = [(r[1], r[2]) for r in RELASI]
    for nama, (x, y) in POSISI.items():
        gambar_kartu(d, nama, skema[nama], x, y, relasi_keluar)

    # Garis + badge digambar lagi di atas kartu agar badge tidak tertutup
    for nomor, anak, kolom_fk, induk, idx in RELASI:
        gambar_relasi(d, skema, nomor, anak, kolom_fk, induk, idx)

    gambar_legenda(d)

    os.makedirs(os.path.dirname(OUTPUT), exist_ok=True)
    img.save(OUTPUT, "PNG", optimize=True)
    print("ERD tersimpan:", OUTPUT)
    print("Ukuran:", img.size[0], "x", img.size[1])


if __name__ == "__main__":
    main()
