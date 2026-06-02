@extends('layouts.sidebar')

@section('content')

<?php use App\Models\Button;
$activePageId = app(\App\Services\Agency\AgencyHubContext::class)->editingUserId(auth()->user(), request());
$activePageSlug = isset($activePageSlug) ? trim((string) $activePageSlug) : '';
if ($activePageSlug === '') {
  $activePageSlug = (string) \App\Models\User::query()->whereKey($activePageId)->value('littlelink_name');
}

// Check if the LinkCount cookie is set
if (isset($_COOKIE['LinkCount'])) {
  // Set the expiration time of the cookie to one hour in the past
  setcookie('LinkCount', '', time() - 3600);
}

?>

<style>
.sortable-handle {
    margin-right: 25px;
    width: 25px;
    height: auto;
    transform: rotate(90deg);
    cursor: grab;
    cursor: -webkit-grabbing;
    fill: currentColor;
}

.ls-block-type-hint {
    display: inline-flex;
    align-items: center;
    border: 1px solid #d9dee4;
    border-radius: 999px;
    padding: 2px 8px;
    font-size: 0.72rem;
    line-height: 1.2;
    color: #667085;
    background: #f8fafc;
}

.ls-link-icon-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 25px;
    height: 25px;
    border: 1px solid #d0d4d7 !important;
    border-radius: 5px;
    background-color: rgba(108, 117, 125, 0.14);
    color: var(--bs-body-color, #1f2937);
}

.ls-link-icon-chip i {
    color: currentColor !important;
    margin: 0 !important;
    line-height: 1;
}

.ls-link-icon-chip img {
    width: 15px;
    height: 15px;
    object-fit: contain;
}

@media (max-width: 575.98px) {
    .ls-links-header-actions {
        width: 100%;
    }
    .ls-links-header-actions .btn {
        flex: 1;
        white-space: normal;
        text-align: center;
        line-height: 1.3;
        padding-top: 0.75rem;
        padding-bottom: 0.75rem;
    }
    .ls-links-header-actions {
        margin-top: 0.5rem;
        margin-bottom: 0.25rem;
    }
}
</style>

@push('sidebar-stylesheets')
<script src="{{ asset('assets/external-dependencies/fontawesome.js') }}" crossorigin="anonymous"></script>
<style>
@media only screen and (max-width: 1500px) {
  .pre-side{display:none!important;}
  .pre-left{width:100%!important;}
  .pre-bottom{display:block!important;}
}

