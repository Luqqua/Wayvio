@extends('layouts.sidebar')

@section('content')
<div class="conatiner-fluid content-inner mt-n5 py-0">
    <div class="row">
        <div class="col-lg-12">
            {{-- MODULE: AnalyticsPremium include, does NOT belong to Core --}}
            @includeIf('modules.AnalyticsPremium.views.premium-overview')
        </div>
    </div>
</div>
@endsection
