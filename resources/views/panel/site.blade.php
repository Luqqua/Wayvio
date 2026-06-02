@extends('layouts.sidebar')

@section('content')

<script src="{{ asset('resources/ckeditor/ckeditor.js') }}"></script>

<div class="conatiner-fluid content-inner mt-n5 py-0">
  <div class="row">   


      <div class="col-lg-12">
          <div class="card   rounded">
             <div class="card-body">
                <div class="row">
                    <div class="col-sm-12">  
  
                      <section class="text-gray-400">
                        <h2 class="mb-4 card-header"><i class="bi bi-person"> {{__('messages.Site Customization')}}</i></h2>
                                <div class="card-body p-0 p-md-3">
                          
                          <form action="{{ route('editSite') }}" method="post">
                          @csrf
                            <div class="form-group col-lg-8">
                              <div class="alert alert-light border" role="alert">
                                Site logo and favicon are managed outside the web UI.
                                Use <code>php artisan branding:set-site-asset logo /absolute/path/to/logo.png</code> or
                                <code>php artisan branding:set-site-asset favicon /absolute/path/to/favicon.webp</code>,
                                or place the files via SSH in <code>assets/wayvio/images/</code>.
                              </div>
                            </div>
                            <div class="form-group col-lg-8">
                              <h3>{{__('messages.Home message')}}</h3>
                              @php
                              if($home_message == "default") $home_message = __('messages.HOME.MESSAGE');
                              @endphp
                              <textarea class="form-control ckeditor" name="message" rows="3">{{ $home_message }}</textarea>
                            </div>
                            <button type="submit" class="mt-3 ml-3 btn btn-primary">{{__('messages.Save')}}</button>
                          </form>
                  
                            </div>
                  </section>
  
                    </div>
                </div>
             </div>
          </div>
       </div>


    </div>
  </div>

@endsection
