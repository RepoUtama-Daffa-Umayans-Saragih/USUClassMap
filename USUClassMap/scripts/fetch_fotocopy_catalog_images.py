#!/usr/bin/env python3
"""Scraper gambar katalog fotokopi & ATK (Wikimedia Commons Primary + Cache & Title Scoring -> Optional Bing -> Sibling Fallback)."""

from __future__ import annotations

import argparse
import hashlib
import io
import json
import random
import re
import shutil
import sys
import time
from datetime import datetime, timezone
from pathlib import Path
from urllib.parse import quote_plus

import requests
from bs4 import BeautifulSoup
from PIL import Image, ImageOps

SCRIPT_DIR = Path(__file__).resolve().parent
PROJECT_ROOT = SCRIPT_DIR.parent
MASTER_CATALOG_PATH = PROJECT_ROOT / "database" / "seeders" / "data" / "master_fotocopy_catalog.json"
STORAGE_PUBLIC_DIR = PROJECT_ROOT / "storage" / "app" / "public"
CATALOG_IMG_DIR = STORAGE_PUBLIC_DIR / "fotocopy_catalog"
REPORT_PATH = SCRIPT_DIR / "image_fetch_report.json"

BING_HEADERS = {
    "User-Agent": (
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
        "AppleWebKit/537.36 (KHTML, like Gecko) "
        "Chrome/124.0.0.0 Safari/537.36"
    ),
    "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8",
    "Accept-Language": "id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7",
}

WIKI_HEADERS = {
    "User-Agent": "USUClassMapCatalogBot/1.0 (https://usu.ac.id; academic-student-project)",
    "Accept": "application/json",
}

DOWNLOAD_HEADERS = {
    "User-Agent": "USUClassMapCatalogBot/1.0 (https://usu.ac.id; academic-student-project)",
    "Accept": "image/avif,image/webp,image/apng,image/*,*/*;q=0.8",
}

WIKI_BAD_TERMS = (
    "icon", "logo", "flag", "map", "diagram", "chart", "screenshot",
    "coat of arms", ".svg", "soviet", "1923", "1956", "191", "190",
    "fuck", "shit", "nsfw", "nude", "sex", "laminaat", "laminate floor",
    "floor", "windows", "software", "gnome", "cartoon", "painting",
    "portrait", "singer", "korea", "beast", "anime", "meme",
    "five guys", "meal", "food", "pizza", "guitar", "surgical",
    "hospital", "kaserne", "diaper", "hanssen", "ambigram", "fake",
    "challenged", "concerned man", "varanasi", "narita", "boarding pass",
    "bat-ears", "supermarket", "e-ink", "hittegods", "escher", "campaign",
    "vatican", "church", "street", "mountain", "river", "ports",
    "scaffolding", "building", "spider", "turnstile", "dress", "museum",
    "aol", "mugshot", "figurine", "aces", "interference", "rainbow",
    "3d printer", "prusa", "ultimaker", "rep-rap", "filament", "mcdonald",
    "mason jar", "pickle", "still", "castle", "sketch-book", "met sf",
    "columbus", "allied paper", "buckstaff", "heraldry", "crest",
    "lorem ipsum", "wax seal", "sewing", "tracing wheel", "chrome ball",
    "sphere", "garland", "hallway", "restaurant", "schoolchildren",
    "bermuda", "postage", "stamps", "stamp", "coin",
    # Spam / web block terms
    "ngawi", "ponorogo", "meat grinder", "grinder", "cabela", "insulin", "komatsu",
    "periodic table", "crocs", "slot", "toto", "gacor", "echo valley",
    "cinderella", "shrimp", "vitacimin", "polibatam", "lion parcel", "al-azhar",
    "gym", "istockphoto", "alamy", "shutterstock", "dreamstime", "vecteezy",
    "pngtree", "123rf", "wisata", "air terjun", "kabupaten", "prostate", "bph",
    "tiktok", "resep", "sinetron", "sindiran", "pancasila", "auto magic",
    "sumatra", "bengkulu", "gundam", "mobile legends", "foom", "irc tire",
    "mahindra", "kitchen cabinet", "wallpaper",
)

# Peta Wikipedia Summary API untuk foto objek spesifik yang sudah terverifikasi
WIKI_PAGE_OVERRIDES: dict[str, list[str]] = {
    "jilid-softcover-direct-binding": ["Bookbinding"],
    "jilid-spiral-plastik-a4-f4": ["Spiral_binding", "Notebook"],
    "print-hitam-putih-a4-80gsm": ["Laser_printing", "Printer_(computing)"],
    "print-hitam-putih-bolak-balik-a4": ["Photocopier", "Laser_printing"],
    "print-warna-teks-grafik-a4": ["Printer_(computing)"],
    "print-warna-teks-f4": ["Printer_(computing)"],
    "print-warna-bolak-balik-a4": ["Photocopier", "Inkjet_printing"],
    "print-warna-a3": ["Plotter"],
    "print-poster-a2": ["Plotter"],
    "fotokopi-bolak-balik-a4-f4": ["Photocopier"],
    "cetak-id-card-pvc-panitia": ["Identity_document", "Smart_card"],
    "kertas-milimeter-blok-a4-1-lembar": ["Graph_paper"],
    "kertas-milimeter-blok-a4-1-pak-50": ["Graph_paper"],
    "kertas-kalkir-kosong-a4-1-lembar": ["Tracing_paper"],
    "kertas-kalkir-kosong-a3-1-lembar": ["Tracing_paper"],
    "kertas-kalkir-a4-1-pak-50": ["Tracing_paper"],
    "peniti-kecil-baju-panitia-pak": ["Safety_pin"],
    "penghapus-staedtler-rasoplast-b30": ["Eraser"],
    "correction-tape-joyko-5mm-12m": ["Correction_tape"],
    "double-tape-12mm-kecil": ["Double-sided_tape"],
    "double-tape-24mm-sedang": ["Double-sided_tape"],
    "paper-clip-trigonal-no3-box": ["Paper_clip"],
    "paper-clip-trigonal-no5-besar-box": ["Paper_clip"],
    "kertas-buffalo-jilid-warna-1-lembar": ["Construction_paper"],
    "kertas-buffalo-jilid-1-pak-100": ["Construction_paper"],
    "kertas-hvs-warna-a4-1-lembar": ["Construction_paper"],
    "penggaris-besi-stainless-30cm": ["Ruler"],
}

