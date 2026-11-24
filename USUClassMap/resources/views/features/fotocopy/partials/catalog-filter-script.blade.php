<script>
if (!window.usuCatalogFilter) {
    window.usuCatalogFilter = function (items) {
        const normalized = Array.isArray(items)
            ? items.map(function (it) {
                return Object.assign({}, it, { _imgError: false });
            })
            : [];

        return {
            catalog: normalized,
            catalogTab: 'all',
            catalogSub: '',
            catalogQuery: '',

            setCatalogTab(tab) {
                this.catalogTab = tab;
                this.catalogSub = '';
            },

            catalogCount(type) {
                if (type === 'all') {
                    return this.catalog.length;
                }
                return this.catalog.filter(function (item) {
                    return item.item_type === type;
                }).length;
            },

            catalogSubOptions() {
                const set = new Set();
                const tab = this.catalogTab;
                this.catalog.forEach(function (item) {
                    if (tab !== 'all' && item.item_type !== tab) {
                        return;
                    }
                    if (item.sub_category) {
                        set.add(item.sub_category);
                    }
                });
                return Array.from(set).sort();
            },

            catalogFiltered() {
                const tab = this.catalogTab;
                const sub = this.catalogSub;
                const q = (this.catalogQuery || '').trim().toLowerCase();

                return this.catalog.filter(function (item) {
                    if (tab !== 'all' && item.item_type !== tab) {
                        return false;
                    }
                    if (sub && item.sub_category !== sub) {
                        return false;
                    }
                    if (!q) {
                        return true;
                    }
                    const metaVals = item.metadata && typeof item.metadata === 'object'
                        ? Object.values(item.metadata).join(' ')
                        : '';
                    const haystack = [
                        item.name,
                        item.brand,
                        item.sub_category,
                        item.category,
                        item.unit,
                        item.description,
                        metaVals,
                    ].filter(Boolean).join(' ').toLowerCase();

                    return haystack.includes(q);
                });
            },

            rupiah(n) {
                return 'Rp ' + new Intl.NumberFormat('id-ID').format(Number(n || 0));
            },

            metadataSummary(meta) {
                if (!meta || typeof meta !== 'object') {
                    return '';
                }
                return [
                    meta.ukuran_atau_gramasi,
                    meta.warna_varian,
                    meta.penjualan,
                ].filter(Boolean).join(' • ');
            },
        };
    };
}
</script>
