@extends('meganet.layout.master')
@section('title') @lang('translation.Dashboard') @endsection

@section('css')
    <link href="{{ URL::asset('plugins/datatables.net-buttons-bs4/css/buttons.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ URL::asset('plugins/datatables.net-dt/css/jquery.dataTables.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ URL::asset('assets/libs/toastr/toastr.min.css')}}" rel="stylesheet" type="text/css" />
@endsection

@section('content')
    <dashboard-crm

    ></dashboard-crm>


@endsection

@section('script')

    <script src="{{ URL::asset('assets/libs/datatables/datatables.min.js')}}"></script>
    <script src="{{ URL::asset('assets/libs/jszip/jszip.min.js')}}"></script>
    <script src="{{ URL::asset('assets/libs/pdfmake/pdfmake.min.js')}}"></script>
    <script src="{{ URL::asset('assets/js/pages/datatables.init.js')}}"></script>
    <script src="{{ URL::asset('assets/libs/gmaps/index.min.js')}}"></script>
    <script src="{{ URL::asset('assets/libs/gmaps/index.js')}}"></script>

    <script>
        // $(document).ready(function () {
        //
        //     $('#assigned').DataTable();
        //
        //     $('#assignedadmin').DataTable({
        //         "columnDefs": [{
        //             "targets": [3],
        //             "type": "num-html"
        //         }]
        //     });
        // }


    </script>
@endsection
