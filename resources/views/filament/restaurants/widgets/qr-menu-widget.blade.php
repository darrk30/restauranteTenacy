<x-filament::widget>
    <x-filament::card>
        <div x-data="{
                isDownloading: false,
                descargarImagen(tenantSlug) {
                    this.isDownloading = true;
                    let originalSvg = document.querySelector('#qr-svg-container svg');
                    if (!originalSvg) { this.isDownloading = false; return; }
            
                    let svg = originalSvg.cloneNode(true);
                    let size = 1000;
                    let canvas = document.createElement('canvas');
                    canvas.width = size; canvas.height = size;
                    let ctx = canvas.getContext('2d');
            
                    svg.setAttribute('width', size);
                    svg.setAttribute('height', size);
                    if (!svg.getAttribute('xmlns')) { svg.setAttribute('xmlns', 'http://www.w3.org/2000/svg'); }
            
                    let data = new XMLSerializer().serializeToString(svg);
                    let svgUrl = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(data);
            
                    let img = new Image();
                    img.onload = () => {
                        ctx.fillStyle = '#ffffff';
                        ctx.fillRect(0, 0, canvas.width, canvas.height);
                        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
            
                        let base64 = canvas.toDataURL('image/png');
                        this.$wire.procesarDescargaPng(base64).then(() => {
                            this.isDownloading = false;
                        });
                    };
                    img.src = svgUrl;
                }
            }" 
            class="flex flex-col items-center text-center space-y-4"
        >
            
            <div class="flex items-center justify-between w-full border-b pb-4">
                <h2 class="text-xl font-bold">Tu Menú Digital (QR)</h2>
                
                {{-- 🟢 AQUÍ RENDERIZAMOS EL TOGGLE NATIVO DE FILAMENT --}}
                <div>
                    {{ $this->form }}
                </div>
            </div>
            
            {{-- AVISO SI ESTÁ BLOQUEADO POR ADMIN --}}
            @if(!$cartaActivaAdmin)
                <div class="w-full bg-red-50 text-red-600 p-3 rounded-lg text-sm border border-red-200 flex items-center gap-2 text-left">
                    <x-heroicon-o-lock-closed class="w-5 h-5 flex-shrink-0" />
                    <span><b>Servicio Inactivo:</b> Tu plan actual no incluye la carta digital. Para activarla, mejora tu suscripción.</span>
                </div>
            @endif

            {{-- Variables para saber si mostrar el QR opaco o normal --}}
            @php
                $estaActivo = ($data['carta_activa_cliente'] ?? false) && $cartaActivaAdmin;
            @endphp

            <p class="text-sm {{ $estaActivo ? 'text-gray-500' : 'text-red-500 font-bold' }}">
                {{ $estaActivo ? 'Escanea este código para ver la carta pública.' : '¡Tu carta está oculta! Los clientes no podrán verla.' }}
            </p>

            {{-- CÓDIGO QR --}}
            <div id="qr-svg-container" class="p-4 bg-white rounded-xl shadow-sm border inline-block transition-opacity {{ $estaActivo ? 'opacity-100' : 'opacity-30' }}">
                {!! $this->getQrCodeHtml() !!}
            </div>

            <div class="flex gap-3 justify-center w-full mt-2">
                <x-filament::button tag="a" href="{{ route('carta.digital', filament()->getTenant()->slug) }}" target="_blank" color="gray">
                    Ver Carta
                </x-filament::button>

                <x-filament::button @click="descargarImagen('{{ filament()->getTenant()->slug }}')" x-bind:disabled="isDownloading" color="info" icon="heroicon-o-arrow-down-tray">
                    <span x-show="!isDownloading">Descargar Imagen PNG</span>
                    <span x-show="isDownloading">Generando Archivo...</span>
                </x-filament::button>
            </div>
        </div>
    </x-filament::card>
</x-filament::widget>