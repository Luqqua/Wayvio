@extends('wayvio.layout')

@section('content')
    @push('wayvio-head')
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $imprint_title }} - {{ $userinfo->name }}</title>
        @include('wayvio.modules.favicon')
        @include('wayvio.modules.assets')
    @endpush

    @push('wayvio-head-end')
        @include('wayvio.modules.theme')
    @endpush

    @push('wayvio-content')
        @php
            $normalizeCountry = static function ($country) {
                $value = trim((string) $country);
                if ($value === '') {
                    return '';
                }

                $lower = function_exists('mb_strtolower')
                    ? mb_strtolower($value, 'UTF-8')
                    : strtolower($value);

                if (in_array($lower, ['germany', 'deutschland', 'de', 'deu'], true)) {
                    return __('messages.imprint.country_germany');
                }

                return function_exists('mb_convert_case')
                    ? mb_convert_case($lower, MB_CASE_TITLE, 'UTF-8')
                    : ucfirst($lower);
            };

            $displayCountry = $normalizeCountry($imprint_country ?? '');
            $hasLegalGroup = !empty($imprint_vat_id)
                || !empty($imprint_business_id)
                || !empty($imprint_register_name)
                || !empty($imprint_register_court)
                || !empty($imprint_register_number)
                || !empty($imprint_supervisory_authority)
                || !empty($imprint_chamber)
                || !empty($imprint_professional_title)
                || !empty($imprint_professional_state)
                || !empty($imprint_professional_rules)
                || !empty($imprint_liquidation_notice)
                || !empty($imprint_additional_text);
        @endphp

        <div class="imprint-shell">
            <h1 class="imprint-title">{{ $imprint_title }}</h1>
            <p class="imprint-intro">{{ __('messages.imprint.intro_tmg') }}</p>

            <section class="imprint-group">
                <h2 class="imprint-group-title">{{ __('messages.imprint.group.provider') }}</h2>
                <address class="imprint-address">{{ $imprint_name }}@if(!empty($imprint_legal_form)) ({{ $imprint_legal_form }})@endif
                    @if(!empty($imprint_represented_by))
                    <br>{!! nl2br(e(__('messages.imprint.represented_by') . ': ' . $imprint_represented_by)) !!}
                    @endif
                    <br>{{ $imprint_street }}
                    <br>{{ trim($imprint_postal_code . ' ' . $imprint_city) }}
                    @if($displayCountry !== '')
                    <br>{{ $displayCountry }}
                    @endif
                </address>
            </section>

            <hr class="imprint-divider">

            <section class="imprint-group">
                <h2 class="imprint-group-title">{{ __('messages.imprint.group.contact') }}</h2>
                <div class="imprint-item"><a class="imprint-link" href="mailto:{{ $imprint_email }}">{{ $imprint_email }}</a></div>
                @if(!empty($imprint_phone))
                <div class="imprint-item">{{ $imprint_phone }}</div>
                @endif
                @if(!empty($imprint_fax))
                <div class="imprint-item">{{ $imprint_fax }}</div>
                @endif
                @if(!empty($imprint_contact_form_enabled))
                <div class="imprint-contact-form">
                    <x-forms.embed form-key="imprint_contact" :hub="$userinfo" context="imprint" />
                </div>
                @endif
            </section>

            @if($hasLegalGroup)
            <hr class="imprint-divider">

            <section class="imprint-group">
                <h2 class="imprint-group-title">{{ __('messages.imprint.group.legal') }}</h2>
                @if(!empty($imprint_vat_id))
                <div class="imprint-item">{{ __('messages.imprint.vat_id') }}: {{ $imprint_vat_id }}</div>
                @endif
                @if(!empty($imprint_business_id))
                <div class="imprint-item">{{ __('messages.imprint.business_id') }}: {{ $imprint_business_id }}</div>
                @endif
                @if(!empty($imprint_register_name) || !empty($imprint_register_court) || !empty($imprint_register_number))
                <div class="imprint-item">
                    @if(!empty($imprint_register_name))
                        {{ __('messages.imprint.register') }}: {{ $imprint_register_name }}<br>
                    @endif
                    @if(!empty($imprint_register_court))
                        {{ __('messages.imprint.register_court') }}: {{ $imprint_register_court }}<br>
                    @endif
                    @if(!empty($imprint_register_number))
                        {{ __('messages.imprint.register_number') }}: {{ $imprint_register_number }}
                    @endif
                </div>
                @endif
                @if(!empty($imprint_supervisory_authority))
                <div class="imprint-item">{!! nl2br(e(__('messages.imprint.supervisory_authority') . ': ' . $imprint_supervisory_authority)) !!}</div>
                @endif
                @if(!empty($imprint_chamber) || !empty($imprint_professional_title) || !empty($imprint_professional_state) || !empty($imprint_professional_rules))
                <div class="imprint-item">
                    @if(!empty($imprint_chamber))
                        {{ __('messages.imprint.chamber') }}: {{ $imprint_chamber }}<br>
                    @endif
                    @if(!empty($imprint_professional_title))
                        {{ __('messages.imprint.professional_title') }}: {{ $imprint_professional_title }}<br>
                    @endif
                    @if(!empty($imprint_professional_state))
                        {{ __('messages.imprint.professional_state') }}: {{ $imprint_professional_state }}<br>
                    @endif
                    @if(!empty($imprint_professional_rules))
                        {{ __('messages.imprint.professional_rules') }}: {!! nl2br(e($imprint_professional_rules)) !!}
                    @endif
                </div>
                @endif
                @if(!empty($imprint_liquidation_notice))
                <div class="imprint-item">{!! nl2br(e(__('messages.imprint.liquidation') . ': ' . $imprint_liquidation_notice)) !!}</div>
                @endif
                @if(!empty($imprint_additional_text))
                <div class="imprint-item">{!! nl2br(e($imprint_additional_text)) !!}</div>
                @endif
            </section>
            @endif

            @php
                $domainResolver = app(\App\Services\Domains\DomainUrlResolver::class);
                $profileOwner = $domainResolver->ownerForPageUser($userinfo);
                $profileUrl = $domainResolver->profileUrlForEditor($profileOwner, $userinfo);
            @endphp
            <a class="imprint-back" href="{{ $profileUrl }}">{{ __('messages.imprint.back_to_page') }}</a>
        </div>

        <style>
            .imprint-shell {
                max-width: 640px;
                margin: 40px auto;
                padding: 2rem;
                background: #ffffff;
                border-radius: 16px;
                box-shadow: 0 2px 20px rgba(0, 0, 0, 0.10);
                box-sizing: border-box;
                text-align: left;
            }

            .imprint-title {
                color: #111827;
                font-size: 1.5rem;
                font-weight: 700;
                margin: 0 0 0.25rem;
            }

            .imprint-intro {
                color: #6b7280;
                font-size: 0.875rem;
                line-height: 1.5;
                margin: 0 0 2rem;
            }

            .imprint-group-title {
                color: #111827;
                font-size: 1rem;
                font-weight: 600;
                margin: 0 0 0.75rem;
            }

            .imprint-address {
                font-style: normal;
                font-size: 0.9375rem;
                line-height: 1.6;
                color: #111827;
                word-break: break-word;
                margin: 0;
            }

            .imprint-item {
                color: #111827;
                font-size: 0.9375rem;
                line-height: 1.65;
                font-weight: 400;
                word-break: break-word;
                margin: 0;
                padding: 1px 0;
            }

            .imprint-divider {
                border: 0;
                border-top: 1px solid #e5e7eb;
                margin: 1.5rem 0;
            }

            .imprint-link {
                font-weight: 400;
                text-decoration: underline;
            }

            .imprint-contact-form .wayvio-form-embed {
                margin: 1.25rem 0 0;
                width: 100%;
            }

            .imprint-contact-form .wayvio-form-title {
                font-size: 1rem;
                font-weight: 600;
            }

            .imprint-contact-form .wayvio-form-row label,
            .imprint-contact-form .wayvio-form-consent,
            .imprint-contact-form .wayvio-form-row input,
            .imprint-contact-form .wayvio-form-row textarea {
                font-size: 0.9375rem;
            }

            .imprint-back {
                display: inline-block;
                margin-top: 2rem;
                padding: 0;
                border: 0;
                color: #6b7280;
                text-decoration: none;
                font-size: 0.875rem;
                line-height: 1.5;
            }

            .imprint-back:hover {
                text-decoration: underline;
            }

            .imprint-back:focus {
                outline: 2px solid rgba(0, 0, 0, 0.35);
                outline-offset: 2px;
            }

            @media (max-width: 640px) {
                .imprint-shell {
                    margin: 24px 16px;
                    padding: 1.25rem 1rem;
                    border-radius: 12px;
                }
            }
        </style>

        @include('wayvio.modules.footer', ['info' => $information->first()])
    @endpush
@endsection
