@if(auth()->user()->canAny(['scheduling_view_scheduling', 'scheduling_task_view_task']))
    <li>
        <a href="javascript: void(0);" class="has-arrow">
            <i data-feather="check-square"></i>
            <span data-key="t-scheduling">{{ $item->sidebar_label ?? 'Scheduling' }}</span>
        </a>
        <ul class="sub-menu" aria-expanded="false">
            @if(auth()->user()->can('scheduling_task_view_task'))
                <li><a href="{{ url('/scheduling/task') }}"><span><small><i class="fa fa-fw fa-tasks"></i></small> Tareas</span></a></li>
            @endif
            @if(auth()->user()->can('scheduling_project_view_project'))
                <li><a href="{{ url('/scheduling/project') }}"><span><small><i class="fa fa-fw fa-project-diagram"></i></small> Proyectos</span></a></li>
            @endif
            @if(auth()->user()->can('scheduling_view_calendar'))
                <li><a href="{{ url('/scheduling/task/calendar') }}"><span><small><i class="fa fa-fw fa-calendar-alt"></i></small> Calendario</span></a></li>
            @endif

            {{-- Hijos dinámicos desde module_sidebar_config (Fase 2.3/3.5) --}}
            @foreach($item->dynamic_children ?? collect() as $child)
                <li><a href="{{ $child->sidebar_url ? url($child->sidebar_url) : url('/' . $child->module_key) }}"><span>@if($child->sidebar_icon)<small><i class="{{ $child->sidebar_icon }}"></i></small> @endif{{ $child->sidebar_label ?? $child->module_key }}</span></a></li>
            @endforeach
        </ul>
    </li>
@endcanany
