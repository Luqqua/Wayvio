@php
    $requiredTypes = is_array($requiredAgreementTypes ?? null) ? $requiredAgreementTypes : [];
    $snapshots = is_array($agreementSnapshots ?? null) ? $agreementSnapshots : [];
@endphp

<x-guest-layout>
    <x-auth-card>
        <x-slot name="logo"></x-slot>

        <x-auth-validation-errors class="mb-4 alert alert-danger" role="alert" :errors="$errors" />

        <div class="container py-2 w-100">
            <div class="card p-4">
                <h1 class="mb-3 text-center">Vereinbarungen bestaetigen</h1>
                <p class="text-muted mb-4 text-center">Bitte akzeptiere die erforderlichen Vereinbarungen, bevor du fortfaehrst.</p>

                <form method="POST" action="{{ route('tos.accept.store') }}">
                    @csrf

                    @foreach (['agb', 'avv'] as $type)
                        @php
                            $snapshot = is_array($snapshots[$type] ?? null) ? $snapshots[$type] : [];
                            $label = (string) ($snapshot['label'] ?? strtoupper($type));
                            $url = (string) ($snapshot['url'] ?? url('/pages/' . $type));
                            $version = (string) ($snapshot['version'] ?? 'unknown');
                            $required = in_array($type, $requiredTypes, true);
                        @endphp

                        <div class="form-check mb-2">
                            <input
                                type="checkbox"
                                class="form-check-input"
                                name="accept_{{ $type }}"
                                id="accept_{{ $type }}"
                                value="1"
                                {{ $required ? 'required' : '' }}
                            >
                            <label class="form-check-label" for="accept_{{ $type }}">
                                Ich akzeptiere {{ $label }}
                                <a href="{{ $url }}" target="_blank" rel="noopener">(Version {{ $version }})</a>.
                            </label>
                        </div>
                    @endforeach

                    <div class="d-flex justify-content-center mt-4">
                        <button id="submit-btn" type="submit" class="btn btn-primary">Weiter</button>
                    </div>
                </form>
            </div>
        </div>
    </x-auth-card>
</x-guest-layout>