# Juga tolak tahun 18xx secara spesifik di judul file agar tidak ambil foto antik buram
YEAR_18XX_RE = re.compile(r"\b18\d{2}\b")

REQUIRED_ENGLISH_NOUNS = (
    "pen", "pencil", "marker", "highlighter", "eraser", "glue", "tape",
    "paper", "ream", "notebook", "folder", "binder", "envelope", "sticky",
    "post-it", "stapler", "staple", "clip", "cutter", "knife", "scissor",
    "punch", "sharpener", "ruler", "protractor", "compass", "clipboard",
    "stamp", "pin", "rubber band", "paper bag", "label", "thermal",
    "pencil case", "lanyard", "badge", "usb", "flash", "pendrive", "cd",
    "dvd", "disc", "battery", "calculator", "casio", "otg", "printer",
    "photocopier", "copier", "binding", "hardcover", "scanner", "meterai",
    "photo", "brochure", "card", "laptop", "receipt", "invoice", "stencil",
    "template", "laminat", "vellum", "tracing", "grid", "folio", "origami",
    "pad", "eraser", "whiteboard", "plotter", "blueprint",
)

SKU_WIKI_QUERY_OVERRIDES: dict[str, list[str]] = {
    "print-hitam-putih-a4-80gsm": ["Brother HL-1050", "laser printer"],
    "print-hitam-putih-bolak-balik-a4": ["Ricoh MP 402SPF", "photocopier"],
    "print-warna-teks-grafik-a4": ["Epson-inkjet-printer", "Canon iP3500", "color printer"],
    "print-warna-teks-f4": ["Epson EcoTank", "color printer"],
    "print-warna-bolak-balik-a4": ["Xerox AltaLink", "color printer"],
    "print-warna-a3": ["Plotter HP Designjet", "wide format printer"],
    "print-poster-a2": ["Plotter HP Designjet", "7900-wide-format-printer"],
    "print-stiker-hvs-a4": ["adhesive labels sheet", "mailing address labels"],
    "cetak-id-card-pvc-panitia": ["Student ID Card", "identification card badge"],
    "fotokopi-bolak-balik-a4-f4": ["Canon ImageRUNNER", "photocopier"],
    "jilid-softcover-direct-binding": ["Bookbinding tie thread", "hardcover book"],
    "jilid-spiral-plastik-a4-f4": ["Notizblock spiralgebunden", "spiral binding"],
    "laminating-f4-folio": ["4084 - Pouch Laminator", "Pouch laminator"],
    "cetak-pas-foto-4x6-paket-4": ["photo printer", "printed photographs"],
    "jasa-edit-ganti-background-pas-foto": ["printed photographs", "photo printer"],
    "cetak-foto-4r-borderless": ["printed photographs", "photo printer"],
    "pulpen-kenko-gel-k1-05-1pcs": ["ballpoint pen", "gel pen"],
    "pulpen-4-warna-multifungsi": ["Bic penholder", "ballpoint pen"],
    "pensil-faber-castell-2b-1-lusin": ["Faber-Castell pencil and eraser", "Faber-Castell Lead pencil"],
    "pensil-joyko-2b-p88-1pcs": ["Faber-Castell pencil and eraser", "graphite pencil"],
    "pensil-joyko-2b-p88-1-lusin": ["Faber-Castell Lead pencil", "graphite pencil"],
    "spidol-whiteboard-snowman-bg12-1pcs": ["Blue Expo marker in whiteboard holder", "whiteboard marker"],
    "spidol-whiteboard-snowman-bg12-1-lusin": ["Blue Expo marker in whiteboard holder", "whiteboard marker"],
    "tinta-refill-spidol-whiteboard-snowman": ["Pelikan Tintenglas", "whiteboard marker"],
    "correction-tape-joyko-5mm-12m": ["Tipp-Ex Pocket Mouse", "Nastro bianchetto", "Tipp-Ex"],
    "penghapus-staedtler-rasoplast-b30": ["Eraser and pencil marks", "2-color-eraser", "Pink Pearl eraser"],
    "lakban-bening-2-inch-daimaru": ["Box Sealing Tape Large Roll", "adhesive tape"],
    "lakban-kain-hitam-jilid-2-inch": ["Duct Tape and Kapton Tape", "black duct tape"],
    "double-tape-12mm-kecil": ["Double sided tape", "Adhesive tape (5)"],
    "double-tape-24mm-sedang": ["Double sided tape", "Adhesive tape (5)"],
    "isi-staples-no10-1-box": ["staples box", "Tackerklammern"],
    "isi-staples-no10-1-pak-20-box": ["Tackerklammern", "staples box"],
    "binder-clip-no105-1pcs": ["Binder clip (HDR)", "Foldback clip"],
    "binder-clip-no105-box-12": ["Binder clip (HDR)", "Binder Clips"],
    "binder-clip-no107-1pcs": ["Binder clip (32267518724)", "Foldback clip"],
    "binder-clip-no107-box-12": ["Binder Clips", "Binder clip (HDR)"],
    "binder-clip-no111-box-12": ["Binder clip (HDR)", "Binder Clips"],
    "binder-clip-no155-box-12": ["Binder clip (32267518724)", "Foldback clip"],
    "binder-clip-no200-1pcs": ["Binder clip (HDR)", "Foldback clip"],
    "binder-clip-no200-box-12": ["Binder Clips", "Binder clip (32267518724)"],
    "binder-clip-no260-1pcs": ["Binder clip (HDR)", "Foldback clip"],
    "binder-clip-no260-box-12": ["Binder Clips", "Foldback clip"],
    "penggaris-besi-stainless-30cm": ["Steel ruler and measuring tape", "20171110-169-steel-ruler"],
    "kertas-hvs-sidu-a4-70gsm-paket-10": ["15 reams of paper stacked", "2009-09-25 A4 ream wrapper"],
    "kertas-hvs-sidu-a4-70gsm-1-rim": ["6 reams of paper stacked", "15 reams of paper stacked"],
    "kertas-hvs-paperone-a4-80gsm-1-rim": ["2009-09-25 A4 ream wrapper", "6 reams of paper stacked"],
    "kertas-hvs-paperone-f4-80gsm-1-rim": ["15 reams of paper stacked", "Rank Xerox A4"],
    "kertas-hvs-warna-a4-1-lembar": ["wp:Construction_paper"],
    "kertas-double-folio-bergaris-1-lembar": ["Ruled paper", "Spiral notebook"],
    "kertas-double-folio-bergaris-1-pak-100": ["Spiral notebook", "Ruled paper"],
    "kertas-buffalo-jilid-warna-1-lembar": ["Construction Paper", "Origami paper stack"],
    "kertas-buffalo-jilid-1-pak-100": ["Construction Paper", "Origami paper stack"],
    "kertas-concorde-sertifikat-a4-1-lembar": ["Certificate paper", "White paper stack"],
    "kertas-concorde-a4-1-pak-50": ["White paper stack", "6 reams of paper stacked"],
    "kertas-milimeter-blok-a4-1-lembar": ["Graph-paper-10sqsm", "Graph paper"],
    "kertas-milimeter-blok-a4-1-pak-50": ["Graph-paper-10sqsm", "Graph paper"],
    "kertas-kalkir-kosong-a4-1-lembar": ["Newone-printed tracing paper", "Traceroll"],
    "kertas-kalkir-kosong-a3-1-lembar": ["Newone-printed tracing paper", "Traceroll"],
    "kertas-kalkir-a4-1-pak-50": ["Newone-printed tracing paper", "Traceroll"],
    "loose-leaf-b5-grid-kotak-50-lembar": ["Graph-paper-10sqsm", "Notizblock-spiralgebunden"],
    "buku-gambar-a4-sidu": ["Sketch-book", "Moleskine notebook"],
    "buku-gambar-a3-sidu": ["Notizblock-spiralgebunden", "Sketch-book"],
    "clear-holder-display-book-60-pocket": ["Compare SpineLess ringless binder", "ring binder"],
    "amplop-coklat-tali-a4-1pcs": ["5-digit Envelope", "Briefumschlag (fcm)"],
    "amplop-coklat-tali-folio-1pcs": ["Briefumschlag (fcm)", "5-digit Envelope"],
    "amplop-coklat-folio-1-pak-50": ["5-digit Envelope", "Briefumschlag (fcm)"],
    "jasa-potong-kertas-mesin-presisi": ["paper trimmer", "paper guillotine cutter"],
    "jasa-lipat-brosur-paket-100": ["brochure leaflet folded", "tri-fold brochure"],
    "laminating-a4-ijazah-sertifikat": ["pouch laminator", "laminating machine"],
    "laminating-a3-poster-denah": ["Laminator-01", "pouch laminator"],
    "burning-cd-r-skripsi-plus-label": ["Blank Verbatim CD-R (Front)", "cd-r optical disc"],
    "burning-dvd-r-skripsi-plus-label": ["dvd-r disc", "Blank Verbatim CD-R"],
    "print-langsung-dari-hp-whatsapp": ["smartphone laptop desk", "mobile printing"],
    "cetak-pas-foto-2x3-paket-6": ["photo printer", "printed photographs"],
    "cetak-pas-foto-3x4-paket-6": ["photo printer", "printed photographs"],
    "cetak-kartu-nama-box-100": ["business cards", "visiting cards"],
    "bantalan-stempel-stamp-pad-joyko": ["ink pad stamp", "rubber stamp office"],
    "tinta-stempel-pyramid-ungu-biru": ["Pelikan Tintenglas", "Füllhaltertinte Pelikan"],
    "push-pin-paku-mading-joyko-box": ["thumbtack push pin", "drawing pins corkboard"],
    "karet-gelang-pentil-pak-kecil": ["rubber bands", "Green Rubber Band Ball"],
    "paper-bag-skripsi-kraft-tali": ["Paper bag", "kraft paper shopping bag"],
    "label-stiker-tom-jerry-no103": ["adhesive labels sheet", "mailing address labels"],
    "label-stiker-tom-jerry-no121": ["adhesive labels sheet", "mailing address labels"],
    "kertas-thermal-struk-58x30mm-roll": ["Cash-receipt-sampleasdf", "Paper Roll Holders"],
    "kotak-pensil-jaring-transparan-ujian": ["Pencil Case", "pencil pouch"],
    "penghapus-papan-tulis-whiteboard-magnet": ["whiteboard eraser", "Blue Expo marker"],
    "plastik-sampul-buku-bening-mika": ["book cover paper", "clear plastic folder"],
    "sampul-kertas-coklat-buku-paket": ["book cover paper", "notebook cover"],
    "plastik-mika-bening-jilid-a4-1lbr": ["clear plastic folder", "transparent acetate sheet"],
    "papan-ujian-clipboard-kayu-f4": ["wooden clipboard", "clipboard office"],
    "papan-ujian-clipboard-plastik-a4": ["clipboard office", "wooden clipboard"],
    "name-tag-panitia-b2-plus-lanyard-1pcs": ["20150725 Lanyard", "lanyard badge"],
    "name-tag-panitia-b3-lanyard-1-pak-10": ["conference name badge", "lanyard badge"],
    "id-card-holder-b4-kaku-1pcs": ["Red Cross Youth", "Lanyard Photo Gitta Wilen"],
    "tali-lanyard-polos-gantungan-id-card": ["Lanyard of a Red Cross Youth", "Lanyard Photo Gitta Wilen"],
    "flashdisk-sandisk-cruzer-blade-16gb": ["sandisk usb flash drive", "usb flash drive pendrive"],
    "flashdisk-sandisk-cruzer-blade-32gb": ["sandisk usb flash drive", "usb flash drive pendrive"],
    "flashdisk-kingston-datatraveler-64gb": ["kingston usb flash drive", "usb flash drive pendrive"],
    "keping-cd-r-kosong-verbatim-satuan": ["Blank Verbatim CD-R (Front)", "Blank Verbatim CD-R (Back)"],
    "keping-dvd-r-kosong-verbatim-satuan": ["dvd-r disc", "Blank Verbatim CD-R (Front)"],
    "kotak-cd-mika-casing-slim": ["cd jewel case", "compact disc case"],
    "sleeve-kantong-cd-kertas-kaca": ["Blank Verbatim CD-R (Back)", "Audio CD with Copy Protection"],
    "peniti-kecil-baju-panitia-pak": ["2023 Agrafki", "Diaper pins"],
    "baterai-aaa-alkaline-isi-2": ["aaa alkaline battery", "alkaline batteries"],
    "baterai-kancing-lr44-kalkulator": ["lr44 button cell battery", "coin cell battery"],
    "kalkulator-ilmiah-casio-fx-991ex": ["casio fx scientific calculator", "scientific calculator"],
    "kalkulator-ilmiah-casio-fx-350ms": ["casio calculator", "scientific calculator"],
    "kalkulator-saku-joyko-cc-23-12-digit": ["pocket calculator", "desk calculator"],
    "kabel-otg-type-c-ke-usb": ["usb otg adapter", "usb-c adapter cable"],
    "kabel-otg-micro-usb-ke-usb": ["micro usb cable", "usb otg adapter"],
    "paper-clip-trigonal-no3-box": ["Trombone_073", "Clips grandes y pequeños"],
    "paper-clip-trigonal-no5-besar-box": ["Paper clip (4038719532)", "Trombone_073"],
    "pembolong-kertas-2-lubang-kenko": ["hole punch office", "paper puncher"],
    "rautan-pensil-mini-faber-castell": ["pencil sharpener"],
    "penggaris-plastik-butterfly-30cm": ["plastic ruler", "30cm ruler"],
    "penggaris-segitiga-sepasang-butterfly": ["set square ruler", "drafting triangles"],
    "busur-derajat-180-butterfly": ["plastic protractor", "protractor"],
    "penggaris-sablon-huruf-angka-mal": ["lettering stencil ruler", "drafting stencil template"],
}

