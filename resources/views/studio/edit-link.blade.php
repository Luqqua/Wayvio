@extends('layouts.sidebar')

@section('content')

@push('sidebar-stylesheets')
<script src="{{ asset('assets/external-dependencies/fontawesome.js') }}" crossorigin="anonymous"></script>
<style>
    #SelectLinkType {
        z-index: 1300;
        padding: 0.75rem;
        background: rgba(11, 31, 31, 0.45);
        overflow-y: auto;
        overscroll-behavior: contain;
    }

    #SelectLinkType:not(.is-open) {
        display: none !important;
    }

    #SelectLinkType.is-open {
        display: block !important;
    }

    body.select-linktype-modal-open {
        overflow: hidden;
    }

    body.select-linktype-modal-open .iq-banner .dashboard-topbar,
    body.select-linktype-modal-open .iq-banner .iq-navbar-header,
    body.select-linktype-modal-open .iq-banner .hero-mobile-actions,
    body.select-linktype-modal-open .iq-banner .view-share-actions,
    body.select-linktype-modal-open .iq-banner .view-page-btn,
    body.select-linktype-modal-open .iq-banner .view-page-share,
    body.select-linktype-modal-open .iq-banner .navbar-toggler {
        pointer-events: none !important;
    }

    #SelectLinkType .modal-dialog {
        max-width: min(780px, calc(100vw - 1.5rem));
        margin: 0.75rem auto;
    }

    #SelectLinkType .modal-body {
        padding: 0.9rem;
    }

    #SelectLinkType .select-linktype-grid {
        display: flex;
        flex-direction: column;
        gap: 0.6rem;
        padding: 0;
    }

    #SelectLinkType .select-linktype-option {
        display: block;
        margin: 0;
    }

    #SelectLinkType .select-linktype-card {
        margin: 0;
        border: 1px solid rgba(0, 0, 0, 0.08);
        border-radius: 0.85rem;
        overflow: hidden;
        transition: background-color 0.18s ease, border-color 0.18s ease;
    }

    #SelectLinkType .select-linktype-option:hover .select-linktype-card,
    #SelectLinkType .select-linktype-option:focus .select-linktype-card,
    #SelectLinkType .select-linktype-option:focus-visible .select-linktype-card {
        background-color: rgba(0, 0, 0, 0.04);
        border-color: rgba(0, 0, 0, 0.18);
    }

    #SelectLinkType .select-linktype-option,
    #SelectLinkType .select-linktype-option * {
        transform: none !important;
    }

    #SelectLinkType .modal-footer {
        display: none;
    }

    .add-link-actions {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.6rem;
    }

    .add-link-actions .btn {
        margin-right: 0 !important;
    }

    @media (min-width: 992px) and (max-width: 1199.98px) {
        #SelectLinkType {
            top: 72px;
            left: var(--sidebar-width, 16.2rem);
            width: calc(100vw - var(--sidebar-width, 16.2rem));
            height: calc(100vh - 72px);
            padding: 0.45rem;
            background: rgba(11, 31, 31, 0.26);
        }

        #SelectLinkType .modal-dialog {
            max-width: min(780px, calc(100vw - var(--sidebar-width, 16.2rem) - 1.5rem));
            margin: 0.75rem auto;
        }
    }

    @media (max-width: 991.98px) {
        #SelectLinkType {
            top: 72px;
            height: calc(100vh - 72px);
            padding: 0.45rem;
            background: rgba(11, 31, 31, 0.26);
        }

        #SelectLinkType .modal-dialog {
            max-width: 100%;
            margin: 0;
            min-height: 100%;
        }

        #SelectLinkType .modal-content {
            min-height: 100%;
            border-radius: 0.85rem;
        }
    }

    @media (max-width: 575.98px) {
        #SelectLinkType {
            top: 64px;
            height: calc(100vh - 64px);
            padding: 0.35rem;
        }

        .add-link-actions .btn {
            width: 100%;
            justify-content: center;
        }

        #SelectLinkType .modal-dialog {
            max-width: 100%;
            margin: 0;
        }

        #SelectLinkType .modal-content {
            max-height: 100%;
            border-radius: 0.8rem;
        }

        #SelectLinkType .modal-body {
            padding: 0.65rem;
        }

        #SelectLinkType .select-linktype-card .row {
            align-items: stretch;
        }

        #SelectLinkType .select-linktype-card .card-body {
            padding: 0.7rem 0.8rem;
        }

        #SelectLinkType .select-linktype-card .card-title {
            font-size: 0.95rem;
            line-height: 1.25rem;
        }

        #SelectLinkType .select-linktype-card .card-text {
            font-size: 0.82rem;
            line-height: 1.2rem;
            margin-top: 0.2rem;
            margin-bottom: 0;
        }

        #SelectLinkType .select-linktype-icon {
            min-width: 56px;
            padding: 0.55rem !important;
        }

        #SelectLinkType .select-linktype-icon i {
            font-size: 1.35rem !important;
        }
    }
</style>
@endpush

