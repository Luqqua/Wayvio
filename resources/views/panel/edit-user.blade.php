@extends('layouts.sidebar')

@section('content')

<div class="conatiner-fluid content-inner mt-n5 py-0">
  <div class="row">   


      <div class="col-lg-12">
          <div class="card   rounded">
             <div class="card-body">
                <div class="row">
                    <div class="col-sm-12">  
  
                      <section class="text-gray-400">
                        <h2 class="mb-4 card-header"><i class="bi bi-person"> {{__('messages.Edit User')}}</i></h2>
                          <div class="card-body p-0 p-md-3">
                  
                        @foreach($user as $user)
                        <form action="{{ route('editUser', $user->id) }}" method="post">
                          @csrf
                              <div class="form-group col-lg-8">
                              <label>{{__('messages.Name')}}</label>
                              <input type="text" class="form-control" name="name" value="{{ $user->name }}">
                            </div>
                            <div class="form-group col-lg-8">
                              <label>{{__('messages.Email')}}</label>
                              <input type="email" class="form-control" name="email" value="{{ $user->email }}">
                            </div>
                            <div class="form-group col-lg-8">
                              <label>{{__('messages.Password')}}</label>
                              <input type="password" class="form-control" name="password" placeholder="Leave empty for no change">
                            </div>

                            <div class="alert alert-light border col-lg-8" role="alert">
                              Profile images and custom backgrounds can no longer be changed from the admin UI.
                            </div>

                            <label>{{__('messages.Select theme')}}</label>
                              <div class="form-group col-lg-8">
                                  @php
                                    $slug = Auth::user()->littlelink_name;
                                    $reserved = reservedSlugs();
                                    $profileUrl = in_array($slug,$reserved) ? url('/p/'.$slug) : url('/'.$slug);
                                  @endphp
                                  <select id="theme-select" style="margin-bottom: 40px;" class="form-control" name="theme" data-base-url="{{ $profileUrl }}">
                                      <?php
                                          if ($handle = opendir('themes')) {
                                              while (false !== ($entry = readdir($handle))) {
                                                  if ($entry != "." && $entry != "..") {
                                                      if(file_exists(base_path('themes') . '/' . $entry . '/readme.md')){
                                                          $text = file_get_contents(base_path('themes') . '/' . $entry . '/readme.md');
                                                          $pattern = '/Theme Name:.*/';
                                                          preg_match($pattern, $text, $matches, PREG_OFFSET_CAPTURE);
                                                          if(sizeof($matches) > 0) {
                                                              $themeName = substr($matches[0][0],12);
                                                          }
                                                      }
                                                      if($user->theme != $entry and isset($themeName)){
                                                          echo '<option value="'.$entry.'" data-image="'.url('themes/'.$entry.'/screenshot.png').'">'.$themeName.'</option>';
                                                      }
                                                  }
                                              }
                                          }
                              
                                          if($user->theme != "default" and $user->theme != ""){
                                              if(file_exists(base_path('themes') . '/' . $user->theme . '/readme.md')){
                                                  $text = file_get_contents(base_path('themes') . '/' . $user->theme . '/readme.md');
                                                  $pattern = '/Theme Name:.*/';
                                                  preg_match($pattern, $text, $matches, PREG_OFFSET_CAPTURE);
                                                  $themeName = substr($matches[0][0],12);
                                              }
                                              echo '<option value="'.$user->theme.'" data-image="'.url('themes/'.$user->theme.'/screenshot.png').'" selected>'.$themeName.'</option>';
                                          }
                              
                                          echo '<option value="default" data-image="'.url('themes/default/screenshot.png').'"';
                                          if($user->theme == "default" or $user->theme == ""){
                                              echo ' selected';
                                          }
                                          echo '>Default</option>';
                                      ?>
                                  </select>
                              </div>
                            
                            <div class="form-group col-lg-8">
                              <label>{{__('messages.Page URL')}}</label>
                              <div class="input-group">
                            <div class="input-group-prepend">
                            <div class="input-group-text">{{ url('') }}/</div>
                            </div>
                            <input type="text" class="form-control" name="littlelink_name" value="{{ $user->littlelink_name }}">
                          </div>
                        </div>
                            
                            <div class="form-group col-lg-8">
                              <label> {{__('messages.Page description')}}</label>
                              <textarea class="form-control" name="littlelink_description" rows="3">{{ $user->littlelink_description }}</textarea>
                            </div>
                            <div class="form-group col-lg-8">
                              <label for="exampleFormControlSelect1">{{__('messages.Role')}}</label>
                              <select class="form-control" name="role">
                                <option <?= ($user->role === strtolower('user')) ? 'selected' : '' ?>>user</option>
                                <option <?= ($user->role === strtolower('vip')) ? 'selected' : '' ?>>vip</option>
                                <option <?= ($user->role === strtolower('admin')) ? 'selected' : '' ?>>admin</option>
                              </select>
                            </div>
                            {{-- MODULE: SaaS tier selection (core view extension) --}}
                            <div class="form-group col-lg-8">
                              <label>Tier</label>
                              <select class="form-control" name="tier_id">
                                <option value="" {{ (isset($currentTierSlug) && $currentTierSlug==='free') ? 'selected' : '' }}>Free</option>
                                @isset($tiers)
                                  @foreach($tiers as $tier)
                                    <option value="{{ $tier->id }}" {{ (isset($currentTierSlug) && $currentTierSlug===$tier->slug) ? 'selected' : '' }}>{{ $tier->name ?? $tier->slug }}</option>
                                  @endforeach
                                @endisset
                              </select>
                            </div>
                            @endforeach
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