WIKI_SUBCATEGORY_QUERIES: dict[str, list[str]] = {
    "Print Hitam Putih": ["laser printer", "printed paper stack", "office printer"],
    "Print Warna": ["inkjet printer", "color printer", "printed brochure"],
    "Print Ukuran Besar": ["wide format plotter", "blueprint architecture", "large poster print"],
    "Print Media Khusus": ["photo paper", "adhesive stickers", "certificate paper", "business cards"],
    "Fotokopi": ["photocopier", "copy machine", "photocopy document"],
    "Jilid": ["spiral binding", "bookbinding", "hardcover book", "ring bound"],
    "Laminating & Finishing": ["pouch laminator", "laminating machine", "paper trimmer"],
    "Scan & Digital": ["document scanner", "flatbed scanner", "compact disc"],
    "Cetak Foto": ["photo printer", "printed photographs", "photo paper"],
    "Kebutuhan Skripsi": ["laptop typing", "thesis dissertation", "typing keyboard"],
    "Pulpen": ["ballpoint pen", "gel pen", "rollerball pen", "stationery pen"],
    "Pensil": ["graphite pencil", "mechanical pencil", "pencil lead"],
    "Spidol & Highlighter": ["whiteboard marker", "highlighter marker", "permanent marker", "felt tip markers"],
    "Koreksi & Penghapus": ["correction fluid", "correction tape", "pencil eraser", "vinyl eraser"],
    "Lem & Perekat": ["glue stick", "liquid glue bottle", "super glue"],
    "Lakban & Isolasi": ["packing tape", "masking tape", "adhesive tape", "double sided tape"],
    "Kertas HVS": ["copy paper ream", "white a4 paper stack", "colored paper sheets"],
    "Kertas Khusus": ["colored cardstock paper", "origami paper", "lined paper notebook"],
    "Kertas Gambar Teknik": ["graph paper grid", "tracing paper translucent"],
    "Buku & Loose Leaf": ["exercise notebook", "spiral notebook", "loose leaf paper", "sketchbook"],
    "Nota & Kwitansi": ["receipt book", "invoice paper", "cash receipt book"],
    "Map & Pengarsipan": ["manila folder", "ring binder", "plastic document folder", "zipper pouch"],
    "Amplop": ["mailing envelope", "kraft envelope", "paper envelopes"],
    "Sticky Notes & Marker": ["sticky notes", "post-it note", "index tabs"],
    "Stapler & Penjepit": ["office stapler", "staples box", "binder clip", "paper clip"],
    "Alat Potong": ["utility knife cutter", "scissors", "hole punch office", "pencil sharpener"],
    "Pengukur & Alat Gambar": ["plastic ruler", "steel ruler", "set square ruler", "plastic protractor", "drawing compass"],
    "Niche Kampus": ["clipboard office", "rubber stamp office", "thumbtack push pin", "rubber bands", "kraft paper bag"],
    "Event & Panitia": ["lanyard badge", "conference name badge", "id card lanyard"],
    "Elektronik & Digital": ["usb flash drive", "cd-r blank disc", "aa battery", "scientific calculator", "usb otg adapter"],
}

