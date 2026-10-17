<style>
    /* Panel filter "AfterContent" (mis. AntrianBarangMasukResource) ikut
       nge-scroll bareng konten tabel karena default-nya cuma flex sibling
       biasa. Bikin nempel di kanan selagi discroll. */
    .fi-ta-filters-after-content-ctn {
        position: sticky;
        top: 5rem;
        align-self: flex-start;
    }

    /* Sticky Actions Column on Tables (termasuk di mobile agar tombol titik tiga tetap sticky di kanan) */
    .fi-ta-table th:last-child,
    .fi-ta-table td:has(> .fi-ta-actions),
    .fi-ta-table td:last-child {
        position: sticky !important;
        right: 0 !important;
        z-index: 10 !important;
        background-color: #ffffff !important;
        box-shadow: -4px 0 8px -2px rgba(0, 0, 0, 0.05);
        width: 1% !important;
        white-space: nowrap !important;
    }

    :is(.dark .fi-ta-table th:last-child),
    :is(.dark .fi-ta-table td:has(> .fi-ta-actions)),
    :is(.dark .fi-ta-table td:last-child) {
        background-color: #090d16 !important;
        box-shadow: -4px 0 8px -2px rgba(0, 0, 0, 0.3);
    }
</style>
