<td>
    @foreach($buttons as $button)
        @foreach($button as $key => $v)
            <a
                class="@if(isset($button[$key]['class'])) {{ $button[$key]['class'] }} @endif"
                href="javascript:void(0);"
                @if($key == 'modal')
                toggle-modal="{{ $button[$key]['ref'] }}"
                @endif
                id-item="{{ $id }}"
                data-toggle="tooltip"
                data-placement="top"
                title="{{ $button[$key]['title'] }}"
            ><i class="{{ $button[$key]['iclass'] }}"></i></a>
        @endforeach
    @endforeach
</td>