SUBCATEGORY_KEYWORDS: dict[str, set[str]] = {
    "Print Hitam Putih": {"print", "printer", "laser", "hvs", "dokumen", "a4", "f4", "duplex", "booklet", "paper"},
    "Print Warna": {"print", "printer", "warna", "color", "poster", "brosur", "grafik", "dokumen"},
    "Print Ukuran Besar": {"print", "plotter", "a3", "a2", "a1", "denah", "blueprint", "poster", "arsitektur"},
    "Print Media Khusus": {"photo paper", "art paper", "art carton", "stiker", "sticker", "concorde", "linen", "sertifikat", "kalkir", "transparansi", "ohp", "kartu nama", "id card", "pvc", "cetak"},
    "Fotokopi": {"fotokopi", "photocopy", "photocopier", "copier", "kopi", "scan", "dokumen", "ktp"},
    "Jilid": {"jilid", "binding", "hardcover", "softcover", "spiral", "lakban", "skripsi", "binder", "mika", "buku"},
    "Laminating & Finishing": {"laminating", "laminator", "laminate", "potong", "guillotine", "trimmer", "lipat", "brosur", "ktp"},
    "Scan & Digital": {"scan", "scanner", "burning", "cd", "dvd", "pdf", "whatsapp", "print"},
    "Cetak Foto": {"foto", "photo", "pas foto", "passport", "4r", "background"},
    "Kebutuhan Skripsi": {"skripsi", "pengetikan", "typing", "word", "margin", "daftar isi", "laptop"},
    "Pulpen": {"pulpen", "pena", "bolpoin", "pen", "ballpoint", "gel", "standard", "ae7", "pilot", "kenko", "joyko", "snowman", "sarasa", "faster", "grip"},
    "Pensil": {"pensil", "pencil", "2b", "faber", "staedtler", "joyko", "mekanik", "mechanical", "lead"},
    "Spidol & Highlighter": {"spidol", "marker", "whiteboard", "highlighter", "stabilo", "snowman", "joyko"},
    "Koreksi & Penghapus": {"tipe-x", "tipex", "correction", "penghapus", "eraser", "kenko", "joyko", "staedtler", "faber"},
    "Lem & Perekat": {"lem", "glue", "glukol", "povinal", "uhu", "kenko", "alteco", "adhesive"},
    "Lakban & Isolasi": {"lakban", "isolasi", "selotip", "tape", "masking", "double", "daimaru", "nachi", "joyko"},
    "Kertas HVS": {"kertas", "hvs", "paper", "sidu", "sinar dunia", "paperone", "rim", "ream", "a4", "f4"},
    "Kertas Khusus": {"folio", "double folio", "buffalo", "manila", "karton", "concorde", "origami", "kertas", "paper", "cardstock"},
    "Kertas Gambar Teknik": {"milimeter", "kalkir", "tracing", "graph paper", "vellum", "kertas"},
    "Buku & Loose Leaf": {"buku", "notebook", "loose leaf", "binder", "sidu", "campus", "big boss", "gambar"},
    "Nota & Kwitansi": {"nota", "kwitansi", "kuitansi", "paperline", "receipt", "ncr"},
    "Map & Pengarsipan": {"stopmap", "map", "snelhecter", "zipper", "clear holder", "display book", "binder", "folder", "biola", "bantex"},
    "Amplop": {"amplop", "envelope", "paperline", "kraft", "coklat", "samson"},
    "Sticky Notes & Marker": {"sticky", "post-it", "memo", "page marker", "indeks", "tabs", "joyko"},
    "Stapler & Penjepit": {"stapler", "staples", "hekter", "binder clip", "paper clip", "trigonal", "klip", "kangaro", "joyko"},
    "Alat Potong": {"cutter", "pisau", "gunting", "scissors", "pembolong", "puncher", "rautan", "sharpener", "joyko", "kenko"},
    "Pengukur & Alat Gambar": {"penggaris", "ruler", "segitiga", "busur", "protractor", "jangka", "compass", "sablon", "mal", "butterfly"},
    "Niche Kampus": {"materai", "meterai", "clipboard", "papan ujian", "sampul", "mika", "stempel", "stamp", "push pin", "peniti", "karet gelang", "paper bag", "label", "tom", "thermal", "tempat pensil", "kotak pensil", "penghapus papan", "whiteboard"},
    "Event & Panitia": {"name tag", "id card", "card holder", "lanyard", "badge", "panitia", "tali"},
    "Elektronik & Digital": {"flashdisk", "flash drive", "usb", "sandisk", "kingston", "cd-r", "dvd-r", "cd", "sleeve", "baterai", "battery", "lr44", "kalkulator", "calculator", "casio", "joyko", "otg"},
}


