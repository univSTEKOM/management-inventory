@php
    $photos = $record?->photos?->sortBy('sort_order') ?? collect();
    $itemName = $record?->name ?? 'Barang';

    $photoList = [];
    foreach ($photos as $index => $photo) {
        if (! filled($photo->file_path)) {
            continue;
        }

        $fullUrl = $photo->url;
        $thumbUrl = \Illuminate\Support\Facades\Storage::disk('s3_thumb')->url($photo->file_path);
        $downloadUrl = route('asset.photo.download', [
            'path' => $photo->file_path,
            'name' => ($record?->name ? \Illuminate\Support\Str::slug($record->name, '_') : 'barang') . '_foto_' . ($index + 1),
        ]);

        $photoList[] = [
            'index' => $index,
            'number' => $index + 1,
            'full_url' => $fullUrl,
            'thumb_url' => $thumbUrl,
            'download_url' => $downloadUrl,
            'file_path' => $photo->file_path,
        ];
    }
@endphp

<div
    x-data="{
        selectedPhoto: null,
        toggle(idx) {
            this.selectedPhoto = (this.selectedPhoto === idx) ? null : idx;
        },
        close() {
            this.selectedPhoto = null;
        }
    }"
    @click.outside="close()"
    class="w-full"
>
    @if (empty($photoList))
        <div class="flex flex-col items-center justify-center p-4 border-2 border-dashed border-gray-200 dark:border-white/10 rounded-xl text-center bg-gray-50/50 dark:bg-white/[0.02]" style="max-width: 200px;">
            <div class="p-2 bg-gray-100 dark:bg-white/5 rounded-full text-gray-400 dark:text-gray-500 mb-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z" />
                </svg>
            </div>
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Belum ada foto</p>
        </div>
    @else
        <div class="flex flex-wrap items-center gap-3">
            @foreach ($photoList as $i => $item)
                <div
                    class="relative rounded-xl overflow-hidden border border-gray-200 dark:border-white/10 shadow-sm bg-gray-50 dark:bg-gray-900 flex-shrink-0"
                    style="width: 160px; height: 160px;"
                >
                    <div
                        @click="toggle({{ $i }})"
                        class="relative w-full h-full flex items-center justify-center cursor-pointer select-none bg-gray-100 dark:bg-gray-950 p-1.5"
                        title="Klik untuk opsi Lihat atau Download"
                    >
                        {{-- Foto Barang: proporsional tidak gepeng, ukuran rapi 160x160 --}}
                        <img
                            src="{{ $item['thumb_url'] }}"
                            onerror="this.onerror=null; this.src='{{ $item['full_url'] }}';"
                            alt="Foto {{ $item['number'] }} - {{ $itemName }}"
                            style="width: 100%; height: 100%; object-fit: contain; display: block; transition: filter 0.25s ease, transform 0.25s ease;"
                            :style="selectedPhoto === {{ $i }} ? 'filter: blur(4px) brightness(0.6); transform: scale(1.05);' : 'filter: none; transform: scale(1);'"
                            loading="lazy"
                        >

                        {{-- Badge nomor foto jika lebih dari 1 foto --}}
                        @if (count($photoList) > 1)
                            <div
                                x-show="selectedPhoto !== {{ $i }}"
                                class="absolute top-1.5 left-1.5 bg-black/60 backdrop-blur-md text-white text-[9px] font-semibold px-1.5 py-0.5 rounded pointer-events-none"
                            >
                                #{{ $item['number'] }}
                            </div>
                        @endif

                        {{-- 2 CTA di atas foto saat foto diklik dan jadi burem --}}
                        <div
                            x-show="selectedPhoto === {{ $i }}"
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 scale-90"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-90"
                            class="absolute inset-0 flex flex-col items-center justify-center gap-1.5 p-2 z-10"
                            style="background: rgba(0, 0, 0, 0.45); display: none;"
                        >
                            {{-- Tombol Tutup Silang di pojok kanan atas --}}
                            <button
                                type="button"
                                @click.stop="close()"
                                class="absolute top-1.5 right-1.5 p-0.5 rounded-full text-white/70 hover:text-white bg-black/40 hover:bg-black/60 transition-colors"
                                title="Batal / Tutup"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>

                            {{-- Wadah 2 CTA --}}
                            <div class="flex flex-col items-stretch gap-1.5 w-full px-1" @click.stop>
                                {{-- CTA 1: Lihat Foto --}}
                                <a
                                    href="{{ $item['full_url'] }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center justify-center gap-1 py-1 px-2 rounded-md text-[11px] font-semibold bg-white text-gray-900 hover:bg-gray-100 shadow transition-all hover:scale-102 active:scale-98 whitespace-nowrap text-center"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-primary-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <span>Lihat Foto</span>
                                </a>

                                {{-- CTA 2: Download --}}
                                <a
                                    href="{{ $item['download_url'] }}"
                                    download
                                    class="inline-flex items-center justify-center gap-1 py-1 px-2 rounded-md text-[11px] font-semibold bg-primary-600 text-white hover:bg-primary-500 shadow transition-all hover:scale-102 active:scale-98 whitespace-nowrap text-center"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                    </svg>
                                    <span>Download</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
