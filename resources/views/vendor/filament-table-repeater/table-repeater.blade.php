@php
    $containers = $getChildComponentContainers();
    $addAction = $getAction($getAddActionName());
    $deleteAction = $getAction($getDeleteActionName());
    $isAddable = $isAddable();
    $isDeletable = $isDeletable();
    $isReorderable = $isReorderable();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div x-data="{ isCollapsed: @js($isCollapsed()) }"
        {{ $attributes->merge($getExtraAttributes())->class(['bg-white border border-gray-300 shadow-sm rounded-xl relative dark:bg-gray-800 dark:border-gray-600']) }}>
        @if (count($containers) > 0)
            <div
                class="hidden md:flex gap-4 px-4 py-2  rounded-t-xl border-b  font-medium text-xs text-gray-500 uppercase tracking-wider">
                @foreach ($containers[array_key_first($containers)]->getComponents() as $component)
                    @if (!$component instanceof \Filament\Forms\Components\Hidden)
                        <div class="flex-1">
                            {{ $component->getLabel() }}
                        </div>
                    @endif
                @endforeach
                @if ($isDeletable)
                    <div class="w-10"></div>
                @endif
            </div>
        @endif

        {{-- LISTA DE FILAS --}}
        <div class="divide-y">
            @foreach ($containers as $uuid => $item)
                <div x-sortable-item="{{ $uuid }}"
                    class="flex flex-col md:flex-row gap-4 p-4 items-start md:items-center">
                    @foreach ($item->getComponents() as $component)
                        @if (!$component instanceof \Filament\Forms\Components\Hidden)
                            <div class="w-full md:flex-1">
                                {{-- Label Móvil: Solo se ve en celular (md:hidden) para saber qué campo es --}}
                                <div class="md:hidden text-xs font-medium text-gray-500 uppercase mb-1">
                                    {{ $component->getLabel() }}
                                </div>
                                {{-- El Input --}}
                                {{ $component }}
                            </div>
                        @endif
                    @endforeach
                    @if ($isDeletable)
                        <div>
                            {{ $deleteAction(['item' => $uuid]) }}
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
        {{-- BOTÓN AGREGAR --}}
        @if ($isAddable && $addAction->isVisible())
            <div class="flex justify-center p-2 border-t dark:border-gray-700">
                {{ $addAction }}
            </div>
        @endif
    </div>
</x-dynamic-component>
