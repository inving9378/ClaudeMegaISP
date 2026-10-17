{{-- Botón principal del formulario: muestra el texto de data-texto-enviando del form mientras envía. --}}
<button type="submit" class="pl-btn" id="pl-btn">
    <span class="pl-btn-texto">{{ $texto }}</span>
    <svg class="pl-flecha" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
    <span class="pl-spinner" aria-hidden="true"></span>
</button>
