@extends('layouts.sidebar')

@section('content')
<div class="conatiner-fluid content-inner mt-n5 pt-0 pb-4 ls-consistent-spacing">
    <div class="row">
        <div class="col-lg-12">
            @includeIf('modules.CustomDomains.views.agency-settings')
        </div>
    </div>
</div>
@endsection