<div class="conatiner-fluid content-inner mt-n5 py-0 ls-consistent-spacing">
    <div class="row">   
        
     <div class="col-lg-12">
        <div class="card rounded">
            <div class="card-body">
               <div class="row">
                   <div class="col-sm-12">  
  
                    @push('sidebar-stylesheets')
                    <script src="{{ asset('assets/js/jquery.min.js') }}"></script>
                    @endpush
                    
                    <section class='text-gray-400'>
                    
                        <h3 class="card-header"><i class="bi bi-journal-plus"> @if($LinkID !== 0) {{__('messages.Edit')}} @else {{__('messages.Add')}} @endif {{__('messages.Block')}}</i></h3>
                    
                        <div class='card-body'>
                            <form action="{{ route('addLink') }}" method="post" id="my-form" autocomplete="off" data-lpignore="true">
                                @method('POST')
                                @csrf
                                <input type='hidden' name='linkid' value="{{ $LinkID }}" />
                                <input type="hidden" name="page_id" value="{{ (int) ($selectedPageId ?? 0) }}" />
                    
                                <div class="form-group col-lg-8 flex justify-around">
                                    @php $isEditingExistingBlock = (int) ($LinkID ?? 0) > 0; @endphp
                                    <div class="btn-group shadow m-2">
                                        <button
                                            type="button"
                                            id='btnLinkType'
                                            class="btn btn-primary rounded-pill js-open-linktype-modal"
                                            title='{{ $isEditingExistingBlock ? "Blocktyp kann bei bestehenden Blöcken nicht geändert werden." : __("messages.Click to change link blocks") }}'
                                            @if($isEditingExistingBlock) disabled aria-disabled="true" @endif
                                        >{{__('messages.Select Block')}}
                                            <span class="btn-inner">
                                                <i class="bi bi-window-plus"></i>
                                            </span>
                                        </button>{{infoIcon(__('messages.Click for a list of available link blocks'))}}
                                          
                                        <input type='hidden' name='typename' value='{{$typename}}'>
                    
                                    </div>
                                    @error('typename')
                                    <div class="text-danger small m-2">{{ $message }}</div>
                                    @enderror
                                    @if($isEditingExistingBlock)
                                    <div class="text-muted small m-2">Blocktyp ist beim Bearbeiten gesperrt.</div>
                                    @endif
                                </div>

                                <div id='link_params' class='col-lg-8'></div>

                                <div class="add-link-actions pt-4">
                                    <a class="btn btn-danger me-3" href="{{ url('studio/links') }}">{{__('messages.Cancel')}}</a>
                                    <button type="submit" class="btn btn-primary me-3">{{__('messages.Save')}}</button>
                                    <button type="button" class="btn btn-soft-primary me-3" onclick="submitFormWithParam('add_more')">{{__('messages.Save and Add More')}}</button>
                                    <script>
                                        function submitFormWithParam(paramValue) {
                                            // get the form element
                                            var form = document.getElementById("my-form");
                                            
                                            // create a hidden input field with the parameter value
                                            var paramField = document.createElement("input");
                                            paramField.setAttribute("type", "hidden");
                                            paramField.setAttribute("name", "param");
                                            paramField.setAttribute("value", paramValue);
                                            // append the hidden input field to the form
                                            form.appendChild(paramField);
                                            // submit the form
                                            form.submit();
                                        }
                                        </script>
                                </div>                                
                    
                            </form>
                        </div>
                    </section>
                    <br><br>

                    <!-- Modal -->
                    <style>.modal-title{color:#000!important;}</style>
                    <x-modal title="{{__('messages.Select Block')}}" id="SelectLinkType">
                    
                        <div class="select-linktype-grid">
                            @foreach ($LinkTypes as $lt)
                            @php 
                            if(block_text_translation_check($lt['title'])) {$title = bt($lt['title']);} else {$title = __('messages.block.title.'.$lt['typename']);}
                            $description = bt($lt['description']) ?? __('messages.block.description.'.$lt['typename']); 
                            @endphp
                            <a href="#" data-typeid="{{$lt['typename']}}" data-typename="{{$title}}" class="doSelectLinkType select-linktype-option">
                                <div class="shadow-lg select-linktype-card">
                                    <div class="row g-0">
                                        <div class="col-auto bg-light d-flex align-items-center justify-content-center p-3 select-linktype-icon">
                                            <i class="{{$lt['icon']}} text-primary h1 mb-0"></i>
                                        </div>
                                        <div class="col">
                                            <div class="card-body">
                                                <h5 class="card-title text-dark mb-0">{{$title}}</h5>
                                                <p class="card-text text-muted">{{$description}}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>      
                            </a>                     
                            @endforeach
                    
                        </div>
                    
                        <x-slot name="buttons">
                            
                        </x-slot>
                    
                    </x-modal>
  
                   </div>
               </div>
            </div>
         </div>
        </div>
        
      </div>
    </div>

@endsection

@push("sidebar-scripts")
<script>
$(function() {
    var isEditingExistingBlock = @json(((int) ($LinkID ?? 0) > 0));
    var initialTypeName = ($("input[name='typename']").val() || '').trim();
    var initialLinkId = $("input[name=linkid]").val();
    if (initialTypeName !== '') {
        LoadLinkTypeParams(initialTypeName, initialLinkId);
    }
    var $selectLinkTypeModal = $('#SelectLinkType');
    var $body = $('body');
    var linkInputSelector = [
        'input[type="url"]',
        'input[name="link"]',
        'input[name$="_url"]',
        'input[name$="_link"]',
        'input[name*="[avatar_url]"]',
        'input[name*="[url]"]',
        'input[name*="[link]"]'
    ].join(', ');

    function disableLinkInputAutofill($scope) {
        var $root = $scope && $scope.length ? $scope : $(document);
        var $fields = $root.find(linkInputSelector);
        if (!$fields.length) {
            return;
        }

        $fields.each(function() {
            var $field = $(this);
            if (($field.attr('type') || '').toLowerCase() === 'url') {
                $field.attr('type', 'text');
            }

            $field.attr({
                autocomplete: 'new-password',
                autocapitalize: 'off',
                autocorrect: 'off',
                spellcheck: 'false',
                'aria-autocomplete': 'none',
                inputmode: 'url',
                'data-lpignore': 'true',
                'data-1p-ignore': 'true'
            });

            if ($field.attr('data-private-field-bound') === '1') {
                return;
            }

            $field.attr('data-private-field-bound', '1');
            $field.attr('readonly', 'readonly');
            $field.on('focus pointerdown touchstart', function() {
                this.removeAttribute('readonly');
            });
            $field.on('blur', function() {
                this.setAttribute('readonly', 'readonly');
            });
        });
    }

    function closeMobileNavbarIfOpen() {
        var $mobileCollapse = $('#navbarSupportedContent');
        if (!$mobileCollapse.length || !$mobileCollapse.hasClass('show')) {
            return;
        }

        if (window.bootstrap && window.bootstrap.Collapse) {
            var collapseEl = document.getElementById('navbarSupportedContent');
            if (collapseEl) {
                var collapseApi = window.bootstrap.Collapse;
                var collapseInstance = null;

                if (typeof collapseApi.getOrCreateInstance === 'function') {
                    collapseInstance = collapseApi.getOrCreateInstance(collapseEl, { toggle: false });
                } else if (typeof collapseApi.getInstance === 'function') {
                    collapseInstance = collapseApi.getInstance(collapseEl);
                    if (!collapseInstance && typeof collapseApi === 'function') {
                        collapseInstance = new collapseApi(collapseEl, { toggle: false });
                    }
                } else if (typeof collapseApi === 'function') {
                    collapseInstance = new collapseApi(collapseEl, { toggle: false });
                }

                if (collapseInstance && typeof collapseInstance.hide === 'function') {
                    collapseInstance.hide();
                    return;
                }
            }
        }

        $mobileCollapse.collapse('hide');
    }

    function openSelectLinkTypeModal() {
        closeMobileNavbarIfOpen();
        $body.addClass('select-linktype-modal-open');
        $selectLinkTypeModal.addClass('is-open show').attr('aria-hidden', 'false');
    }

    function hideSelectLinkTypeModal() {
        $selectLinkTypeModal.removeClass('is-open show').attr('aria-hidden', 'true');
        $body.removeClass('select-linktype-modal-open');
    }

    $('.js-open-linktype-modal').on('click', function(event) {
        event.preventDefault();
        if (isEditingExistingBlock) {
            return;
        }
        openSelectLinkTypeModal();
    });

    $('.doSelectLinkType').on('click', function(event) {
        event.preventDefault();
        if (isEditingExistingBlock) {
            return;
        }
        var selectedLabel = $(this).data('typename');
        $("input[name='typename']").val($(this).data('typeid'));
        $("#btnLinkType").html(selectedLabel + ' <span class="btn-inner"><i class="bi bi-window-plus"></i></span>');

        LoadLinkTypeParams($(this).data('typeid'), $("input[name=linkid]").val());

        hideSelectLinkTypeModal();
    });

    $selectLinkTypeModal.on('click', '[data-dismiss="modal"], [data-bs-dismiss="modal"]', function(event) {
        event.preventDefault();
        hideSelectLinkTypeModal();
    });

    $(window).on('resize', function() {
        if (window.innerWidth >= 1200 && $selectLinkTypeModal.hasClass('is-open')) {
            hideSelectLinkTypeModal();
        }
    });

    function LoadLinkTypeParams($TypeId, $LinkId) {
        var baseURL = <?php echo "\"" . url('') . "\""; ?>;
        $("#link_params")
            .html('<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>')
            .load(baseURL + `/studio/linkparamform_part/${$TypeId}/${$LinkId}`, function(response, status, xhr) {
                if (status === 'error') {
                    var statusCode = xhr && xhr.status ? xhr.status : '';
                    $("#link_params").html('<div class="alert alert-danger small mb-0">Block konnte nicht geladen werden' + (statusCode ? ' (' + statusCode + ')' : '') + '.</div>');
                    return;
                }
                disableLinkInputAutofill($(this));
                document.dispatchEvent(new Event('contentLoaded'));
            });
    }

    disableLinkInputAutofill($(document));
});
</script>
@endpush
