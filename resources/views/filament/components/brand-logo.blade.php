<div class="flex items-center gap-3.5 h-full py-1" x-data>
    <style>
        .brand-text-wrapper {
            font-family: 'Poppins', 'Montserrat', 'Outfit', system-ui, sans-serif;
            font-size: 1.4rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.2;
            display: inline-flex;
            align-items: center;
            user-select: none;
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 55%, #0284c7 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 1px 1px rgba(37, 99, 235, 0.1));
        }

        :is(.dark) .brand-text-wrapper {
            background: linear-gradient(135deg, #ffffff 0%, #93c5fd 55%, #38bdf8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.3));
        }
    </style>

    <!-- Logo -->
    <img
        src="{{ asset('logo.png') }}"
        alt="Logo STEKOM"
        class="h-full w-auto object-contain shrink-0 py-1 drop-shadow-sm"
    />

    <!-- Teks di samping logo -->
    <div
        class="flex items-center whitespace-nowrap"
        x-show="$store?.sidebar ? $store.sidebar.isOpen : true"
        x-transition
    >
        <span class="brand-text-wrapper">
            Sistem Manajemen Barang
        </span>
    </div>
</div>
