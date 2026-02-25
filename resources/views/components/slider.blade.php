@props(['slides' => [], 'interval' => 4000])

@if (count($slides) > 0)
    {{-- 🟢 Agregamos 'app-slider-wrapper' y 'data-interval' para que el JS lo detecte --}}
    <div class="app-slider-wrapper relative w-full max-w-6xl mx-auto group overflow-hidden rounded-2xl md:rounded-3xl shadow-lg mb-10 bg-gray-100" data-interval="{{ $interval }}">

        <div class="slider-track flex overflow-x-auto snap-x snap-mandatory scroll-smooth no-scrollbar cursor-grab h-64 md:h-96 relative">

            @foreach ($slides as $index => $slide)
                <div class="slide-item w-full shrink-0 snap-center relative flex items-center h-full overflow-hidden transition-all duration-700 {{ $index === 0 ? 'slide-active' : '' }} {{ $slide['type'] === 'full_image' ? 'bg-full-slider' : '' }}"
                    style="{{ $slide['type'] !== 'full_image' ? 'background-color: ' . ($slide['bg_color'] ?? '#ce6439') : '' }}"
                    data-index="{{ $index }}">

                    @if (!empty($slide['link']) && $slide['type'] === 'full_image')
                        <a href="{{ $slide['link'] }}" class="absolute inset-0 z-30"></a>
                    @endif

                    @if ($slide['type'] === 'full_image')
                        <picture class="w-full h-full flex justify-center items-center">
                            <source media="(max-width: 767px)" srcset="{{ $slide['image_mobile'] ?? $slide['image'] }}">
                            <img src="{{ $slide['image'] }}" class="img-fit-full" alt="Promoción">
                        </picture>
                    @else
                        <div class="relative w-full h-full flex items-center px-8 md:px-16 z-20">
                            <div class="w-full {{ $slide['type'] === 'mixed' ? 'md:w-3/5' : 'text-center' }} slider-content text-white animate-text">
                                {!! $slide['title'] !!}
                            </div>

                            @if ($slide['type'] === 'mixed' && !empty($slide['image']))
                                <div class="mixed-image-container absolute right-0 bottom-0 top-0 w-1/2 md:w-2/5 flex items-center justify-center p-4 pointer-events-none z-10">
                                    <picture class="w-full h-full">
                                        <source media="(max-width: 767px)" srcset="{{ $slide['image_mobile'] ?? $slide['image'] }}">
                                        <img src="{{ $slide['image'] }}" class="w-full h-full object-contain drop-shadow-2xl">
                                    </picture>
                                </div>
                            @endif
                        </div>
                        <div class="absolute inset-0 bg-black/20 md:hidden z-0"></div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Controles --}}
        <button class="prev-btn absolute left-4 top-1/2 -translate-y-1/2 bg-white/20 backdrop-blur-md hover:bg-white text-white hover:text-gray-900 p-3 rounded-full z-40 opacity-0 group-hover:opacity-100 transition-all hidden md:flex">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7"></path></svg>
        </button>
        <button class="next-btn absolute right-4 top-1/2 -translate-y-1/2 bg-white/20 backdrop-blur-md hover:bg-white text-white hover:text-gray-900 p-3 rounded-full z-40 opacity-0 group-hover:opacity-100 transition-all hidden md:flex">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7"></path></svg>
        </button>

        <div class="absolute bottom-5 left-0 right-0 flex justify-center gap-3 z-40">
            @foreach ($slides as $index => $slide)
                <button class="dot h-1.5 rounded-full transition-all duration-500 {{ $index === 0 ? 'bg-white w-10' : 'bg-white/40 w-4' }}" data-index="{{ $index }}"></button>
            @endforeach
        </div>
    </div>
@endif