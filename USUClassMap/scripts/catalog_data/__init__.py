"""Modul data katalog master fotokopi & ATK USUClassMap."""

from .jasa import ITEMS as JASA_ITEMS
from .barang_atk import ITEMS as BARANG_ATK_ITEMS
from .barang_kertas_map import ITEMS as BARANG_KERTAS_MAP_ITEMS
from .barang_niche import ITEMS as BARANG_NICHE_ITEMS

ALL_MODULES = [
    JASA_ITEMS,
    BARANG_ATK_ITEMS,
    BARANG_KERTAS_MAP_ITEMS,
    BARANG_NICHE_ITEMS,
]