@media only screen and (min-width: 1501px) {
  .pre-left{width:70%!important;}
  .pre-right{width:30%!important;}
  .pre-bottom{display:none!important;}
}
</style>
<style>.delete{position:relative; color:transparent; background-color:tomato; border-radius:5px; left:5px; padding:5px 12px; cursor: pointer;}.delete:hover{color:transparent;background-color:#f13d1d;}html,body{max-width:100%;overflow-x:hidden;}</style>
@endpush

@include('components.favicon')
@include('components.favicon-extension')

<?php if(!function_exists('strp')){function strp($urlStrp){return str_replace(array('http://', 'https://'), '', $urlStrp);}} ?>

<div class="conatiner-fluid content-inner mt-n5 pt-0 pb-4 ls-consistent-spacing">
    <div class="row gx-3 gy-4">
        <div class="col-12">
            <div class="row gx-3 gy-4">
                <section class="pre-left text-gray-400">
                    <div class="card rounded">
                        <div class="card-body">
                            <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-link-45deg"></i>
                                    <h4 class="mb-0">{{__('messages.My Links')}}</h4>
                                </div>
                                <div class="d-flex align-items-center gap-2 ls-links-header-actions">
                                    <a class="btn btn-outline-secondary" href="#icons"><i class="bi bi-icons me-1"></i>{{__('messages.Page Icons')}}</a>
                                    <a class="btn btn-primary" href="{{ route('showButtons', ['page_id' => $activePageId]) }}">{{__('messages.Add new Link')}}</a>
                                </div>
                            </div>

                            <div>
                            
                                    {{-- <div style="text-align: right;"><a href="{{ url('/studio/links') }}/10">10</a> | <a href="{{ url('/studio/links') }}/20">20</a> | <a href="{{ url('/studio/links') }}/30">30</a> | <a href="{{ url('/studio/links') }}/all">all</a></div> --}}
                            
                                <div style="overflow-y: none;" class="col col-md-7 ms-3">
                            
                                    <div id="links-table-body" data-page="{{request('page', 1)}}" data-per-page="{{$pagePage ? $pagePage : 0}}">
                                        @if($links->total() == 0)
                                              <div class="col-6 text-center">
                                                <p class="mt-5">{{__('messages.No Link Added')}}</p>
                                              </div>
                                        @else
                                        @foreach($links as $link)
                                        @php $button = Button::find($link->button_id); if(isset($button->name)){$buttonName = $button->name;}else{$buttonName = 0;} @endphp
                                        @php $studioTitle = trim((string) ($link->studio_title ?? $link->title ?? '')); @endphp
                                        @php if($buttonName == "default email"){$buttonName = "email";} if($buttonName == "default email_alt"){$buttonName = "email_alt";} @endphp
                                        @if($button && $button->name !== 'icon')
                                        <div class='row h-100 pb-0 mb-2 border rounded hvr-glow w-100' data-id="{{$link->id}}">
                                            <div class="d-flex ">
                            
                            
                                                <div class='col-auto p-2 my-auto mr-2' title="{{ $link->link }}">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="sortable-handle" viewBox="0 0 16 16">
                                                        <path d="M1 4a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V4zm5 0a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4zm5 0a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1h-2a1 1 0 0 1-1-1V4zM1 9a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V9zm5 0a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V9zm5 0a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1h-2a1 1 0 0 1-1-1V9z"/>
                                                      </svg>
                                                </div>
                            
                                            <div class='col h-100'>
                            
                                                <div class='row h-100'>
                                                    <div class='col-12 p-2' style="max-width:300px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;" title="{{ $studioTitle }}">
                                                        <span class='h6'>
                                                            @if($button->name == "custom_website")
                                                            @php 
                                                              $rawCustomIcon = trim((string) ($link->custom_icon ?? ''));
                                                              $legacyFaMap = [
                                                                  'fa-newspaper-o' => 'fa-newspaper',
                                                                  'fa-file-text-o' => 'fa-file-lines',
                                                                  'fa-lightbulb-o' => 'fa-lightbulb',
                                                              ];
                                                              $normalizedRawIcon = strtolower($rawCustomIcon);
                                                              if (isset($legacyFaMap[$normalizedRawIcon])) {
                                                                  $rawCustomIcon = $legacyFaMap[$normalizedRawIcon];
                                                              }
                                                              $customFaIcon = (preg_match('/^fa-[a-z0-9-]+$/i', $rawCustomIcon) === 1) ? strtolower($rawCustomIcon) : null;
                                                              $customBiIcon = (preg_match('/^bi-[a-z0-9-]+$/i', $rawCustomIcon) === 1) ? strtolower($rawCustomIcon) : null;
                                                              $useLegacyWebsiteFallback = $rawCustomIcon === 'fa-external-link';
                                                            @endphp
                                                            <span class="ls-link-icon-chip">
                                                                @if(!$useLegacyWebsiteFallback && $customBiIcon)
                                                                    <i class="bi {{ $customBiIcon }} icon hvr-icon"></i>
                                                                @elseif(!$useLegacyWebsiteFallback && $customFaIcon)
                                                                    <i class="fa {{ $customFaIcon }} icon hvr-icon"></i>
                                                                @else
                                                                    <img alt="button-icon" class="icon hvr-icon" src="{{ file_exists(base_path('assets/favicon/icons/').localIcon($link->id)) ? url('assets/favicon/icons/'.localIcon($link->id)) : getFavIcon($link->id) }}" onerror="this.onerror=null; this.src='{{asset('assets/wayvio/icons/website.svg')}}';">
                                                                @endif
                                                            </span>
                                                            @elseif($button->name == "space")
                                                            <span class="ls-link-icon-chip"><i class='bi bi-distribute-vertical'>&nbsp;</i></span>
                                                            @elseif($button->name == "heading")
                                                            <span class="ls-link-icon-chip"><i class='bi bi-card-heading'>&nbsp;</i></span>
                                                            @elseif($button->name == "text")
                                                            <span class="ls-link-icon-chip"><i class='bi bi-fonts'>&nbsp;</i></span>
                                                            @elseif($link->custom_icon && $link->type && $link->type !== 'predefined')
                                                            @php
                                                                $rawInlineIcon = trim((string) $link->custom_icon);
                                                                $legacyInlineFaMap = [
                                                                    'fa-newspaper-o' => 'fa-newspaper',
                                                                    'fa-file-text-o' => 'fa-file-lines',
                                                                    'fa-lightbulb-o' => 'fa-lightbulb',
                                                                ];
                                                                $normalizedInlineIcon = strtolower($rawInlineIcon);
                                                                if (isset($legacyInlineFaMap[$normalizedInlineIcon])) {
                                                                    $rawInlineIcon = $legacyInlineFaMap[$normalizedInlineIcon];
                                                                }
                                                                $inlineFaIcon = preg_match('/^fa-[a-z0-9-]+$/i', $rawInlineIcon) === 1 ? strtolower($rawInlineIcon) : null;
                                                                $inlineBiIcon = preg_match('/^bi-[a-z0-9-]+$/i', $rawInlineIcon) === 1 ? strtolower($rawInlineIcon) : null;
                                                            @endphp
                                                            <span class="ls-link-icon-chip">@if($inlineBiIcon)<i class="bi {{ $inlineBiIcon }}">&nbsp;</i>@else<i class='fa {{ $inlineFaIcon ?: "fa-external-link" }}'>&nbsp;</i>@endif</span>
                                                            @else
                                                            <span class="ls-link-icon-chip"><img alt="button-icon" class="m-1" src="{{ asset('\/assets/wayvio/icons\/') . $buttonName }}.svg "></span>
                                                            @endif
                            
                                                            {{ strip_tags($studioTitle, '') }}</span>
                            
                                                        @if(!empty($link->link) and $button->name != "vcard")
                                                        <br>
                                                        <a title='{{$link->link}}' href="{{ $link->link}}" target="_blank" class="d-none d-md-block ml-4 text-muted small">{{Str::limit($link->link, 75 )}}</a>
                                                        <a title='{{$link->link}}' href="{{ $link->link}}" target="_blank" class="d-md-none ml-4 text-muted small">{{Str::limit($link->link, 25 )}}</a>
                                                        @elseif(!empty($link->link) and $button->name == "vcard")
                                                        <br><a href="{{ url('vcard/'.$link->id) }}" target="_blank" class="ml-4 small">{{__('messages.Download')}}</a>
                            
                                                        @endif
                            
                                                    </div>
                            
                                                    <div class='col' class="text-right">
                                                        {{Str::limit($link->params['text'] ?? null, 150)  }}
                            
                                                        @if($link->typename == 'video')
                                                            @php
                                                                $videoUrl = is_string($link->link ?? null) ? trim($link->link) : '';
                                                                $thumbnailUrl = '';

                                                                if ($videoUrl !== '') {
                                                                    if (!preg_match('/^https?:\/\//i', $videoUrl)) {
                                                                        $videoUrl = 'https://' . ltrim($videoUrl, '/');
                                                                    }

                                                                    $parts = parse_url($videoUrl);
                                                                    if (is_array($parts)) {
                                                                        $host = strtolower((string) ($parts['host'] ?? ''));
                                                                        $path = trim((string) ($parts['path'] ?? ''), '/');
                                                                        $query = [];
                                                                        parse_str((string) ($parts['query'] ?? ''), $query);
                                                                        $segments = array_values(array_filter(explode('/', $path), 'strlen'));

                                                                        $videoId = '';
                                                                        if ($host === 'youtu.be' || str_ends_with($host, '.youtu.be')) {
                                                                            $videoId = (string) ($segments[0] ?? '');
                                                                        } elseif ($host === 'youtube.com' || str_ends_with($host, '.youtube.com')) {
                                                                            if (($segments[0] ?? '') === 'watch') {
                                                                                $videoId = (string) ($query['v'] ?? '');
                                                                            } elseif (in_array((string) ($segments[0] ?? ''), ['embed', 'shorts', 'live'], true)) {
                                                                                $videoId = (string) ($segments[1] ?? '');
                                                                            }
                                                                        }

                                                                        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId) === 1) {
                                                                            $thumbnailUrl = 'https://i.ytimg.com/vi/' . $videoId . '/hqdefault.jpg';
                                                                        }
                                                                    }
                                                                }
                                                            @endphp

                                                            @if($thumbnailUrl !== '')
                                                                <img style='max-height: 150px;' src="{{ $thumbnailUrl }}" alt="Video thumbnail" />
                                                            @endif
                            
                                                        @endif
                                                    </div>
                            
                            
                                                    <div class='col-12 py-1 px-3 m-0 mt-2'>
                                                        @php
                                                            $resolvedTypeName = trim((string) ($link->type ?? ''));
                                                            if ($resolvedTypeName === '') {
                                                                $resolvedTypeName = 'predefined';
                                                            }
                                                            $typeTitleKey = 'messages.block.title.' . $resolvedTypeName;
                                                            $typeTitle = __($typeTitleKey);
                                                            if ($typeTitle === $typeTitleKey) {
                                                                $typeTitle = ucfirst(str_replace('_', ' ', $resolvedTypeName));
                                                            }
                                                        @endphp
                                                        <span class="ls-block-type-hint" title="Blocktyp">
                                                            {{ $typeTitle }}@if(!empty($link->studio_uses_fallback_title)) · Auto-Titel @endif
                                                        </span>
                                                        <form method="POST" action="{{ route('deleteLink', $link->id ) }}" style="float: right; display: inline;" onsubmit="return confirm('{{ __('messages.confirm_delete', ['title' => addslashes($studioTitle)]) }}')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-sm me-1 btn-icon btn-danger" data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Delete" data-original-title="{{__('messages.Delete')}}">
                                                                <span class="btn-inner">
                                                                   <svg class="icon-20" width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor">
                                                                      <path d="M19.3248 9.46826C19.3248 9.46826 18.7818 16.2033 18.4668 19.0403C18.3168 20.3953 17.4798 21.1893 16.1088 21.2143C13.4998 21.2613 10.8878 21.2643 8.27979 21.2093C6.96079 21.1823 6.13779 20.3783 5.99079 19.0473C5.67379 16.1853 5.13379 9.46826 5.13379 9.46826" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                                                      <path d="M20.708 6.23975H3.75" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                                                      <path d="M17.4406 6.23973C16.6556 6.23973 15.9796 5.68473 15.8256 4.91573L15.5826 3.69973C15.4326 3.13873 14.9246 2.75073 14.3456 2.75073H10.1126C9.53358 2.75073 9.02558 3.13873 8.87558 3.69973L8.63258 4.91573C8.47858 5.68473 7.80258 6.23973 7.01758 6.23973" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                                                   </svg>
                                                                </span>
                                                            </button>
                                                        </form>

                                                            <a style="float: right;" href="{{ route('editLink', $link->id ) }}" class="btn btn-sm me-1 btn-icon btn-warning" data-bs-toggle="tooltip" data-bs-placement="top" data-original-title="{{__('messages.Edit')}}" aria-label="Edit" data-bs-original-title="{{__('messages.Edit')}}">
                                                               <span class="btn-inner">
                                                                  <svg class="icon-20" width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                     <path d="M11.4925 2.78906H7.75349C4.67849 2.78906 2.75049 4.96606 2.75049 8.04806V16.3621C2.75049 19.4441 4.66949 21.6211 7.75349 21.6211H16.5775C19.6625 21.6211 21.5815 19.4441 21.5815 16.3621V12.3341" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                                                     <path fill-rule="evenodd" clip-rule="evenodd" d="M8.82812 10.921L16.3011 3.44799C17.2321 2.51799 18.7411 2.51799 19.6721 3.44799L20.8891 4.66499C21.8201 5.59599 21.8201 7.10599 20.8891 8.03599L13.3801 15.545C12.9731 15.952 12.4211 16.181 11.8451 16.181H8.09912L8.19312 12.401C8.20712 11.845 8.43412 11.315 8.82812 10.921Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                                                     <path d="M15.1655 4.60254L19.7315 9.16854" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                                                  </svg>
                                                               </span>
                                                            </a>

                                                        @if(file_exists(base_path("assets/favicon/icons/").localIcon($link->id)))
                                                            <form method="POST" action="{{ route('clearIcon', $link->id ) }}" style="float: right; display: inline;">
                                                                @csrf
                                                                <button type="submit" data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Add" data-original-title="Clear icon cache" class="float-right hvr-grow p-1 text-primary border-0 bg-transparent">
                                                                    <i style="-webkit-text-stroke:1px;padding-right:5px;" class="bi bi-arrow-repeat"></i>
                                                                </button>
                                                            </form>
                                                        @endif
                            
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        </div>
                                        @endif
                                        @endforeach
                                        @endif
                                    </div>
                            
                            
                                    <script type="text/javascript">
                                        const linksTableOrders = "{{ implode(' | ', $links->pluck('id')->toArray()) }}"
                                    </script>
                                </div>
                            
                                <ul class="pagination justify-content-center">
                                    {!! $links ?? ''->links() !!}
                                </ul>
                            
                                @if(count($links) > 3)<a class="btn btn-primary" href="{{ url('/studio/add-link') }}">{{__('messages.Add new Link')}}</a>@endif
                            </div>
                        </div>
                    </div>
                </section>

                <section class="pre-right text-gray-400 pre-side">
                    <div class="card rounded">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <i class="bi bi-window-fullscreen"></i>
                                <h4 class="mb-0">{{__('messages.Preview')}}</h4>
                            </div>
                            <div class="p-0 p-md-3">
                                @php
                                  $slug = $activePageSlug;
                                  $reserved = reservedSlugs();
                                  $previewBaseUrl = in_array($slug, $reserved) ? url('/p/' . $slug) : url('/' . $slug);
                                  $previewUrl = $previewBaseUrl . (str_contains($previewBaseUrl, '?') ? '&' : '?') . 'studio_preview=1';
                                @endphp
                                <center><iframe allowtransparency="true" id="frPreview1" style=" border-radius:0.25rem !important; background: #FFFFFF; min-height:600px; height:100%; max-width:500px !important;" class='w-100' src="{{ $previewUrl }}">{{__('messages.No compatible browser')}}</iframe></center>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <div class="col-12">
            <section class="pre-bottom text-gray-400 pre-side">
                <div class="card rounded">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <i class="bi bi-window-fullscreen"></i>
                            <h4 class="mb-0">{{__('messages.Preview')}}</h4>
                        </div>
                        <div class="p-0 p-md-3">
                            <center><iframe allowtransparency="true" id="frPreview2" style=" border-radius:0.25rem !important; background: #FFFFFF; min-height:600px; height:100%; width:100% !important;" class='w-100' src="{{ $previewUrl }}">{{__('messages.No compatible browser')}}</iframe></center>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="col-12">
            <section class="text-gray-400">
                <div class="card rounded">
                    <div class="card-body">
                        <a name="icons"></a>
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <i class="fa-solid fa-icons"></i>
                            <h4 class="mb-0">{{__('messages.Page Icons')}}</h4>
                        </div>
                            
                            <form action="{{ route('editIcons') }}" enctype="multipart/form-data" method="post" autocomplete="off" data-lpignore="true">
                                @csrf
                                <div class="form-group col-lg-8">
                            
                                    @php
                                    if (!function_exists('iconLink')) {
                                        function iconLink($icon) {
                                            $editorUser = auth()->user();
                                            $activeUserId = $editorUser
                                                ? app(\App\Services\Agency\AgencyHubContext::class)->editingUserId($editorUser, request())
                                                : 0;
                                            $iconQuery = \App\Models\Link::where('user_id', $activeUserId)
                                            ->where('title', $icon)
                                            ->where('button_id', 94);
                                            if (\Illuminate\Support\Facades\Schema::hasColumn('links', 'is_disabled')) {
                                                $iconQuery->where('is_disabled', false);
                                            }
                                            $iconLink = $iconQuery->value('link');
                                            if (is_null($iconLink)){
                                                return false;
                                            } else {
                                                return $iconLink;
                                            }
                                        }
                                    }
                                    
                                    if (!function_exists('searchIcon')) {
                                        function searchIcon($icon) {
                                            $editorUser = auth()->user();
                                            $activeUserId = $editorUser
                                                ? app(\App\Services\Agency\AgencyHubContext::class)->editingUserId($editorUser, request())
                                                : 0;
                                            $iconIdQuery = \App\Models\Link::where('user_id', $activeUserId)
                                            ->where('title', $icon)
                                            ->where('button_id', 94);
                                            if (\Illuminate\Support\Facades\Schema::hasColumn('links', 'is_disabled')) {
                                                $iconIdQuery->where('is_disabled', false);
                                            }
                                            $iconId = $iconIdQuery->value('id');
                                            if (is_null($iconId)){
                                                return false;
                                            } else {
                                                return $iconId;
                                            }
                                        }
                                    }

                                    if (!function_exists('icon')) {
                                        function icon($name, $label) {
                                            echo '<div class="mb-3">
                                                    <label class="form-label">'.$label.'</label>
                                                    <div class="input-group">
                                                      <span class="input-group-text"><i class="fab fa-'.$name.'"></i></span>
                                                      <input type="text" class="form-control" name="'.$name.'" value="'.iconLink($name).'" autocomplete="new-password" autocapitalize="off" autocorrect="off" spellcheck="false" inputmode="url" aria-autocomplete="none" data-lpignore="true" data-1p-ignore="true" readonly onfocus="this.removeAttribute(\'readonly\')" onpointerdown="this.removeAttribute(\'readonly\')" ontouchstart="this.removeAttribute(\'readonly\')" onblur="this.setAttribute(\'readonly\', \'readonly\')" />
                                                      '.(searchIcon($name) != NULL ? '<a href="'.route("deleteLink", searchIcon($name)).'" class="btn btn-danger"><i class="bi bi-trash-fill"></i></a>' : '').'
                                                    </div>
                                                  </div>';
                                        }
                                    }
                                    @endphp
                                    <style>input{border-top-right-radius: 0.25rem!important; border-bottom-right-radius: 0.25rem!important;}</style>
                            
                            
                                {!!icon('mastodon', 'Mastodon')!!}
                            
                                {!!icon('instagram', 'Instagram')!!}
                            
                                {!!icon('x-twitter', 'X')!!}
                            
                                {!!icon('facebook', 'Facebook')!!}
                            
                                {!!icon('github', 'GitHub')!!}
                            
                                {!!icon('twitch', 'Twitch')!!}
                            
                                {!!icon('linkedin', 'LinkedIn')!!}
                            
                                {!!icon('tiktok', 'TikTok')!!}
                            
                                {!!icon('discord', 'Discord')!!}
                            
                                {!!icon('youtube', 'YouTube')!!}
                            
                                {!!icon('snapchat', 'Snapchat')!!}
                            
                                {!!icon('reddit', 'Reddit')!!}
                            
                                {!!icon('pinterest', 'Pinterest')!!}

                                {!!icon('telegram', 'Telegram')!!}

                                {!!icon('whatsapp', 'WhatsApp')!!}

                                {!! icon('behance', 'Behance') !!}

                                {!! icon('dribbble', 'Dribble') !!}

                                {!! icon('bluesky', 'Bluesky') !!}

                                {!! icon('threads', 'Threads') !!}

                        
                            
                                <button type="submit" class="mt-3 ml-3 btn btn-primary">{{__('messages.Save links')}}</button>
                            </form>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>

<script src="{{ asset('assets/external-dependencies/jquery-1.12.4.min.js') }}"></script>
<script type="text/javascript">
$("iframe").load(function() {
    $("iframe").contents().find("a").each(function(index) {
        $(this).on("click", function(event) {
            event.preventDefault();
            event.stopPropagation();
        });
    });
});

$(function() {
    $("form[action*='edit-icons'] input.form-control[name]").each(function() {
        var $field = $(this);
        var name = ($field.attr("name") || "").toLowerCase();
        if (!name) {
            return;
        }

        $field.attr({
            type: "text",
            autocomplete: "new-password",
            autocapitalize: "off",
            autocorrect: "off",
            spellcheck: "false",
            "aria-autocomplete": "none",
            inputmode: "url",
            "data-lpignore": "true",
            "data-1p-ignore": "true"
        });

        if (!$field.attr("readonly")) {
            $field.attr("readonly", "readonly");
        }

        $field.on("focus pointerdown touchstart", function() {
            this.removeAttribute("readonly");
        });

        $field.on("blur", function() {
            this.setAttribute("readonly", "readonly");
        });
    });
});
</script>

@endsection
