<x-guest-layout>
@include('layouts.lang')

    <x-auth-card>
        <x-slot name="logo"></x-slot>

        <x-auth-session-status class="mb-4" :status="session('status')" />
        <x-auth-validation-errors class="mb-4" :errors="$errors" />

        <div style="max-width:480px" class="container mt-5 w-100">
            <div class="card p-5">
                <h2 class="mb-2 text-center">{{__('messages.Two-factor authentication')}}</h2>
                <p class="text-center">{{__('messages.Enter the code from your authenticator app or a recovery code')}}.</p>

                <form method="POST" action="{{ route('two-factor.challenge.store') }}">
                    @csrf
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="form-group">
                                <label for="code" class="form-label">{{__('messages.Authentication code')}}</label>
                                <input type="text" class="form-control" id="code" name="code" autocapitalize="none" autocomplete="one-time-code" aria-describedby="code" placeholder="123456 or recovery code" required autofocus>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-center">
                        <button type="submit" class="btn btn-primary w-100">{{__('messages.Verify')}}</button>
                    </div>
                </form>
            </div>
        </div>
    </x-auth-card>
</x-guest-layout>