class BingSearchUnavailable(Exception):
    pass


def log(msg: str) -> None:
    print(msg, flush=True)


def validate_existing_webp(path: Path) -> tuple[bool, int, int, int, str]:
    """Kembalikan (valid, size_bytes, width, height, sha1) untuk file gambar yang sudah ada."""
    if not path.is_file():
        return False, 0, 0, 0, ""
    size_bytes = path.stat().st_size
    if size_bytes <= 2048:
        return False, size_bytes, 0, 0, ""
    raw = path.read_bytes()
    try:
        with Image.open(io.BytesIO(raw)) as img:
            img.verify()
        with Image.open(io.BytesIO(raw)) as img:
            w, h = img.size
        if w < 120 or h < 120:
            return False, size_bytes, w, h, ""
        sha1 = hashlib.sha1(raw).hexdigest()
        return True, size_bytes, w, h, sha1
    except Exception:
        return False, size_bytes, 0, 0, ""


def encode_and_validate_image(raw: bytes) -> tuple[bytes, int, int] | None:
    """Validasi gambar mentah dengan Pillow, resize <=500x600, konversi ke WEBP >2KB."""
    if len(raw) <= 2048:
        return None
    try:
        with Image.open(io.BytesIO(raw)) as img:
            img.verify()
        with Image.open(io.BytesIO(raw)) as img:
            img = ImageOps.exif_transpose(img)
            w, h = img.size
            if w < 120 or h < 120:
                return None
            aspect = w / float(h)
            if aspect < 0.4 or aspect > 2.5:
                return None

            if img.mode in ("RGBA", "LA") or (img.mode == "P" and "transparency" in img.info):
                rgba = img.convert("RGBA")
                bg = Image.new("RGB", rgba.size, (255, 255, 255))
                bg.paste(rgba, mask=rgba.split()[-1])
                rgb = bg
            elif img.mode != "RGB":
                rgb = img.convert("RGB")
            else:
                rgb = img.copy()

            if rgb.width > 500 or rgb.height > 600:
                rgb.thumbnail((500, 600), Image.Resampling.LANCZOS)

            out_w, out_h = rgb.size
            for quality in (80, 92):
                buf = io.BytesIO()
                rgb.save(buf, format="WEBP", quality=quality, method=6)
                encoded = buf.getvalue()
                if len(encoded) > 2048:
                    return encoded, out_w, out_h
            return None
    except Exception:
        return None


def is_text_clean_and_relevant(text: str, url: str, item: dict) -> bool:
    """Pastikan judul/nama file bebas kata spam & cocok dengan produk."""
    haystack = f"{text} {url}".lower()
    if any(bad in haystack for bad in WIKI_BAD_TERMS) or YEAR_18XX_RE.search(haystack):
        return False

    sub = item.get("sub_category", "")
    keywords = set(SUBCATEGORY_KEYWORDS.get(sub, set()))
    for token in re.split(r"[^a-z0-9]+", item["slug"].lower()):
        if len(token) >= 3 and token not in {"1pcs", "1lbr", "box", "pak", "rim", "pcs", "lembar", "isi", "dan"}:
            keywords.add(token)

    title_lower = text.lower()
    url_lower = url.lower()
    return any(kw in title_lower or kw in url_lower for kw in keywords)


