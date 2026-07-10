@if(auth()->user()->canAny(['marketing.leads.view', 'marketing.leads.view', 'marketing.conversations.view',
         'marketing.forms.view', 'marketing.forms.create', 'marketing.campaigns.view',
         'marketing.templates.manage', 'view-video-templates', 'generate-video-content',
         'create-marketing-campaigns', 'marketing.brand_kit.configure', 'test-voices',
         'view-publishing-dashboard', 'publish-content', 'manage-publication-queue',
         'manage-publishing-channels']))
    <li>
        <a href="javascript: void(0);" class="has-arrow">
            <i data-feather="target"></i>
            <span data-key="t-marketing">{{ $item->sidebar_label ?? 'Marketing' }}</span>
        </a>
        <ul class="sub-menu" aria-expanded="false">
            @if(auth()->user()->canAny(['marketing.leads.view', 'marketing.leads.view']))
                <li><a href="{{ url('/marketing/leads') }}"><span data-key="t-mkt-leads"><small><i class="fa fa-fw fa-user-tag"></i></small> Leads</span></a></li>
            @endcanany
            @if(auth()->user()->can('marketing.conversations.view'))
                <li><a href="{{ url('/marketing/conversations') }}"><span data-key="t-mkt-conv"><small><i class="fa fa-fw fa-comments"></i></small> Conversaciones</span></a></li>
            @endif
            @if(auth()->user()->canAny(['marketing.forms.view', 'marketing.forms.create']))
                <li><a href="{{ url('/marketing/lead-forms') }}"><span data-key="t-mkt-forms"><small><i class="fa fa-fw fa-wpforms"></i></small> Formularios</span></a></li>
            @endcanany
            @if(auth()->user()->can('marketing.campaigns.view'))
                <li><a href="{{ url('/marketing') }}"><span data-key="t-mkt-camp"><small><i class="fa fa-fw fa-bullhorn"></i></small> Campañas</span></a></li>
            @endif
            @if(auth()->user()->can('marketing.templates.manage'))
                <li><a href="{{ url('/marketing') }}?tab=templates"><span data-key="t-mkt-tpl"><small><i class="fa fa-fw fa-file-alt"></i></small> Plantillas</span></a></li>
            @endif
            @if(auth()->user()->canAny(['view-video-templates', 'generate-video-content']))
                <li><a href="{{ url('/marketing/video-templates') }}"><span data-key="t-mkt-video"><small><i class="fa fa-fw fa-video"></i></small> Video</span></a></li>
            @endcanany
            @if(auth()->user()->can('create-marketing-campaigns'))
                <li><a href="{{ url('/marketing/campaigns/generate') }}"><span data-key="t-mkt-ia"><small><i class="fa fa-fw fa-magic"></i></small> Campaña IA</span></a></li>
            @endif
            @if(auth()->user()->can('marketing.brand_kit.configure'))
                <li><a href="{{ url('/marketing/brand-kit') }}"><span data-key="t-mkt-bk"><small><i class="fa fa-fw fa-palette"></i></small> Brand Kit</span></a></li>
            @endif
            @if(auth()->user()->can('test-voices'))
                <li><a href="{{ url('/marketing/voice-comparator') }}"><span data-key="t-mkt-vc"><small><i class="fa fa-fw fa-microphone"></i></small> Voces TTS</span></a></li>
            @endif
            @if(auth()->user()->can('view-publishing-dashboard'))
                <li><a href="{{ url('/marketing/publishing') }}"><span data-key="t-mkt-pub"><small><i class="fa fa-fw fa-broadcast-tower"></i></small> Publicador</span></a></li>
            @endif
            @if(auth()->user()->can('publish-content'))
                <li><a href="{{ url('/marketing/publishing/campaign') }}"><span data-key="t-mkt-pubcam"><small><i class="fa fa-fw fa-paper-plane"></i></small> Publicar</span></a></li>
            @endif
            @if(auth()->user()->can('manage-publication-queue'))
                <li><a href="{{ url('/marketing/publishing/queue') }}"><span data-key="t-mkt-queue"><small><i class="fa fa-fw fa-list-ol"></i></small> Cola</span></a></li>
            @endif
            @if(auth()->user()->can('manage-publishing-channels'))
                <li><a href="{{ url('/marketing/publishing/setup') }}"><span data-key="t-mkt-setup"><small><i class="fa fa-fw fa-plug"></i></small> Canales</span></a></li>
            @endif
        </ul>
    </li>
@endcanany
