@canany(['scheduling_view_scheduling', 'scheduling_task_view_task'])
    <li>
        <a href="javascript: void(0);" class="has-arrow">
            <i data-feather="check-square"></i>
            <span data-key="t-scheduling">{{ $item->sidebar_label ?? 'Scheduling' }}</span>
        </a>
        <ul class="sub-menu" aria-expanded="false">
            @can('scheduling_task_view_task')
                <li><a href="{{ url('/scheduling/task') }}"><span><small><i class="fa fa-fw fa-tasks"></i></small> Tareas</span></a></li>
            @endcan
            @can('scheduling_project_view_project')
                <li><a href="{{ url('/scheduling/project') }}"><span><small><i class="fa fa-fw fa-project-diagram"></i></small> Proyectos</span></a></li>
            @endcan
            @can('scheduling_view_calendar')
                <li><a href="{{ url('/scheduling/task/calendar') }}"><span><small><i class="fa fa-fw fa-calendar-alt"></i></small> Calendario</span></a></li>
            @endcan
        </ul>
    </li>
@endcanany
