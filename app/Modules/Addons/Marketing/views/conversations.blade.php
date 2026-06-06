@extends('core-layout::master')

@section('content')
<div>
    <marketing-conversations-view
        :initial-conversation-id="{{ isset($conversationId) ? (int)$conversationId : 'null' }}"
        :auth-user-id="{{ auth()->id() }}"
    ></marketing-conversations-view>
</div>
@endsection
