<div class="flex flex-col items-center text-center space-y-4"
    x-data="{
        isDownloading: false,
        descargarImagen() {
            this.isDownloading = true;
            // 🟢 Usamos el ID de la mesa para seleccionar el SVG correcto
            let originalSvg = document.querySelector('#qr-mesa-{{ $mesa->id }} svg');
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
    
                // 🟢 Usamos toBlob para descargar seguro sin HTTPS
                canvas.toBlob((blob) => {
                    let blobUrl = URL.createObjectURL(blob);
                    let a = document.createElement('a');
                    a.download = 'qr-mesa-{{ Str::slug($mesa->name) }}-{{ $tenantSlug }}.png';
                    a.href = blobUrl;
                    a.click();
                    URL.revokeObjectURL(blobUrl);
                    this.isDownloading = false;
                }, 'image/png');
                
                URL.revokeObjectURL(svgUrl); 
            };
            img.src = svgUrl;
        }
    }">
    
    <p class="text-sm text-gray-500">
        Al escanear este QR, el sistema sabrá que el pedido viene automáticamente de la <b>{{ $mesa->name }}</b>.
    </p>

    {{-- Contenedor del QR con ID único por mesa --}}
    <div id="qr-mesa-{{ $mesa->id }}" class="p-4 bg-white rounded-xl shadow-sm border inline-block">
        {!! $qrHtml !!}
    </div>

    <div class="flex gap-3 justify-center w-full mt-2">
        <x-filament::button tag="a" href="{{ $url }}" target="_blank" color="gray">
            Probar Enlace
        </x-filament::button>

        <x-filament::button @click="descargarImagen()" x-bind:disabled="isDownloading" color="info" icon="heroicon-o-arrow-down-tray">
            <span x-show="!isDownloading">Descargar PNG de Mesa</span>
            <span x-show="isDownloading">Generando...</span>
        </x-filament::button>
    </div>
</div>