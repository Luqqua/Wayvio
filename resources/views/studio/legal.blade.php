@extends('layouts.sidebar')

@section('content')
<div class="conatiner-fluid content-inner mt-n5 pt-0 pb-4 legal-editor-page ls-consistent-spacing">
    <style>
        .legal-editor-page .legal-stack {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .legal-editor-page .legal-shell {
            border-radius: 14px;
        }

        .legal-editor-page .legal-banner {
            background: #ffffff;
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 14px;
            padding: 22px 24px;
        }

        .legal-editor-page .legal-banner h2 {
            margin: 0;
            font-size: 1.35rem;
            font-weight: 700;
            color: #1c1c1c;
        }

        .legal-editor-page .legal-banner p {
            margin: 8px 0 0;
            color: #5b5b5b;
        }

        .legal-editor-page .imprint-card {
            margin-bottom: 24px;
            border-radius: 12px;
        }

        .legal-editor-page .imprint-cards-stack {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .legal-editor-page .privacy-preview {
            white-space: pre-wrap;
            line-height: 1.55;
            font-size: 0.95rem;
            color: #2f2f2f;
            max-height: 420px;
            overflow: auto;
        }

        body.dark .legal-editor-page .legal-banner {
            background: #1f2430;
            border-color: rgba(255, 255, 255, 0.12);
        }

        body.dark .legal-editor-page .legal-banner h2 {
            color: #f2f4f8;
        }

        body.dark .legal-editor-page .legal-banner p {
            color: #b6c0d1;
        }

        body.dark .legal-editor-page .legal-shell,
        body.dark .legal-editor-page .imprint-card {
            background: #1f2733;
            border-color: rgba(255, 255, 255, 0.1);
        }

        body.dark .legal-editor-page .card-header {
            color: #e9edf5;
        }

        body.dark .legal-editor-page .privacy-preview {
            color: #d4dbea;
        }

        @media (min-width: 768px) and (max-width: 1199.98px) {
            .legal-editor-page .privacy-sync-row {
                min-height: 3.15rem;
            }
        }

        @media (max-width: 767.98px) {
            .legal-editor-page .legal-banner {
                padding: 16px;
            }
        }
    </style>

    <div class="row">
        <div class="col-lg-12 legal-stack">
            @php
                $legalLocale = $privacyLocale ?? app()->getLocale();
            @endphp
            <section class="legal-banner">
                <h2>{{ __('messages.legal.banner.title', [], $legalLocale) }}</h2>
                <p>{{ __('messages.legal.banner.subtitle', [], $legalLocale) }}</p>
            </section>

            @if(session('success'))
                <div class="alert alert-success mb-0">{{ session('success') }}</div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger mb-0">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="card rounded legal-shell">
                <div class="card-body">
                    <section class="text-gray-400">
                        <h3 class="card-header">
                            <i class="bi bi-file-text"> {{ __('messages.imprint.default_title') }}</i>
                        </h3>

                        <div class="card-body">
                            <form action="{{ route('saveLegal') }}" method="post" autocomplete="off" data-lpignore="true">
                                @csrf
                                <input type="hidden" name="page_id" value="{{ (int) ($selectedPageId ?? 0) }}" />
                                <input type="hidden" name="linkid" value="{{ (int) ($imprintLinkId ?? 0) }}" />

                                @include('imprint.form')

                                <div class="pt-4 d-flex flex-wrap gap-2">
                                    <a class="btn btn-danger" href="{{ url('studio/links') }}">{{ __('messages.Cancel') }}</a>
                                    <button type="submit" class="btn btn-primary">{{ __('messages.Save') }}</button>
                                </div>
                            </form>
                        </div>
                    </section>
                </div>
            </div>

            <div class="card rounded legal-shell">
                <div class="card-body">
                    <section class="text-gray-400">
                        <h3 class="card-header">
                            <i class="bi bi-shield-lock"> {{ __('messages.legal.privacy.section_title', [], $legalLocale) }}</i>
                        </h3>

                        <div class="card-body">
                            <div class="alert alert-warning mb-3" role="alert">
                                <strong>{{ __('messages.legal.privacy.disclaimer_title', [], $legalLocale) }}</strong>
                                {{ __('messages.legal.privacy.disclaimer_text', [], $legalLocale) }}
                            </div>

                            <form action="{{ route('savePrivacyAllLayers') }}" method="post" autocomplete="off" data-lpignore="true">
                                @csrf
                                <input type="hidden" name="page_id" value="{{ (int) ($selectedPageId ?? 0) }}" />

                                <div class="imprint-cards-stack">
                                @include('studio.legal.layer1')
                                @include('studio.legal.layer2')
                                @include('studio.legal.layer3')

                                <div class="card border imprint-card">
                                    <div class="card-body">
                                        <h5 class="mb-3">{{ __('messages.legal.privacy.live_output', [], $legalLocale) }}</h5>
                                        <p class="text-muted small mb-2">{{ __('messages.legal.privacy.preview_rule', [], $legalLocale) }}</p>
                                        <div class="privacy-preview">{{ $privacyLiveOutput ?? '' }}</div>
                                    </div>
                                </div>

                                <div class="d-flex">
                                    <button type="submit" name="layer2_action" value="save" class="btn btn-primary">{{ __('messages.legal.privacy.action.save_all', [], $legalLocale) }}</button>
                                </div>
                                </div>{{-- /imprint-cards-stack --}}
                            </form>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
