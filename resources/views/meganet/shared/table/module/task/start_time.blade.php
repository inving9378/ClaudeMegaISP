<td>
    @if ($start_time)
        <span class="task-chip {{ $atrasada ? 'task-chip-atrasada' : 'task-chip-fecha' }}"
            @if ($atrasada) title="Ya pasó su fecha y sigue abierta" @endif>
            @if ($atrasada)<i class="fas fa-exclamation-circle"></i>@endif
            {{ $start_time }}
        </span>
    @endif
</td>
