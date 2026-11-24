<?php

namespace App\Support;

class CatalogBehaviorClassifier
{
    public const DIGITAL_PRINT = 'digital_print';
    public const HYBRID_FINISHING = 'hybrid_finishing';
    public const PHYSICAL_ONLY = 'physical_only';
    public const PHYSICAL_GOODS = 'physical_goods';

    /**
     * Deterministically classify a catalog entry into one of 4 contextual order behaviors:
     * - physical_goods: Tangible ATK/niche retail products (`- qty +`)
     * - hybrid_finishing: Laminating & Jilid (Offline physical OR Print+Finishing PDF bundle)
     * - physical_only: Services strictly requiring original physical documents/objects (Fotokopi, Scan, Guillotine)
     * - digital_print: Print/Cetak/Foto/Burn/Layout services requiring file upload (Multi-PDF)
     */
    public static function classify(
        ?string $itemType,
        ?string $category,
        ?string $subCategory,
        ?string $name
    ): string {
        if (strtolower(trim((string) $itemType)) === 'barang') {
            return self::PHYSICAL_GOODS;
        }

        $cat = strtolower(trim((string) $category));
        $sub = strtolower(trim((string) $subCategory));
        $title = strtolower(trim((string) $name));
        $combined = "{$cat} {$sub} {$title}";

        // Hybrid Finishing: Laminating & Jilid
        if ($cat === 'jilid' || preg_match('/\b(laminat\w*|jilid\w*)\b/i', $combined)) {
            return self::HYBRID_FINISHING;
        }

        // Physical Only: Fotokopi, Scan fisik, Potong kertas mesin, Lipat brosur fisik
        if (
            $cat === 'fotocopy'
            || preg_match('/\b(fotokopi|fotocopy|scan|potong\s+kertas|guillotine|lipat\s+brosur)\b/i', $combined)
        ) {
            return self::PHYSICAL_ONLY;
        }

        return self::DIGITAL_PRINT;
    }

    /**
     * Determine if a finishing/laminating service is charged per printed sheet (`lembar`/`pcs`)
     * versus per bound copy/book (`buku`/`jilid`).
     */
    public static function isPerSheetFinishing(?string $unit, ?string $name): bool
    {
        $title = strtolower(trim((string) $name));
        $u = strtolower(trim((string) $unit));

        if (str_contains($title, 'laminat')) {
            return in_array($u, ['lembar', 'pcs', 'lbr', ''], true);
        }

        return false;
    }

    /**
     * Return standardized UI/API behavior metadata for a catalog item model or array.
     *
     * @param  array<string, mixed>|object  $item
     * @return array<string, mixed>
     */
    public static function metadataForItem(array|object $item): array
    {
        $itemType = is_array($item) ? ($item['item_type'] ?? null) : ($item->item_type ?? null);
        $category = is_array($item) ? ($item['category'] ?? null) : ($item->category ?? null);
        $subCategory = is_array($item) ? ($item['sub_category'] ?? null) : ($item->sub_category ?? null);
        $name = is_array($item) ? ($item['name'] ?? null) : ($item->name ?? null);
        $unit = is_array($item) ? ($item['unit'] ?? null) : ($item->unit ?? null);

        $behavior = self::classify($itemType, $category, $subCategory, $name);

        [$badge, $cta] = match ($behavior) {
            self::DIGITAL_PRINT => ['Upload PDF', 'Pilih & Unggah PDF'],
            self::HYBRID_FINISHING => ['Offline / Bundel PDF', 'Pilih Opsi'],
            self::PHYSICAL_ONLY => ['Khusus Berkas Fisik (Offline)', 'Pesan Offline'],
            default => ['Barang ATK', 'Tambah'],
        };

        return [
            'order_behavior' => $behavior,
            'behavior_badge' => $badge,
            'behavior_cta' => $cta,
            'is_per_sheet_finishing' => self::isPerSheetFinishing($unit, $name),
        ];
    }
}
