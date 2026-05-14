
<ol class="q-pa-none q-ma-none" style="padding-left: 0px">

@foreach($sellers as $c)
<li>
    -{{$c->full_name}}
</li>
@endforeach
</ol>