def fetch_bing_candidates(session: requests.Session, query: str, item: dict) -> list[str]:
    """Ambil kandidat URL dari Bing Images dengan verifikasi ketat judul a.iusc['m']['t']."""
    url = f"https://www.bing.com/images/search?q={quote_plus(query)}&form=HDRSC2&first=1"
    for attempt in range(2):
        resp = session.get(url, headers=BING_HEADERS, timeout=12)
        if resp.status_code == 429:
            if attempt == 0:
                log("   [Bing 429] Tidur 20 detik sebelum coba lagi...")
                time.sleep(20)
                continue
            return []
        if resp.status_code != 200:
            return []
        break

    soup = BeautifulSoup(resp.text, "html.parser")
    anchors = soup.select("a.iusc")
    if len(anchors) < 5:
        raise BingSearchUnavailable(f"Halaman Bing hanya memuat {len(anchors)} a.iusc")

    urls: list[str] = []
    for anchor in anchors[:12]:
        raw_meta = anchor.get("m")
        if not raw_meta:
            continue
        try:
            meta = json.loads(raw_meta)
        except Exception:
            continue
        title = str(meta.get("t") or "")
        purl = str(meta.get("purl") or "")
        murl = str(meta.get("murl") or "")
        turl = str(meta.get("turl") or "")

        if not title or not is_text_clean_and_relevant(f"{title} {purl}", murl, item):
            continue

        if murl.startswith("http"):
            urls.append(murl)
        if turl.startswith("http"):
            urls.append(turl)

    deduped: list[str] = []
    seen: set[str] = set()
    for u in urls:
        if u not in seen:
            seen.add(u)
            deduped.append(u)
    return deduped[:10]


def query_wikimedia_raw(
    session: requests.Session,
    query: str,
    wiki_cache: dict[str, list[tuple[str, str]]],
) -> list[tuple[str, str]]:
    """Query Wikimedia Commons API 1x per distinct search string (cached)."""
    key = query.strip().lower()
    if key in wiki_cache:
        return wiki_cache[key]

    params = {
        "action": "query",
        "format": "json",
        "generator": "search",
        "gsrsearch": query,
        "gsrnamespace": 6,
        "gsrlimit": 25,
        "prop": "imageinfo",
        "iiprop": "url|mime",
        "iiurlwidth": 500,
    }
    results: list[tuple[str, str]] = []
    for attempt in range(2):
        try:
            resp = session.get(
                "https://commons.wikimedia.org/w/api.php",
                params=params,
                headers=WIKI_HEADERS,
                timeout=12,
            )
            if resp.status_code == 429:
                log("   [Wikimedia 429] Jeda 5 detik...")
                time.sleep(5)
                continue
            if resp.status_code != 200:
                time.sleep(0.55)
                break
            data = resp.json()
            pages = data.get("query", {}).get("pages", {})
            if isinstance(pages, dict):
                for page in pages.values():
                    title = str(page.get("title") or "")
                    low = title.lower()
                    if any(bad in low for bad in WIKI_BAD_TERMS) or YEAR_18XX_RE.search(low):
                        continue
                    infos = page.get("imageinfo") or []
                    if not infos:
                        continue
                    info = infos[0]
                    mime = str(info.get("mime") or "").lower()
                    if mime not in {"image/jpeg", "image/png", "image/webp"}:
                        continue
                    cand = info.get("thumburl") or info.get("url")
                    if isinstance(cand, str) and cand.startswith("http"):
                        results.append((title, cand))
            time.sleep(0.55)
            break
        except Exception:
            if attempt == 0:
                time.sleep(1.5)
                continue
            break

    wiki_cache[key] = results
    return results


def score_wiki_candidates(
    raw_candidates: list[tuple[str, str]],
    query: str,
    item: dict,
) -> list[str]:
    """Urutkan kandidat Wikimedia berdasarkan skor relevansi judul terhadap query & kata benda produk."""
    query_tokens = [t for t in re.split(r"[^a-z0-9]+", query.lower()) if len(t) >= 2]
    brand = (item.get("brand") or "").lower()
    sub = item.get("sub_category", "")
    sub_kws = SUBCATEGORY_KEYWORDS.get(sub, set())

    scored: list[tuple[int, str]] = []
    for title, url in raw_candidates:
        low = title.lower()
        score = 0
        # Kecocokan kata dalam query
        for qt in query_tokens:
            if qt in low:
                score += 4
        # Kecocokan kata benda bahasa Inggris produk
        for noun in REQUIRED_ENGLISH_NOUNS:
            if noun in low:
                score += 2
        # Kecocokan kata kunci subkategori / brand
        if brand and len(brand) >= 3 and brand in low:
            score += 5
        for kw in sub_kws:
            if len(kw) >= 3 and kw in low:
                score += 3
        # Bonus JPEG produk bersih vs PNG
        if low.endswith((".jpg", ".jpeg")):
            score += 1
        scored.append((score, url))

    scored.sort(key=lambda pair: pair[0], reverse=True)
    positif = [u for sc, u in scored if sc > 0]
    # Batasi hanya 6 kandidat skor tertinggi agar tidak jatuh ke gambar tak relevan
    return (positif if positif else [u for _, u in scored])[:6]


def fetch_wikipedia_page_image(
    session: requests.Session,
    page_title: str,
    wp_cache: dict[str, list[str]],
) -> list[str]:
    """Ambil thumbnail resmi dari Wikipedia REST Summary API."""
    if page_title in wp_cache:
        return wp_cache[page_title]
    urls: list[str] = []
    try:
        resp = session.get(
            f"https://en.wikipedia.org/api/rest_v1/page/summary/{quote_plus(page_title)}",
            headers=WIKI_HEADERS,
            timeout=10,
        )
        if resp.status_code == 200:
            data = resp.json()
            for key in ("thumbnail", "originalimage"):
                src = (data.get(key) or {}).get("source")
                if isinstance(src, str) and src.startswith("http") and not src.lower().endswith(".svg"):
                    urls.append(src)
        time.sleep(0.35)
    except Exception:
        pass
    wp_cache[page_title] = urls
    return urls


def try_download_and_save(
    session: requests.Session,
    candidate_urls: list[str],
    target_path: Path,
    product_key: str,
    hash_registry: dict[str, str],
    allow_shared_hash: bool = False,
) -> tuple[str, int, int, int, str] | None:
    """Coba unduh kandidat URL satu per satu sampai mendapat WEBP valid."""
    for url in candidate_urls:
        try:
            resp = session.get(url, headers=DOWNLOAD_HEADERS, timeout=12, allow_redirects=True)
            if resp.status_code != 200:
                continue
            ctype = str(resp.headers.get("Content-Type") or "").lower()
            if ctype and "image/" not in ctype and "application/octet-stream" not in ctype:
                continue
            result = encode_and_validate_image(resp.content)
            if result is None:
                continue
            webp_bytes, w, h = result
            sha1 = hashlib.sha1(webp_bytes).hexdigest()

            existing_owner = hash_registry.get(sha1)
            if not allow_shared_hash and existing_owner is not None and existing_owner != product_key:
                continue

            target_path.parent.mkdir(parents=True, exist_ok=True)
            target_path.write_bytes(webp_bytes)
            hash_registry.setdefault(sha1, product_key)
            return url, len(webp_bytes), w, h, sha1
        except Exception:
            continue
    return None


