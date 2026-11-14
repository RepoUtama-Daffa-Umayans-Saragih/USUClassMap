@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="py-6 w-full flex flex-col items-center justify-center gap-3">
        
        {{-- Bagian Abu-Abu Muda Navigasi Pindah Halaman (PAS TEPAT DI TENGAH) --}}
        <div class="flex justify-center items-center w-full">
            <div class="inline-flex items-center shadow-sm rounded-xl overflow-hidden border border-gray-200 bg-gray-100 text-gray-700 text-xs divide-x divide-gray-200">
                
                {{-- Previous Page Link --}}
                @if ($paginator->onFirstPage())
                    <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}" class="px-3.5 py-2 text-gray-300 cursor-not-allowed bg-gray-50 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path></svg>
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('pagination.previous') }}" class="px-3.5 py-2 text-gray-600 hover:text-gray-900 hover:bg-gray-200/80 transition-colors flex items-center justify-center cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path></svg>
                    </a>
                @endif

                {{-- Pagination Elements --}}
                @foreach ($elements as $element)
                    {{-- "Three Dots" Separator --}}
                    @if (is_string($element))
                        <span class="px-3.5 py-2 text-gray-400 bg-gray-100 cursor-default select-none font-medium">
                            {{ $element }}
                        </span>
                    @endif

                    {{-- Array Of Links --}}
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page" class="px-4 py-2 font-bold text-white bg-[#10B981] flex items-center justify-center shadow-xs select-none">
                                    {{ $page }}
                                </span>
                            @else
                                <a href="{{ $url }}" class="px-4 py-2 text-gray-700 hover:text-gray-900 hover:bg-gray-200 transition-colors flex items-center justify-center font-medium cursor-pointer" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">
                                    {{ $page }}
                                </a>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                {{-- Next Page Link --}}
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('pagination.next') }}" class="px-3.5 py-2 text-gray-600 hover:text-gray-900 hover:bg-gray-200/80 transition-colors flex items-center justify-center cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    </a>
                @else
                    <span aria-disabled="true" aria-label="{{ __('pagination.next') }}" class="px-3.5 py-2 text-gray-300 cursor-not-allowed bg-gray-50 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    </span>
                @endif

            </div>
        </div>

        {{-- Informasi Jumlah Data (Pas di Tengah) --}}
        <div class="text-xs text-gray-500 font-medium text-center">
            {!! __('Showing') !!}
            <span class="font-bold text-gray-800">{{ $paginator->firstItem() ?? 0 }}</span>
            {!! __('to') !!}
            <span class="font-bold text-gray-800">{{ $paginator->lastItem() ?? 0 }}</span>
            {!! __('of') !!}
            <span class="font-bold text-gray-800">{{ $paginator->total() }}</span>
            {!! __('results') !!}
        </div>

    </nav>
@endif
