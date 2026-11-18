<x-filament::widget class="fi-wi-quick-access">
    @if (!empty($items))
        <div class="space-y-2.5">
            <div class="flex items-center justify-between px-1">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                    <x-filament::icon icon="heroicon-m-bolt" class="w-3.5 h-3.5 text-primary-500" />
                    <span>Akses Cepat</span>
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
                @foreach ($items as $item)
                    <a
                        href="{{ $item['url'] }}"
                        class="flex flex-col justify-between p-4 bg-white dark:bg-gray-900 rounded-xl border border-gray-200/80 dark:border-white/10 shadow-xs hover:border-primary-500/50 hover:bg-primary-50/10 dark:hover:bg-primary-950/20 transition-all duration-150 group"
                    >
                        <div>
                            {{-- Top row: icon + title + badge --}}
                            <div class="flex items-start justify-between gap-2 mb-2.5">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0 {{ $item['icon_bg'] }} group-hover:scale-105 transition-transform">
                                        <x-filament::icon :icon="$item['icon']" class="w-5 h-5" />
                                    </div>
                                    <span class="text-sm font-bold text-gray-900 dark:text-white truncate group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                                        {{ $item['title'] }}
                                    </span>
                                </div>

                                @if (!empty($item['badge']))
                                    <span class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-semibold rounded shrink-0 {{ $item['badge_class'] }}">
                                        {{ $item['badge'] }}
                                    </span>
                                @endif
                            </div>

                            {{-- Deskripsi singkat yang jelas dan informatif --}}
                            <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                                {{ $item['description'] }}
                            </p>
                        </div>

                        {{-- Footer link --}}
                        <div class="mt-3.5 pt-2.5 border-t border-gray-100 dark:border-white/5 flex items-center justify-between text-[11px] font-semibold text-primary-600 dark:text-primary-400 group-hover:underline">
                            <span>Buka menu</span>
                            <x-filament::icon
                                icon="heroicon-m-arrow-right"
                                class="w-3.5 h-3.5 group-hover:translate-x-1 transition-transform"
                            />
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</x-filament::widget>