def build_wikimedia_queries(item: dict) -> list[str]:
    """Susun daftar query Wikimedia dari override spesifik SKU -> fallback Inggris -> subkategori."""
    slug = item["slug"]
    sub = item.get("sub_category", "")
    candidates: list[str] = []

    if slug in SKU_WIKI_QUERY_OVERRIDES:
        candidates.extend(SKU_WIKI_QUERY_OVERRIDES[slug])

    # Tambahkan nama brand + jenis untuk brand global populer
    brand = (item.get("brand") or "").strip()
    if brand.lower() in {"casio", "sandisk", "kingston", "verbatim", "stabilo", "staedtler", "faber-castell", "pilot", "zebra", "uhu"}:
        short_tokens = [
            w for w in slug.split("-")
            if len(w) >= 2 and w not in {"1pcs", "1lbr", "box", "pak", "rim", "satuan", "eceran", "lusin", "paket", "isi"}
        ]
        if short_tokens:
            candidates.append(" ".join(short_tokens[:3]))

    # Ambil fallback queries yang pendek (<= 4 kata berbahasa Inggris / umum)
    for fq in item.get("image_fallback_queries", []):
        clean_fq = fq.strip()
        words = clean_fq.split()
        if 1 <= len(words) <= 4:
            candidates.append(clean_fq)

    candidates.extend(WIKI_SUBCATEGORY_QUERIES.get(sub, []))

    deduped: list[str] = []
    seen: set[str] = set()
    for q in candidates:
        qc = q.strip()
        if qc and qc.lower() not in seen:
            seen.add(qc.lower())
            deduped.append(qc)
    return deduped


