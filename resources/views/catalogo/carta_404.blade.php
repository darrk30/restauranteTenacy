<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $tenant->name }} - Estamos preparando algo rico</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap"
        rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .bg-pattern {
            background-color: #ffffff;
            background-image: radial-gradient(#0f643b 0.5px, transparent 0.5px), radial-gradient(#0f643b 0.5px, #ffffff 0.5px);
            background-size: 20px 20px;
            background-position: 0 0, 10px 10px;
            opacity: 0.05;
        }

        [x-cloak] {
            display: none !important;
        }
    </style>
</head>

<body
    class="bg-white text-gray-900 min-h-screen flex flex-col items-center justify-center p-4 md:p-6 relative overflow-hidden">

    {{-- Fondo decorativo responsivo --}}
    <div class="absolute inset-0 bg-pattern"></div>
    <div
        class="absolute -top-12 -right-12 md:-top-24 md:-right-24 w-48 h-48 md:w-80 md:h-80 bg-[#0f643b]/10 rounded-full blur-3xl">
    </div>
    <div
        class="absolute -bottom-12 -left-12 md:-bottom-24 md:-left-24 w-48 h-48 md:w-80 md:h-80 bg-orange-500/10 rounded-full blur-3xl">
    </div>

    {{-- Contenedor Principal --}}
    <div class="w-full max-w-md mx-auto text-center relative z-10 flex flex-col items-center">

        {{-- Logo o Nombre Adaptativo --}}
        <div class="mb-8 md:mb-12 transition-all">
            @if ($tenant && $tenant->logo)
                <img src="{{ asset('storage/' . $tenant->logo) }}" alt="{{ $tenant->name }}"
                    class="h-12 md:h-16 w-auto object-contain drop-shadow-sm mx-auto">
            @else
                <span class="text-2xl md:text-4xl font-black text-[#0f643b] tracking-tighter">
                    {{ $tenant->name ?? 'Kipu' }}
                </span>
            @endif
        </div>

        {{-- Ilustración / Icono con Escala Proporcional --}}
        <div class="mb-6 md:mb-10 relative inline-block">
            <div
                class="bg-green-50 w-20 h-20 md:w-28 md:h-28 rounded-[2rem] md:rounded-[2.5rem] flex items-center justify-center mx-auto rotate-12 transition-transform hover:rotate-0 duration-500">
                <svg class="w-10 h-10 md:w-14 md:h-14 text-[#0f643b] -rotate-12" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div
                class="absolute -bottom-1 -right-1 md:-bottom-2 md:-right-2 bg-orange-500 text-white p-2 md:p-3 rounded-full shadow-xl border-4 border-white">
                <svg class="w-3 h-3 md:w-5 md:h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M5 4a2 2 0 012-2h6a2 2 0 012 2v14l-5-2.5L5 18V4z"></path>
                </svg>
            </div>
        </div>

        {{-- Textos con tipografía fluida --}}
        <div class="px-2">
            <h2 class="text-xl md:text-3xl font-extrabold text-gray-800 mb-3 md:mb-5 leading-tight">
                Estamos tomando un pequeño respiro
            </h2>

            <p
                class="text-sm md:text-base text-gray-500 mb-8 md:mb-12 leading-relaxed max-w-[320px] md:max-w-none mx-auto">
                Nuestra cocina está descansando para volver con más sabor. Muy pronto nuestra carta digital estará
                disponible para ti.
            </p>
        </div>

        {{-- Acciones (Botones de ancho completo en móvil) --}}
        <div class="w-full space-y-4 px-4 md:px-0">
            @php
                $cleanPhone = $tenant && $tenant->phone ? preg_replace('/[^0-9]/', '', $tenant->phone) : '';
            @endphp

            @if ($cleanPhone)
                <a href="https://wa.me/{{ $cleanPhone }}"
                    class="flex items-center justify-center gap-3 w-full bg-[#0f643b] text-white font-bold py-4 rounded-2xl shadow-lg shadow-green-900/20 hover:bg-[#0c4e2e] transition-all active:scale-95 text-sm md:text-base">
                    <svg class="w-5 h-5 md:w-6 md:h-6" fill="currentColor" viewBox="0 0 24 24">
                        <path
                            d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zM6.654 20.193c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z" />
                    </svg>
                    Escríbenos por WhatsApp
                </a>
            @endif

            @if ($tenant && $tenant->address)
                <div
                    class="text-[11px] md:text-xs text-gray-400 font-medium pt-4 bg-white/50 backdrop-blur-sm p-3 rounded-xl border border-gray-100">
                    <span class="uppercase tracking-widest text-gray-400 block mb-1">Visítanos en</span>
                    <span class="text-gray-700 block">{{ $tenant->address }}</span>
                </div>
            @endif
        </div>

    </div>

    {{-- Footer Fijo en la parte inferior --}}
    <footer class="mt-auto pt-10 pb-6 text-center w-full relative z-10">
        <p class="text-[9px] md:text-[10px] uppercase tracking-[3px] text-gray-400 font-bold">
            Desarrollado por <a href="https://tukipu.cloud" target="_blank" rel="noopener noreferrer"
                class="text-[#0f643b] hover:text-orange-500 transition-colors duration-300 decoration-none">
                TuKipu
            </a>
        </p>
    </footer>

</body>

</html>
