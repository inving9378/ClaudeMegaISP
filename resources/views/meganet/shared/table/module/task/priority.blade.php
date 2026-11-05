<td>
    @php($p = mb_strtolower(trim((string) $priority)))
    @if ($p !== '')
        <span class="task-chip task-prio-{{ in_array($p, ['alta', 'media', 'baja'], true) ? $p : 'otra' }}">{{ $priority }}</span>
    @endif
</td>
