<x-filament-panels::page>

    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/ordenmesa.css') }}">
    @endpush

    @push('scripts')
        <script src="{{ asset('js/ordenmesa.js') }}" defer></script>
    @endpush

    <livewire:pedido-mesa :tenant="$tenant" :mesa="$mesa" />

</x-filament-panels::page>
