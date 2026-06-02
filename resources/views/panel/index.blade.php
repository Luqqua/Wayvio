@extends('layouts.sidebar')


@section('content')
<div class="conatiner-fluid content-inner mt-n5 py-0 ls-consistent-spacing">
    <div class="row g-4">   
        
     <div class="col-lg-12">
        <div class="card   rounded">
            <div class="card-body">
               <div class="row">
                   <div class="col-sm-12">  

                    <h3 class="mb-4"><i class="bi bi-menu-up"></i> {{__('messages.Dashboard')}}</h3>
                    <section class="mb-3 text-center p-4 w-full">
                        @if($isAgencyTier)
                        <div class="row row-cols-2 row-cols-md-3 g-3 justify-content-center">
                            <div class="col">
                                <a class="btn btn-primary w-100 h-100 d-flex flex-column align-items-center justify-content-center gap-2 text-white" href="{{ route('agency.hubs.index') }}">
                                    <span class="d-block">
                                        <svg class="icon-30" width="30" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M7 8C7 6.34315 8.34315 5 10 5H14C15.6569 5 17 6.34315 17 8V9H7V8Z" stroke="currentColor" stroke-width="1.5"/>
                                            <path d="M5 11.5C5 10.6716 5.67157 10 6.5 10H17.5C18.3284 10 19 10.6716 19 11.5V16C19 18.2091 17.2091 20 15 20H9C6.79086 20 5 18.2091 5 16V11.5Z" stroke="currentColor" stroke-width="1.5"/>
                                            <circle cx="9" cy="14.5" r="1" fill="currentColor"/>
                                            <circle cx="15" cy="14.5" r="1" fill="currentColor"/>
                                        </svg>
                                    </span>
                                    <span class="fw-semibold">Hubs</span>
                                </a>
                            </div>
                            <div class="col">
                                <a class="btn btn-primary w-100 h-100 d-flex flex-column align-items-center justify-content-center gap-2 text-white" href="{{ route('domains.page') }}">
                                    <span class="d-block">
                                        <svg class="icon-30" width="30" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M4 5H20V19H4V5Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M8 9H12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                            <path d="M5 13H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                            <path d="M8 17H12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                        </svg>
                                    </span>
                                    <span class="fw-semibold">Agency Settings</span>
                                </a>
                            </div>
                            <div class="col">
                                <a class="btn btn-primary w-100 h-100 d-flex flex-column align-items-center justify-content-center gap-2 text-white" href="{{ url('/dashboard/subscription') }}">
                                    <span class="d-block">
                                        <svg class="icon-30" width="30" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M12 2.25C6.615 2.25 2.25 6.615 2.25 12C2.25 17.385 6.615 21.75 12 21.75C17.385 21.75 21.75 17.385 21.75 12C21.75 6.615 17.385 2.25 12 2.25ZM12 19.875C7.542 19.875 4.125 16.458 4.125 12C4.125 7.542 7.542 4.125 12 4.125C16.458 4.125 19.875 7.542 19.875 12C19.875 16.458 16.458 19.875 12 19.875Z" fill="currentColor"/>
                                            <path d="M12.75 6.75H11.25V12.75L16.125 15.675L16.875 14.445L12.75 12.15V6.75Z" fill="currentColor"/>
                                        </svg>
                                    </span>
                                    <span class="fw-semibold">{{ __('Subscription') }}</span>
                                </a>
                            </div>
                        </div>
                        @else
                        <div class="row row-cols-2 row-cols-md-4 g-3">
                            <div class="col">
                                <a class="btn btn-primary w-100 h-100 d-flex flex-column align-items-center justify-content-center gap-2 text-white" href="{{ url('/studio/add-link') }}">
                                    <span class="d-block">
                                        <svg class="icon-30" width="30" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M7.33 2H16.66C20.06 2 22 3.92 22 7.33V16.67C22 20.06 20.07 22 16.67 22H7.33C3.92 22 2 20.06 2 16.67V7.33C2 3.92 3.92 2 7.33 2ZM12.82 12.83H15.66C16.12 12.82 16.49 12.45 16.49 11.99C16.49 11.53 16.12 11.16 15.66 11.16H12.82V8.34C12.82 7.88 12.45 7.51 11.99 7.51C11.53 7.51 11.16 7.88 11.16 8.34V11.16H8.33C8.11 11.16 7.9 11.25 7.74 11.4C7.59 11.56 7.5 11.769 7.5 11.99C7.5 12.45 7.87 12.82 8.33 12.83H11.16V15.66C11.16 16.12 11.53 16.49 11.99 16.49C12.45 16.49 12.82 16.12 12.82 15.66V12.83Z" fill="currentColor"></path>
                                            <circle cx="18" cy="11.8999" r="1" fill="currentColor"></circle>
                                        </svg>
                                    </span>
                                    <span class="fw-semibold">{{ __('messages.Add Link') }}</span>
                                </a>
                            </div>
                            <div class="col">
                                <a class="btn btn-primary w-100 h-100 d-flex flex-column align-items-center justify-content-center gap-2 text-white" href="{{ url('/studio/theme') }}">
                                    <span class="d-block">
                                        <svg class="icon-30" width="30" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M7.63751 3.39549C5.06051 3.39549 3.39551 5.16249 3.39551 7.88849V16.1025C3.39551 16.8675 3.53751 17.5505 3.78051 18.1415C3.791 18.129 4.01986 17.8501 4.3184 17.4863C4.90188 16.7752 5.75156 15.7398 5.75751 15.7345C6.44951 14.9445 7.74851 13.7665 9.45351 14.4795C9.82712 14.6344 10.1592 14.8466 10.4649 15.042C10.4947 15.061 10.5242 15.0799 10.5535 15.0985C11.1265 15.4815 11.4635 15.6615 11.8135 15.6315C11.9585 15.6115 12.0945 15.5685 12.2235 15.4885C12.7101 15.1885 13.9718 13.4009 14.3496 12.8656C14.405 12.7871 14.4414 12.7355 14.4535 12.7195C15.5435 11.2995 17.2235 10.9195 18.6235 11.7595C18.8115 11.8715 20.1585 12.8125 20.6045 13.1905V7.88849C20.6045 5.16249 18.9395 3.39549 16.3535 3.39549H7.63751ZM16.3535 2.00049C19.7305 2.00049 21.9995 4.36249 21.9995 7.88849V16.1025C21.9995 16.1912 21.9902 16.2743 21.9809 16.3574C21.9744 16.4159 21.9678 16.4742 21.9645 16.5345C21.9624 16.5709 21.9613 16.6073 21.9603 16.6438C21.9589 16.6923 21.9575 16.7409 21.9535 16.7895C21.9515 16.8085 21.9478 16.8267 21.944 16.845C21.9403 16.8632 21.9365 16.8815 21.9345 16.9005C21.9015 17.2145 21.8505 17.5145 21.7795 17.8055C21.7627 17.8782 21.7433 17.9483 21.7238 18.0191L21.7195 18.0345C21.6395 18.3165 21.5455 18.5855 21.4325 18.8425C21.4127 18.8857 21.3918 18.9278 21.3709 18.9699C21.357 18.998 21.3431 19.0261 21.3295 19.0545C21.2075 19.2995 21.0755 19.5345 20.9225 19.7525C20.8942 19.7928 20.8641 19.8307 20.8339 19.8685C20.814 19.8936 20.794 19.9186 20.7745 19.9445C20.6155 20.1505 20.4495 20.3475 20.2615 20.5265C20.224 20.5622 20.1834 20.5948 20.1428 20.6275C20.1175 20.6479 20.0921 20.6683 20.0675 20.6895C19.8745 20.8555 19.6775 21.0145 19.4605 21.1505C19.4132 21.1802 19.3628 21.2052 19.3127 21.2301C19.2803 21.2462 19.2479 21.2622 19.2165 21.2795C18.9955 21.4015 18.7725 21.5205 18.5295 21.6125C18.4711 21.6347 18.4088 21.6508 18.3465 21.6669C18.3021 21.6783 18.2577 21.6898 18.2145 21.7035C18.1929 21.7102 18.1713 21.7169 18.1497 21.7236C17.9326 21.7912 17.7162 21.8585 17.4825 21.8985C17.3471 21.9222 17.2034 21.9313 17.0596 21.9405C16.9974 21.9444 16.9351 21.9484 16.8735 21.9535C16.8073 21.9584 16.7423 21.9664 16.6773 21.9744C16.5716 21.9874 16.4656 22.0005 16.3535 22.0005H7.63751C7.26151 22.0005 6.90251 21.9625 6.55551 21.9055C6.54251 21.9035 6.53051 21.9015 6.51851 21.8995C5.16551 21.6665 4.04251 21.0135 3.25551 20.0285C3.25005 20.0285 3.2479 20.0248 3.24504 20.0199C3.24319 20.0167 3.24105 20.013 3.23751 20.0095C2.44651 19.0135 1.99951 17.6745 1.99951 16.1025V7.88849C1.99951 4.36249 4.27051 2.00049 7.63751 2.00049H16.3535ZM11.0001 8.51505C11.0001 9.87 9.86639 11.0001 8.50496 11.0001C7.30825 11.0001 6.2879 10.1257 6.05922 8.99372C6.02143 8.82387 6.00011 8.64919 6.00011 8.46872C6.00011 7.10412 7.10864 6.00009 8.47879 6.00009C9.17647 6.00009 9.80825 6.29347 10.2608 6.76152C10.7152 7.21317 11.0001 7.83564 11.0001 8.51505Z" fill="currentColor"></path>
                                        </svg>
                                    </span>
                                    <span class="fw-semibold">{{ __('messages.Themes') }}</span>
                                </a>
                            </div>
                            <div class="col">
                                <a class="btn btn-primary w-100 h-100 d-flex flex-column align-items-center justify-content-center gap-2 text-white" href="{{ url('/dashboard/analytics') }}">
                                    <span class="d-block">
                                        <svg class="icon-30" width="30" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M5 3C3.89543 3 3.98422e-08 3.89543 0 5V19C0 20.1046 0.895431 21 2 21H22C23.1046 21 24 20.1046 24 19V5C24 3.89543 23.1046 3 22 3H5Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M7 14.5L10 11L13 14L17 9.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                            <circle cx="7" cy="14.5" r="1" fill="currentColor"/>
                                            <circle cx="10" cy="11" r="1" fill="currentColor"/>
                                            <circle cx="13" cy="14" r="1" fill="currentColor"/>
                                            <circle cx="17" cy="9.5" r="1" fill="currentColor"/>
                                        </svg>
                                    </span>
                                    <span class="fw-semibold">{{ __('Analytics') }}</span>
                                </a>
                            </div>
                            <div class="col">
                                <a class="btn btn-primary w-100 h-100 d-flex flex-column align-items-center justify-content-center gap-2 text-white" href="{{ url('/dashboard/subscription') }}">
                                    <span class="d-block">
                                        <svg class="icon-30" width="30" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M12 2.25C6.615 2.25 2.25 6.615 2.25 12C2.25 17.385 6.615 21.75 12 21.75C17.385 21.75 21.75 17.385 21.75 12C21.75 6.615 17.385 2.25 12 2.25ZM12 19.875C7.542 19.875 4.125 16.458 4.125 12C4.125 7.542 7.542 4.125 12 4.125C16.458 4.125 19.875 7.542 19.875 12C19.875 16.458 16.458 19.875 12 19.875Z" fill="currentColor"/>
                                            <path d="M12.75 6.75H11.25V12.75L16.125 15.675L16.875 14.445L12.75 12.15V6.75Z" fill="currentColor"/>
                                        </svg>
                                    </span>
                                    <span class="fw-semibold">{{ __('Subscription') }}</span>
                                </a>
                            </div>
                        </div>
                        @endif
                    </section>

                    @if(!empty($agencySummary))
                    <section class="mb-3 text-gray-800 text-center p-4 w-full">
                        <div class='font-weight-bold text-left h3'>Agency Overview</div><br>
                        <div class="d-flex flex-wrap justify-content-around">
                            <div class="p-2">
                                <h3 class="text-primary"><strong>{{ $agencySummary['hub_count'] }}</strong></h3>
                                <span class="text-muted">Managed hubs</span>
                            </div>
                            <div class="p-2">
                                <h3 class="text-primary"><strong>{{ $agencySummary['slots_used'] }}/{{ $agencySummary['slots_total'] }}</strong></h3>
                                <span class="text-muted">Slots used</span>
                            </div>
                            <div class="p-2">
                                <h3 class="text-primary"><strong>{{ $agencySummary['links'] }}</strong></h3>
                                <span class="text-muted">Total links (all hubs)</span>
                            </div>
                        </div>
                    </section>
                    @endif

                   </div>
               </div>
            </div>
         </div>
        </div>

       @if(auth()->user()->role == 'admin' && !config('linkstack.single_user_mode'))
       <div class="col-lg-12">
          <div class="card   rounded">
             <div class="card-body">
                <div class="row">
                    <div class="col-sm-12">  

                    {{-- MODULE: AnalyticsPremium include, does NOT belong to Core --}}
                    {{-- Moved to dedicated page /dashboard/analytics --}}

                    {{-- MODULE: CustomDomains include, does NOT belong to Core --}}
                    {{-- Moved to dedicated page /dashboard/custom-domains --}}
        <!-- Section: Design Block -->
        <section class="mb-3 text-gray-800 text-center p-4 w-full">
            <div class='font-weight-bold text-left h3'>{{__('messages.Site statistics:')}}</div><br>
            <div class="d-flex flex-wrap justify-content-around">

                <div class="p-2">
                    <h3 class="text-primary"><strong><i class="bi bi-share-fill"> {{ $siteLinks }} </i></strong></h3>
                    <span class="text-muted">{{__('messages.Total links')}}</span>
                </div>

                <div class="p-2">
                    <h3 class="text-primary"><strong><i class="bi bi bi-person-fill"> {{ $userNumber }}</i></strong></h3>
                    <span class="text-muted">{{__('messages.Total users')}}</span>
                </div>

            </div>
        </section>

                    </div>
                </div>
             </div>
          </div>
       </div>       
       
       <div class="col-lg-12">
        <div class="card   rounded">
           <div class="card-body">
              <div class="row">
                  <div class="col-sm-12">  

      <!-- Section: Design Block -->
      <section class="mb-3 text-gray-800 text-center p-4 w-full">
          <div class='font-weight-bold text-left h3'>{{__('messages.Registrations:')}}</div><br>
          <div class="d-flex flex-wrap justify-content-around">

              <div class="p-2">
                  <h3 class="text-primary"><strong> {{ $lastMonthCount }} </i></strong></h3>
                  <span class="text-muted">{{__('messages.Last 30 days')}}</span>
              </div>

              <div class="p-2">
                  <h3 class="text-primary"><strong> {{ $lastWeekCount }} </i></strong></h3>
                  <span class="text-muted">{{__('messages.Last 7 days')}}</span>
              </div>

              <div class="p-2">
                  <h3 class="text-primary"><strong> {{ $last24HrsCount }}</i></strong></h3>
                  <span class="text-muted">{{__('messages.Last 24 hours')}}</span>
              </div>

          </div>
      </section>

                  </div>
              </div>
           </div>
        </div>
     </div>   

     <div class="col-lg-12">
        <div class="card   rounded">
           <div class="card-body">
              <div class="row">
                  <div class="col-sm-12">  


      <!-- Section: Design Block -->
      <section class="mb-3 text-gray-800 text-center p-4 w-full">
          <div class='font-weight-bold text-left h3'>{{__('messages.Active users:')}}</div><br>
          <div class="d-flex flex-wrap justify-content-around">

              <div class="p-2">
                  <h3 class="text-primary"><strong> {{ $updatedLast30DaysCount }} </i></strong></h3>
                  <span class="text-muted">{{__('messages.Last 30 days')}}</span>
              </div>

              <div class="p-2">
                  <h3 class="text-primary"><strong> {{ $updatedLast7DaysCount }} </i></strong></h3>
                  <span class="text-muted">{{__('messages.Last 7 days')}}</span>
              </div>

              <div class="p-2">
                  <h3 class="text-primary"><strong> {{ $updatedLast24HrsCount }}</i></strong></h3>
                  <span class="text-muted">{{__('messages.Last 24 hours')}}</span>
              </div>

          </div>
      </section>
                  </div>
              </div>
           </div>
        </div>
     </div>   
     @endif

    </div>
    </div>
@endsection
