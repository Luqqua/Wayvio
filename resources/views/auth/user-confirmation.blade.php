<x-guest-layout>
    @php
        $isGerman = str_starts_with(strtolower((string) app()->getLocale()), 'de');
        $domain = str_replace(['http://', 'https://'], '', url(''));
        $adminUsersUrl = url('admin/users/all');
    @endphp

    <div class="mb-4 text-sm text-gray-600">
        @if ($isGerman)
            <h2>Ein neuer Benutzer hat sich auf {{ $domain }} registriert und wartet auf Bestätigung.</h2>
            <p>
                Der Benutzer <i>{{ $user }}</i> (<i>{{ $email }}</i>) hat ein neues Konto auf {{ url('') }} registriert und wartet auf die Freischaltung durch einen Administrator.
                Um den Benutzer zu verifizieren:
            </p>
        @else
            <h2>A new user has registered on {{ $domain }} and is awaiting verification.</h2>
            <p>
                The user <i>{{ $user }}</i> (<i>{{ $email }}</i>) has registered a new account on {{ url('') }} and is awaiting confirmation by an admin.
                Click <a href="{{ $adminUsersUrl }}">here</a> to verify the user.
            </p>
        @endif
    </div>

    <a href="{{ $adminUsersUrl }}">
        <button>{{ $isGerman ? 'Benutzer verwalten' : 'Manage users' }}</button>
    </a>
    <br><br>
    @include('emails.partials.footer')
</x-guest-layout>