def main() -> int:
    parser = argparse.ArgumentParser(description="Unduh & verifikasi gambar katalog fotokopi USUClassMap")
    parser.add_argument("--force", action="store_true", help="Unduh ulang meskipun file lokal sudah valid")
    parser.add_argument("--only", type=str, default="", help="Filter slug dipisahkan koma")
    parser.add_argument("--exclude-only", type=str, default="", help="Skip slug yang sudah terverifikasi (dipisahkan koma)")
    parser.add_argument("--limit", type=int, default=0, help="Batasi jumlah SKU yang diproses (0 = semua)")
    parser.add_argument("--allow-bing", action="store_true", help="Aktifkan fallback/uji ke Bing Images (default hanya Wikimedia + Sibling)")
    args = parser.parse_args()

    if not MASTER_CATALOG_PATH.is_file():
        log(f"ERROR: Master catalog tidak ditemukan di {MASTER_CATALOG_PATH}")
        return 1

    catalog: list[dict] = json.loads(MASTER_CATALOG_PATH.read_text(encoding="utf-8"))
    CATALOG_IMG_DIR.mkdir(parents=True, exist_ok=True)

    only_slugs = {s.strip() for s in args.only.split(",") if s.strip()} if args.only else set()
    exclude_slugs = {s.strip() for s in args.exclude_only.split(",") if s.strip()} if args.exclude_only else set()

    previous_items_map: dict[str, dict] = {}
    if REPORT_PATH.is_file():
        try:
            prev = json.loads(REPORT_PATH.read_text(encoding="utf-8"))
            for row in prev.get("items", []):
                if isinstance(row, dict) and row.get("slug"):
                    previous_items_map[row["slug"]] = row
        except Exception:
            pass

    hash_registry: dict[str, str] = {}
    validated_by_slug: dict[str, Path] = {}

    for item in catalog:
        slug = item["slug"]
        will_overwrite = (slug in only_slugs) or (not only_slugs and args.force and slug not in exclude_slugs)
        if will_overwrite:
            continue
        product_key = item.get("pair_group") or slug
        target = STORAGE_PUBLIC_DIR / item["image_path"]
        ok, _, _, _, sha1 = validate_existing_webp(target)
        if ok:
            validated_by_slug[slug] = target
            hash_registry.setdefault(sha1, product_key)

    session = requests.Session()
    wiki_cache: dict[str, list[tuple[str, str]]] = {}
    wp_cache: dict[str, list[str]] = {}
    bing_disabled = not args.allow_bing
    consecutive_bing_empty = 0

    report_items: list[dict] = []
    failed_slugs: list[str] = []
    process_count = 0

    for idx, item in enumerate(catalog, start=1):
        slug = item["slug"]
        image_rel = item["image_path"]
        target_path = STORAGE_PUBLIC_DIR / image_rel
        product_key = item.get("pair_group") or slug

        if only_slugs and slug not in only_slugs:
            continue

        if slug in exclude_slugs:
            ok, sz, w, h, _ = validate_existing_webp(target_path)
            if ok:
                prev_row = previous_items_map.get(slug, {})
                validated_by_slug[slug] = target_path
                report_items.append({
                    "slug": slug,
                    "image_path": image_rel,
                    "source": prev_row.get("source") or "cached",
                    "query_used": prev_row.get("query_used"),
                    "source_url": prev_row.get("source_url"),
                    "bytes": sz,
                    "width": w,
                    "height": h,
                })
                continue

        if args.limit and process_count >= args.limit:
            continue

        if not args.force:
            ok, sz, w, h, _ = validate_existing_webp(target_path)
            if ok:
                prev_row = previous_items_map.get(slug, {})
                validated_by_slug[slug] = target_path
                report_items.append({
                    "slug": slug,
                    "image_path": image_rel,
                    "source": prev_row.get("source") or "cached",
                    "query_used": prev_row.get("query_used"),
                    "source_url": prev_row.get("source_url"),
                    "bytes": sz,
                    "width": w,
                    "height": h,
                })
                continue

        process_count += 1
        wiki_queries = build_wikimedia_queries(item)
        bing_queries = [item["image_search_query"]] + item.get("image_fallback_queries", [])

        downloaded = None
        source_used = ""
        query_used = ""

        def run_wiki() -> tuple[str, int, int, int, str] | None:
            nonlocal query_used
            # Putaran 0: Jika ada halaman Wikipedia eksak yang sangat cocok
            for wp_page in WIKI_PAGE_OVERRIDES.get(slug, []):
                wp_urls = fetch_wikipedia_page_image(session, wp_page, wp_cache)
                if wp_urls:
                    dl = try_download_and_save(
                        session, wp_urls, target_path, product_key, hash_registry, allow_shared_hash=False
                    )
                    if dl is not None:
                        query_used = f"wp:{wp_page}"
                        return dl
            # Putaran 1: wajib hash unik per produk/pair_group (hanya 6 kandidat teratas)
            # Putaran 2: boleh berbagi foto bersih teratas jika semua top-6 sudah terpakai
            for allow_dup in (False, True):
                for q in wiki_queries:
                    raw_cands = query_wikimedia_raw(session, q, wiki_cache)
                    if not raw_cands:
                        continue
                    ranked_urls = score_wiki_candidates(raw_cands, q, item)
                    if ranked_urls:
                        dl = try_download_and_save(
                            session,
                            ranked_urls,
                            target_path,
                            product_key,
                            hash_registry,
                            allow_shared_hash=allow_dup,
                        )
                        if dl is not None:
                            query_used = q
                            return dl
                # Jika putaran unik belum berhasil, coba Wikipedia summary page dengan allow_shared_hash=True
                if not allow_dup:
                    for wp_page in WIKI_PAGE_OVERRIDES.get(slug, []):
                        wp_urls = fetch_wikipedia_page_image(session, wp_page, wp_cache)
                        if wp_urls:
                            dl = try_download_and_save(
                                session, wp_urls, target_path, product_key, hash_registry, allow_shared_hash=True
                            )
                            if dl is not None:
                                query_used = f"wp:{wp_page}"
                                return dl
            return None

        def run_bing() -> tuple[str, int, int, int, str] | None:
            nonlocal bing_disabled, consecutive_bing_empty, query_used
            if bing_disabled:
                return None
            for q in bing_queries[:2]:
                try:
                    cands = fetch_bing_candidates(session, q, item)
                    consecutive_bing_empty = 0
                except BingSearchUnavailable:
                    consecutive_bing_empty += 1
                    cands = []
                    if consecutive_bing_empty >= 5:
                        bing_disabled = True
                        break
                except Exception:
                    cands = []
                time.sleep(random.uniform(0.9, 1.5))
                if cands:
                    dl = try_download_and_save(session, cands, target_path, product_key, hash_registry)
                    if dl is not None:
                        query_used = q
                        return dl
            return None

        # PRIMARY: Wikimedia Commons API
        downloaded = run_wiki()
        if downloaded is not None:
            source_used = "wikimedia"
        elif args.allow_bing:
            downloaded = run_bing()
            if downloaded is not None:
                source_used = "bing"

        if downloaded is not None:
            src_url, sz, w, h, _ = downloaded
            validated_by_slug[slug] = target_path
            report_items.append({
                "slug": slug,
                "image_path": image_rel,
                "source": source_used,
                "query_used": query_used,
                "source_url": src_url,
                "bytes": sz,
                "width": w,
                "height": h,
            })
            log(f"[{idx}/{len(catalog)}] {slug} -> OK ({source_used}: '{query_used}', {sz / 1024:.1f} KB, {w}x{h})")
            continue

        # Sibling Fallback terakhir
        sibling_path: Path | None = None
        sibling_slug: str | None = None
        pg = item.get("pair_group")
        if pg:
            for other in catalog:
                if other["slug"] != slug and other.get("pair_group") == pg and other["slug"] in validated_by_slug:
                    sibling_slug = other["slug"]
                    sibling_path = validated_by_slug[sibling_slug]
                    break
        if sibling_path is None:
            sub = item.get("sub_category")
            for other in catalog:
                if other["slug"] != slug and other.get("sub_category") == sub and other["slug"] in validated_by_slug:
                    sibling_slug = other["slug"]
                    sibling_path = validated_by_slug[sibling_slug]
                    break

        if sibling_path is not None and sibling_path.is_file():
            shutil.copy2(sibling_path, target_path)
            ok, sz, w, h, _ = validate_existing_webp(target_path)
            if ok:
                validated_by_slug[slug] = target_path
                report_items.append({
                    "slug": slug,
                    "image_path": image_rel,
                    "source": f"sibling:{sibling_slug}",
                    "query_used": None,
                    "source_url": None,
                    "bytes": sz,
                    "width": w,
                    "height": h,
                })
                log(f"[{idx}/{len(catalog)}] {slug} -> SIBLING ({sibling_slug}, {sz / 1024:.1f} KB)")
                continue

        failed_slugs.append(slug)
        log(f"[{idx}/{len(catalog)}] {slug} -> FAILED")

    merged_map = dict(previous_items_map) if (args.only or args.limit) else {}
    for row in report_items:
        merged_map[row["slug"]] = row
    final_items = sorted(merged_map.values(), key=lambda r: r["slug"])

    by_source = {"bing": 0, "wikimedia": 0, "sibling": 0, "cached": 0}
    for row in final_items:
        src = str(row.get("source") or "cached")
        if src.startswith("bing"):
            by_source["bing"] += 1
        elif src.startswith("wikimedia"):
            by_source["wikimedia"] += 1
        elif src.startswith("sibling"):
            by_source["sibling"] += 1
        else:
            by_source["cached"] += 1

    total_bytes = sum(row.get("bytes", 0) for row in final_items)
    report_payload = {
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "total": len(final_items) + len(failed_slugs),
        "success_count": len(final_items),
        "failed_count": len(failed_slugs),
        "failed_slugs": failed_slugs,
        "by_source": by_source,
        "wiki_unique_queries": len(wiki_cache),
        "total_bytes": total_bytes,
        "items": final_items,
    }
    REPORT_PATH.write_text(json.dumps(report_payload, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    log(f"Laporan disimpan ke {REPORT_PATH} (unique wiki queries: {len(wiki_cache)})")
    log(
        f"Selesai: sukses={report_payload['success_count']}, gagal={len(failed_slugs)}, "
        f"sumber={by_source}, total={report_payload['total_bytes'] / 1024 / 1024:.2f} MB"
    )
    return 1 if failed_slugs else 0


if __name__ == "__main__":
    raise SystemExit(main())
