@extends('layouts.sidebar')

@section('content')

<div class="conatiner-fluid content-inner mt-n5 pt-0 pb-4 ls-consistent-spacing">
  <div class="row">   
      
   <div class="col-lg-12">
      <div class="card   rounded">
          <div class="card-body">
             <div class="row">
                 <div class="col-sm-12">  

                  @if(session()->has('success'))
                  <div class="alert alert-success">
                      {{ session()->get('success') }}
                  </div>
              @endif
              
              @if(session()->has('error'))
                  <div class="alert alert-danger">
                      {{ session()->get('error') }}
                  </div>
              @endif
              
              @if(session('status'))
                  <div class="alert alert-info">
                      {{ session('status') }}
                  </div>
              @endif
              
              @if ($errors->any())
                  <div class="alert alert-danger">
                      <ul class="mb-0">
                          @foreach ($errors->all() as $error)
                              <li>{{ $error }}</li>
                          @endforeach
                      </ul>
                  </div>
              @endif
              
              @if($_SERVER['QUERY_STRING'] === '')
              <section class="text-gray-400">
                      <h3 class="mb-4 card-header"><i class="bi bi-person">{{__('messages.Account Settings')}}</i></h3>
              <div class="card-body p-0 p-md-3">
              
                      @foreach($profile as $profile)

              @php
                $status = strtolower($tierInfo['status'] ?? 'free');
                $badgeClass = match ($status) {
                  'active' => 'bg-success',
                  'in grace period' => 'bg-warning text-dark',
                  'expired' => 'bg-danger',
                  default => 'bg-secondary'
                };
                $expiresAt = $tierInfo['expires_at'] ?? null;
                $expiresFormatted = $expiresAt
                  ? \Illuminate\Support\Carbon::parse($expiresAt)->toFormattedDateString()
                  : __('messages.No expiry (free or lifetime)');
                $statusLabel = match ($status) {
                  'active' => __('messages.Active'),
                  'in grace period' => __('messages.In grace period'),
                  'expired' => __('messages.Expired'),
                  default => __('messages.Free'),
                };
              @endphp

              {{-- Current Plan oben --}}
              <div class="card border mb-4">
                <div class="card-body d-flex flex-wrap align-items-center justify-content-between">
                  <div class="me-3">
                    <p class="text-uppercase text-muted small mb-1">{{ __('messages.Current Plan') }}</p>
                    <h5 class="mb-1">{{ strtoupper($tierInfo['name'] ?? 'Free') }}</h5>
                    <p class="mb-0 text-muted">{{ __('messages.Expires') }}: {{ $expiresFormatted }}</p>
                  </div>
                  <span class="badge {{ $badgeClass }} px-3 py-2 text-uppercase">{{ $statusLabel }}</span>
                </div>
              </div>

              {{-- Farbschema (Light/Dark/Auto) --}}
              <div class="card border mb-4">
                <div class="card-body">
                  <h4 class="mb-3">{{ __('messages.Scheme') }}</h4>
                  <div class="d-flex flex-wrap gap-3">
                    <button type="button" class="btn btn-border" data-setting="color-mode" data-name="color" data-value="auto">
                      {{__('messages.Auto')}}
                    </button>
                    <button type="button" class="btn btn-border" data-setting="color-mode" data-name="color" data-value="dark">
                      {{__('messages.Dark')}}
                    </button>
                    <button type="button" class="btn btn-border" data-setting="color-mode" data-name="color" data-value="light">
                      {{__('messages.Light')}}
                    </button>
                  </div>
                  <script>
                    (function() {
                      const buttons = document.querySelectorAll('[data-setting="color-mode"][data-name="color"]');
                      const stored = localStorage.getItem('color-mode') || 'light';
                      buttons.forEach(btn => {
                        const isActive = btn.getAttribute('data-value') === stored;
                        btn.classList.toggle('active', isActive);
                        btn.classList.toggle('btn-primary', isActive);
                      });
                    })();
                  </script>
                </div>
              </div>

              @php
                $selectedLocale = old('locale', $userLocale ?? '');
                $localeLabels = [
                  'en' => __('messages.English'),
                  'de' => __('messages.German'),
                ];
              @endphp

              <div class="card border mb-4">
                <div class="card-body">
                  <h4 class="mb-3">{{ __('messages.Language preference') }}</h4>
                  <p class="text-muted">{{ __('messages.Language preference description') }}</p>
                  <form action="{{ route('profile.locale') }}" method="post">
                    @csrf
                    <div class="form-group col-lg-8 mb-3">
                      <label class="form-label" for="locale">{{ __('messages.Language') }}</label>
                      <select class="form-control" name="locale" id="locale">
                        <option value="">{{ __('messages.Automatic (browser)') }}</option>
                        @foreach($supportedLocales as $locale)
                          @php $label = $localeLabels[$locale] ?? strtoupper($locale); @endphp
                          <option value="{{ $locale }}" @if($selectedLocale === $locale) selected @endif>{{ $label }}</option>
                        @endforeach
                      </select>
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('messages.Save') }}</button>
                  </form>
                </div>
              </div>

              <div class="card border mb-4">
                <div class="card-body">
                  <h4 class="mb-3">{{__('messages.Email')}}</h4>
                  <p class="text-muted">{{ __('messages.Email change description') }}</p>
                  @if(!empty($pendingEmail))
                    <div class="alert alert-info mb-3">
                      {{__('messages.Pending email change to')}} {{ $pendingEmail }}. {{__('messages.Check your inbox to confirm the change')}}. {{ __('messages.Email pending description') }}
                    </div>
                  @endif
                  <form  action="{{ route('profile.email') }}" method="post">
                    @csrf
                      <div class="form-group col-lg-8 mb-3">
                        <label class="form-label" for="current_email">{{ __('messages.Current email address') }}</label>
                        <input type="email" class="form-control" id="current_email" value="{{ $profile->email }}" readonly disabled>
                      </div>
                      <div class="form-group col-lg-8 mb-3">
                        <label class="form-label" for="new_email">{{ __('messages.New email address') }}</label>
                        <input type="email" class="form-control" id="new_email" name="new_email" value="{{ old('new_email', $pendingEmail) }}" required>
                        <small class="text-muted">{{ __('messages.Check your inbox to confirm the change') }}</small>
                      </div>
                      <div class="form-group col-lg-8 mb-3">
                        <label class="form-label" for="current_email_password">{{__('messages.Current Password')}}</label>
                        <input type="password" class="form-control" id="current_email_password" name="current_password" required autocomplete="current-password">
                      </div>
                      <button type="submit" class="btn btn-primary">{{ __('messages.Send verification link') }}</button>
                    </form>
                </div>
              </div>
              
              <div class="card border mb-4">
                <div class="card-body">
                  <h4 class="mb-3">{{__('messages.Password')}}</h4>
                  <form  action="{{ route('profile.password') }}" method="post">
                    @csrf
                      <div class="form-group col-lg-8 mb-3">
                        <label class="form-label" for="current_password">{{__('messages.Current Password')}}</label>
                        <input type="password" name="current_password" id="current_password" class="form-control" required autocomplete="current-password">
                      </div>
                      <div class="form-group col-lg-8 mb-3">
                        <label class="form-label" for="new_password">{{__('messages.New Password')}}</label>
                        <input type="password" name="password" id="new_password" class="form-control" placeholder="{{__('messages.At least 10 characters')}}" required autocomplete="new-password">
                      </div>
                      <div class="form-group col-lg-8 mb-3">
                        <label class="form-label" for="password_confirmation">{{__('messages.Confirm Password')}}</label>
                        <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" required autocomplete="new-password">
                      </div>
                      <p class="small text-muted mb-3">{{__('messages.Password must be at least 10 characters with uppercase and a number')}}</p>
                      <button type="submit" class="btn btn-primary">{{__('messages.Change password')}}</button>
                    </form>
                </div>
              </div>

              <div class="card border mb-4">
                <div class="card-body">
                  <h4 class="mb-3">{{__('messages.Two-factor authentication')}}</h4>
                  <p class="mb-3">
                    @if($twoFactorEnabled)
                      <span class="badge bg-success">{{__('messages.Enabled')}}</span>
                    @else
                      <span class="badge bg-secondary">{{__('messages.Disabled')}}</span>
                    @endif
                  </p>
                  @if(!$twoFactorEnabled)
                    <form action="{{ route('two-factor.enable') }}" method="post" class="mb-3">
                      @csrf
                      <button type="submit" class="btn btn-primary">{{__('messages.Enable two-factor authentication')}}</button>
                    </form>
                  @endif
                  
                  @if(!empty($twoFactorSecret) || !empty($twoFactorRecoveryCodes))
                    @if(!empty($twoFactorSecret))
                      <div class="mb-3">
                        <p class="mb-2">{{__('messages.Scan this QR code or use the setup key below')}}:</p>
                        <div class="d-flex flex-wrap align-items-center gap-3">
                          @if(!empty($twoFactorQr))
                            <div>{!! $twoFactorQr !!}</div>
                          @endif
                          <div>
                            <p class="mb-1 fw-bold">{{__('messages.Setup Key')}}:</p>
                            <code>{{ $twoFactorSecret }}</code>
                          </div>
                        </div>
                      </div>
                    @endif
                    @if(!empty($twoFactorRecoveryCodes))
                      <div class="mb-3">
                        <p class="mb-1">{{__('messages.Recovery Codes')}}</p>
                        <p class="small text-muted">{{__('messages.Store these codes somewhere safe. Each code can be used once.')}}</p>
                        <div class="bg-light border rounded p-3">
                          @foreach($twoFactorRecoveryCodes as $code)
                            <div>{{ $code }}</div>
                          @endforeach
                        </div>
                      </div>
                    @endif
                    @if(!$twoFactorEnabled && !empty($twoFactorSecret))
                      <form action="{{ route('two-factor.confirm') }}" method="post" class="mb-3">
                        @csrf
                        <div class="form-group col-lg-8 mb-3">
                          <label for="two_factor_code" class="form-label">{{__('messages.Authentication code')}}</label>
                          <input type="text" class="form-control" id="two_factor_code" name="code" inputmode="numeric" pattern="[0-9]*" required>
                        </div>
                        <button type="submit" class="btn btn-success">{{__('messages.Confirm and enable')}}</button>
                      </form>
                    @endif
                  @endif

                  @if($twoFactorEnabled)
                    <form action="{{ route('two-factor.recovery') }}" method="post" class="mb-3">
                      @csrf
                      <div class="form-group col-lg-8 mb-2">
                        <label class="form-label" for="regenerate_password">{{__('messages.Current Password')}}</label>
                        <input type="password" name="current_password" id="regenerate_password" class="form-control" required autocomplete="current-password">
                      </div>
                      <button type="submit" class="btn btn-outline-primary">{{__('messages.Generate new recovery codes')}}</button>
                    </form>
                    <form action="{{ route('two-factor.disable') }}" method="post">
                      @csrf
                      <div class="form-group col-lg-8 mb-2">
                        <label class="form-label" for="disable_password">{{__('messages.Current Password')}}</label>
                        <input type="password" name="current_password" id="disable_password" class="form-control" required autocomplete="current-password">
                      </div>
                      <button type="submit" class="btn btn-outline-danger">{{__('messages.Disable two-factor authentication')}}</button>
                    </form>
                  @endif
                </div>
              </div>
              <div class="card border border-danger mb-4">
                <div class="card-body">
                  @php
                    $deletePopupLines = [
                      __('messages.Delete account popup title'),
                      __('messages.Delete account popup subscription warning'),
                      __('messages.Delete account popup refund warning'),
                      __('messages.Delete account popup access warning'),
                      __('messages.Delete account popup data warning'),
                    ];

                    $deletePopupMessage = implode("\n", $deletePopupLines);
                  @endphp
                  <h4 class="mb-3 text-danger">{{ __('messages.Delete your account') }}</h4>
                  <p class="text-muted">{{ __('messages.Delete account description') }}</p>
                  <div class="alert alert-warning mb-3">
                    <div><strong>{{ __('messages.Delete account popup subscription warning') }}</strong></div>
                    <div>{{ __('messages.Delete account popup refund warning') }}</div>
                    <div>{{ __('messages.Delete account popup access warning') }}</div>
                    <div>{{ __('messages.Delete account popup data warning') }}</div>
                  </div>
                  @if(env('ALLOW_USER_EXPORT') != false)
                    <p class="small mb-3">
                      <a href="{{ route('exportAll') }}">{{ __('messages.Export all data') }}</a>
                      {{ __('messages.Delete account export note') }}
                    </p>
                  @endif
                  @if(
                    $errors->has('delete_account')
                    || $errors->has('delete_confirmation')
                    || $errors->has('delete_2fa_code')
                  )
                    <div class="alert alert-danger mb-3">
                      @error('delete_account')<div>{{ $message }}</div>@enderror
                      @error('delete_confirmation')<div>{{ $message }}</div>@enderror
                      @error('delete_2fa_code')<div>{{ $message }}</div>@enderror
                    </div>
                  @endif
                  <form method="POST" id="delete-account-form" action="{{ route('deleteUser', ['id' => Auth::id()]) }}">
                    @csrf
                    @method('DELETE')
                    <div class="form-group col-lg-8 mb-3">
                      <label class="form-label" for="delete_confirmation">{{ __('messages.Type DELETE to confirm') }}</label>
                      <input type="text" class="form-control" id="delete_confirmation" name="delete_confirmation" value="{{ old('delete_confirmation') }}" required autocomplete="off" autocapitalize="characters" spellcheck="false">
                    </div>
                    <div class="form-group col-lg-8 mb-3">
                      <label class="form-label" for="delete_current_password">{{ __('messages.Current Password') }}</label>
                      <input type="password" class="form-control" id="delete_current_password" name="current_password" required autocomplete="current-password">
                    </div>
                    @if($twoFactorEnabled)
                      <div class="form-group col-lg-8 mb-3">
                        <label class="form-label" for="delete_2fa_code">{{ __('messages.Authentication code') }}</label>
                        <input type="text" class="form-control" id="delete_2fa_code" name="delete_2fa_code" inputmode="text" autocomplete="one-time-code" placeholder="123456 or recovery code" required>
                        <small class="text-muted">{{ __('messages.Delete account two-factor description') }}</small>
                      </div>
                    @endif
                    <button type="submit" class="btn btn-danger">{{ __('messages.Delete account') }}</button>
                  </form>
                  <script>
                    document.addEventListener('DOMContentLoaded', function () {
                      const deleteForm = document.getElementById('delete-account-form');
                      if (!deleteForm) {
                        return;
                      }

                      const popupMessage = @json($deletePopupMessage);
                      deleteForm.addEventListener('submit', function (event) {
                        if (!window.confirm(popupMessage)) {
                          event.preventDefault();
                        }
                      });
                    });
                  </script>
                </div>
              </div>
                        </div>
              </section>
                        @endforeach
              @endif

                 </div>
             </div>
          </div>
       </div>
      </div>
    </div>
  </div>

@endsection
