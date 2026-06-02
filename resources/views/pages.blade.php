<!DOCTYPE html>
@include('layouts.lang')
@php
  $publicLegalLinks = legalDocumentLinks((string) app()->getLocale());
  $legalLocale = $publicLegalLinks['locale'];
  $footerIsEn = $legalLocale === 'en';
  $footerLabels = [
    'agb' => $footerIsEn ? 'Terms' : 'AGB',
    'avv' => $footerIsEn ? 'DPA' : 'AVV',
    'privacy' => $footerIsEn ? 'Privacy' : 'Privatsphäre',
    'imprint' => $footerIsEn ? 'Imprint' : 'Impressum',
    'contact' => $footerIsEn ? 'Contact' : 'Kontakt',
    'help' => $footerIsEn ? 'Help Center' : 'Hilfecenter',
    'license' => $footerIsEn ? 'License' : 'Lizenz',
  ];
  $helpCenterUrl = $footerIsEn
    ? (Route::has('help.en.index') ? route('help.en.index') : '')
    : (Route::has('help.index') ? route('help.index') : '');
  $contactUrl = (string) ($publicLegalLinks['imprint'] ?? '');
@endphp
<head>
  <meta charset="utf-8">
  <title>{{ucfirst(Request::segment(2))}} - {{ strtoupper((string) config('app.name')) }}</title>

@include('layouts.analytics')

      <!-- Favicon -->
      @if(file_exists(base_path("assets/wayvio/images/").findFile('favicon')))
      <link rel="icon" type="image/png" href="{{ asset('assets/wayvio/images/'.findFile('favicon')) }}">
      @else
      <link rel="icon" type="image/svg+xml" href="{{ asset('assets/wayvio/images/logo.svg') }}">
      @endif
      
      <!-- Library / Plugin Css Build -->
      <link rel="stylesheet" href="{{asset('assets/css/core/libs.min.css')}}" />
      
      <!-- Aos Animation Css -->
      <link rel="stylesheet" href="{{asset('assets/vendor/aos/dist/aos.css')}}" />
      
      @include('layouts.fonts')
      
      <!-- Hope Ui Design System Css -->
      <link rel="stylesheet" href="{{asset('assets/css/hope-ui.min.css?v=2.0.0')}}" />
      
      <!-- Custom Css -->
      <link rel="stylesheet" href="{{asset('assets/css/custom.min.css?v=2.0.0')}}" />
      
      <!-- Dark Css -->
      <link rel="stylesheet" href="{{asset('assets/css/dark.min.css')}}" />
      
      <!-- Customizer Css -->
            @if(file_exists(base_path("assets/dashboard-themes/dashboard.css")))
      <link rel="stylesheet" href="{{asset('assets/dashboard-themes/dashboard.css')}}" />
      @else
      <link rel="stylesheet" href="{{asset('assets/css/customizer.min.css')}}" />
      @endif
      
      <!-- RTL Css -->
      <link rel="stylesheet" href="{{asset('assets/css/rtl.min.css')}}" />

