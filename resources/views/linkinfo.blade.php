


<!doctype html>
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
    @php $GLOBALS['themeName'] = config('advanced-config.home_theme'); @endphp
      <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
      @if(env('CUSTOM_META_TAGS') == 'true' and config('advanced-config.title') != '')
      <title>{{ config('advanced-config.title') }}</title>
      @else
      <title>{{ strtoupper((string) config('app.name')) }}</title>
      @endif
      
      <!-- Favicon -->
      @if(file_exists(base_path("assets/wayvio/images/").findFile('favicon')))
      <link rel="icon" type="image/png" href="{{ asset('assets/wayvio/images/'.findFile('favicon')) }}">
      @else
      <link rel="icon" type="image/svg+xml" href="{{ asset('assets/wayvio/images/logo.svg') }}">
      @endif
      
      <script src="{{asset('assets/js/detect-dark-mode.js')}}"></script>
      <link rel="stylesheet" href="{{ asset('assets/external-dependencies/bootstrap-icons.css') }}">

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
  </head>

<body>
        <!--Nav Start-->
        <nav class="nav navbar navbar-expand-lg navbar-light iq-navbar">
            <div class="container-fluid navbar-inner">
              <a href="{{ route('panelIndex') }}" class="navbar-brand">
                  
                  <!--Logo start-->
                  <div class="logo-main">
                    @if(file_exists(base_path("assets/wayvio/images/").findFile('avatar')))
                    <div class="logo-normal">
                      <img class="img logo" src="{{ asset('assets/wayvio/images/'.findFile('avatar')) }}" style="width:auto;height:30px;">
                  </div>
                  <div class="logo-mini">
                    <img class="img logo" src="{{ asset('assets/wayvio/images/'.findFile('avatar')) }}" style="width:auto;height:30px;">
                  </div>
                    @else
                    <div class="logo-normal">
                      <img class="img logo" type="image/svg+xml" src="{{ asset('assets/wayvio/images/logo.svg') }}" width="30px" height="30px">
                  </div>
                  <div class="logo-mini">
                    <img class="img logo" type="image/svg+xml" src="{{ asset('assets/wayvio/images/logo.svg') }}" width="30px" height="30px">
                  </div>
                    @endif
                    </div>
                  <!--logo End-->
                  
                  <h4 class="logo-title">{{ strtoupper((string) config('app.name')) }}</h4>
              </a>
            </div>
          </nav>
          <!--Nav End-->

          <div class="container mt-4">
            <div style="height: 89vh;" class="row align-items-center justify-content-center">
                <div class="col-md-8">
                    <div>
                        <h1>{{url('/going'.'/'.$linkID)}}</h1>
                        <p><i class="bi bi-arrow-return-right"></i> <a href="{{$link}}">{{$link}} <i style="font-size:80%" class="bi bi-box-arrow-up-right"></i></a></p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="p-2 mb-3">
                                @if(userAvatarExists($id))
                                    <img alt="avatar" class="rounded-avatar fadein" src="{{ userAvatarUrl($id) }}" height="128px" width="128px" style="object-fit: cover;">
                                @elseif(file_exists(base_path("assets/wayvio/images/").findFile('avatar')))
                                    <img alt="avatar" class="fadein" src="{{ url("assets/wayvio/images/")."/".findFile('avatar') }}" height="128px" width="128px" style="object-fit: cover;">
                                @else
                                    <img alt="avatar" class="fadein" src="{{ asset('assets/wayvio/images/logo.svg') }}" height="128px" style="width:auto;min-width:128px;object-fit: cover;">
                                @endif
                            </div>
                            <h5 class="card-title">{{$userData->name}}</h5>
                            <p class="card-text"><a href="{{url("/".$userData->littlelink_name)}}">{{url("/".$userData->littlelink_name)}} <i style="font-size:80%" class="bi bi-box-arrow-up-right"></i></a></p>
                            <p class="card-text mt-2">{{ $userData->littlelink_description }}</p>
                        </div>
                    </div>
                </div>
    
                @if(auth()->check() && auth()->user()->role == "admin")
                <hr class="my-4 border-top border-2 border-gray">
    
                <div class="tab-pane bd-heading-1 fade show active" id="content-Buttongroup-code" role="tabpanel">
                    <div class="section-block">
                    <pre class=" language-markup" tabindex="0">
                        <code class=" language-markup">
                            {{__('messages.ID')}}: {{$id}}
                            {{__('messages.Name')}}: {{$userData->name}}
                            {{__('messages.Handle:')}} {{$userData->littlelink_name}}
                            {{__('messages.Email')}}: {{$userData->email}}
                            {{__('messages.Role')}}: {{$userData->role}}
                            {{__('messages.Created at')}}: {{$userData->created_at}}
                            {{__('messages.Last seen')}}: {{$userData->updated_at}}</code>
                    </pre>
                    </div>                        
                </div>
    
                <div class="d-flex flex-column flex-md-row align-items-md-center">
                    <form method="POST" action="{{ route('deleteLink', $linkID ) }}" class="me-md-3 mb-3 mb-md-0" onsubmit="return confirm('{{__('messages.confirm.delete.user')}}')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-primary">{{__('messages.Delete')}} {{strtolower(__('messages.Link'))}}</button>
                    </form>
                    <form method="POST" action="{{ route('deleteUser', ['id' => $id]) }}" class="me-md-3 mb-3 mb-md-0" onsubmit="return confirm('{{__('messages.confirm.delete.user')}}')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">{{__('messages.Delete')}} {{strtolower(__('messages.User'))}}</button>
                    </form>
                  </div>
                @endif


    <!-- Footer -->
    <footer class="text-center mt-5">
        <ul class="mb-0 p-0 footer-content">
          @if($publicLegalLinks['agb'] !== '')<li class="list-inline-item"><a class="list-inline-item" href="{{ $publicLegalLinks['agb'] }}">{{ $footerLabels['agb'] }}</a></li>@endif
          @if($publicLegalLinks['avv'] !== '')<li class="list-inline-item"><a class="list-inline-item" href="{{ $publicLegalLinks['avv'] }}">{{ $footerLabels['avv'] }}</a></li>@endif
          @if($publicLegalLinks['privacy'] !== '')<li class="list-inline-item"><a class="list-inline-item" href="{{ $publicLegalLinks['privacy'] }}">{{ $footerLabels['privacy'] }}</a></li>@endif
          @if($publicLegalLinks['imprint'] !== '')<li class="list-inline-item"><a class="list-inline-item" href="{{ $publicLegalLinks['imprint'] }}">{{ $footerLabels['imprint'] }}</a></li>@endif
          @if($contactUrl !== '')<li class="list-inline-item"><a class="list-inline-item" href="{{ $contactUrl }}">{{ $footerLabels['contact'] }}</a></li>@endif
          @if($helpCenterUrl !== '')<li class="list-inline-item"><a class="list-inline-item" href="{{ $helpCenterUrl }}">{{ $footerLabels['help'] }}</a></li>@endif
          <li class="list-inline-item"><a class="list-inline-item" href="https://github.com/Luqqua/wayvio" target="_blank" rel="noreferrer">{{ $footerLabels['license'] }}</a></li>
        </ul>
        <div class="right-panel">
          &copy; @php echo date('Y'); @endphp {{ strtoupper((string) config('app.name')) }}
        </div>
</footer>


            </div>
        </div>


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
    @include('layouts.autofill-strict-off')
    
  </body>
</html>
