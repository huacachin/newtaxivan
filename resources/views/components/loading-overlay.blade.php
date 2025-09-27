@props([
    // Métodos Livewire separados por coma: "loadData,save,exportar"
    'target' => null,
    // Usa wire:loading.delay para evitar parpadeos en cargas muy rápidas
    'delay' => false,
    // Elevación opcional
    'z' => 50,
])

<div
    class="overlay"
    style="z-index: {{ (int)$z }};"
    @if($delay) wire:loading.delay @else wire:loading @endif
    @if($target) wire:target="{{ $target }}" @endif
    wire:loading.flex
    role="status"
    aria-busy="true"
>
    <div class="spinner" aria-label="Cargando"></div>
</div>