<style>.container-text{position:relative;width:95%;max-width:900px;margin:0 auto;box-sizing:border-box}</style>
</head>
<body>

  <!-- Primary Page Layout
  –––––––––––––––––––––––––––––––––––––––––––––––––– -->
  <div class="container-text">
    <div class="row">

      <div class="column" style="margin-top: 10%">
        @if(file_exists(base_path("assets/wayvio/images/").findFile('avatar')))
        <img alt="avatar" src="{{ asset('assets/wayvio/images/'.findFile('avatar')) }}" width="auto" height="128px">
        @else
        <div class="logo-container fadein">
          <img src="{{ asset('assets/wayvio/images/logo.svg') }}" alt="Logo" style="width:150px; height:150px;">
        </div>
        @endif

        <div class="jumbotron" style="margin-top: 10%">
          <h1 class="display-4">{{env('TITLE_FOOTER_'.strtoupper($name))}}</h1>
          <hr class="my-4">
          <p>
            <?php echo $data['page']->$name; ?>
          </p>
          <p class="lead">
          </p>
        </div>

      <!-- Footer Section Start -->
      <footer class="footer mt-5">
        <div class="footer-body">
            <ul class="left-panel list-inline mb-0 p-0">
              @if($publicLegalLinks['agb'] !== '')<li class="list-inline-item"><a class="footer-hover spacing" href="{{ $publicLegalLinks['agb'] }}">{{ $footerLabels['agb'] }}</a></li>@endif
              @if($publicLegalLinks['avv'] !== '')<li class="list-inline-item"><a class="footer-hover spacing" href="{{ $publicLegalLinks['avv'] }}">{{ $footerLabels['avv'] }}</a></li>@endif
              @if($publicLegalLinks['privacy'] !== '')<li class="list-inline-item"><a class="footer-hover spacing" href="{{ $publicLegalLinks['privacy'] }}">{{ $footerLabels['privacy'] }}</a></li>@endif
              @if($publicLegalLinks['imprint'] !== '')<li class="list-inline-item"><a class="footer-hover spacing" href="{{ $publicLegalLinks['imprint'] }}">{{ $footerLabels['imprint'] }}</a></li>@endif
              @if($contactUrl !== '')<li class="list-inline-item"><a class="footer-hover spacing" href="{{ $contactUrl }}">{{ $footerLabels['contact'] }}</a></li>@endif
              @if($helpCenterUrl !== '')<li class="list-inline-item"><a class="footer-hover spacing" href="{{ $helpCenterUrl }}">{{ $footerLabels['help'] }}</a></li>@endif
              <li class="list-inline-item"><a class="footer-hover spacing" href="https://github.com/Luqqua/wayvio" target="_blank" rel="noreferrer">{{ $footerLabels['license'] }}</a></li>
            </ul>
            <div class="right-panel">
              &copy; @php echo date('Y'); @endphp {{ strtoupper((string) config('app.name')) }}
            </div>
        </div>
    </footer>
    <!-- Footer Section End -->

      </div>
    </div>
  </div>

<!-- End Document
  –––––––––––––––––––––––––––––––––––––––––––––––––– -->
</body>

    <!-- Library Bundle Script -->
    <script src="{{asset('assets/js/core/libs.min.js')}}"></script>
    
    <!-- External Library Bundle Script -->
    <script src="{{asset('assets/js/core/external.min.js')}}"></script>
    
    <!-- Widgetchart Script -->
    <script src="{{asset('assets/js/charts/widgetcharts.js')}}"></script>
    
    <!-- mapchart Script -->
    <script src="{{asset('assets/js/charts/vectore-chart.js')}}"></script>
    <script src="{{asset('assets/js/charts/dashboard.js')}}" ></script>
    
    <!-- fslightbox Script -->
    <script src="{{asset('assets/js/plugins/fslightbox.js')}}"></script>
    
    <!-- Settings Script -->
    <script src="{{asset('assets/js/plugins/setting.js')}}"></script>
    
    <!-- Slider-tab Script -->
    <script src="{{asset('assets/js/plugins/slider-tabs.js')}}"></script>
    
    <!-- Form Wizard Script -->
    <script src="{{asset('assets/js/plugins/form-wizard.js')}}"></script>
    
    <!-- AOS Animation Plugin-->
    <script src="{{asset('assets/vendor/aos/dist/aos.js')}}"></script>
    
    <!-- App Script -->
    <script src="{{asset('assets/js/hope-ui.js')}}" defer></script>
    
    <!-- Flatpickr Script -->
    <script src="{{asset('assets/vendor/flatpickr/dist/flatpickr.min.js')}}"></script>
    <script src="{{asset('assets/js/plugins/flatpickr.js')}}" defer></script>
    
    <script src="{{asset('assets/js/plugins/prism.mini.js')}}"></script>

<script src="{{ asset('assets/js/popper.js') }}"></script>
<script src="{{ asset('assets/js/bootstrap.min.js') }}"></script>
<script src="{{ asset('assets/js/Sortable.min.js') }}"></script>
<script src="{{ asset('assets/js/jquery-block-ui.js') }}"></script>
<script src="{{ asset('assets/js/main-dashboard.js') }}"></script>

</html>
