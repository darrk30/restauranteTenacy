<x-filament-panels::page>

    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/ordenmesa.css') }}">
        <link rel="stylesheet" href="{{ asset('css/ordenmesadarrk.css') }}">
    @endpush

    @push('scripts')
        <script src="{{ asset('js/ordenmesa.js') }}" defer></script>
    @endpush

    <livewire:pedido-mesa :tenant="$tenant" :mesa="$mesa" :pedido="$pedido" />

</x-filament-panels::page>
