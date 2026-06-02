@php
    $selectedService = old('embed_service', $embed_service ?? 'youtube');
    $embedSourceUrl = old('embed_source_url', $embed_source_url ?? '');
    $internalTitle = old('internal_title', $internal_title ?? ($title ?? ''));

    $serviceOptions = [
        'youtube' => __('messages.smart_embed.service.youtube'),
        'instagram' => __('messages.smart_embed.service.instagram'),
        'google_maps' => __('messages.smart_embed.service.google_maps'),
        'spotify' => __('messages.smart_embed.service.spotify'),
        'calendly' => __('messages.smart_embed.service.calendly'),
        'tally' => __('messages.smart_embed.service.tally'),
        'gumroad' => __('messages.smart_embed.service.gumroad'),
        'kit' => __('messages.smart_embed.service.kit'),
        'resmio_booking' => __('messages.smart_embed.service.resmio_booking'),
        'resmio_menu' => __('messages.smart_embed.service.resmio_menu'),
    ];

    $serviceExamples = [
        'youtube' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'instagram' => 'https://www.instagram.com/p/DU6CkmOmQMv/?img_index=1',
        'google_maps' => 'https://www.google.com/maps?q=place_id:ChIJ4zGFAZpYwokRGUGph3Mf37k',
        'spotify' => 'https://open.spotify.com/track/4uLU6hMCjMI75M1A2tKUQC',
        'calendly' => 'https://calendly.com/dein-team/erstgespraech',
        'tally' => 'https://tally.so/r/w4zJ5n',
        'gumroad' => 'https://9432604211760.gumroad.com/l/demo',
        'kit' => 'https://deinaccount.kit.com/4b70f698c6',
        'resmio_booking' => 'https://app.resmio.com/testete/widget',
        'resmio_menu' => 'https://app.resmio.com/testete/menu-widget',
    ];
@endphp

<div class="p-3 rounded border bg-white">
    <label for="smart-embed-service" class="form-label">{{ __('messages.smart_embed.editor.service') }}</label>
    <select id="smart-embed-service" name="embed_service" class="form-control" required>
        @foreach($serviceOptions as $value => $label)
            <option value="{{ $value }}" @selected($selectedService === $value)>{{ $label }}</option>
        @endforeach
    </select>
    @error('embed_service')
        <small class="text-danger d-block mt-1">{{ $message }}</small>
    @enderror

    <label for="smart-embed-url" class="form-label mt-3">{{ __('messages.smart_embed.editor.external_url') }}</label>
    <input
        id="smart-embed-url"
        type="text"
        name="embed_source_url"
        value="{{ $embedSourceUrl }}"
        class="form-control"
        placeholder="https://"
        autocomplete="new-password"
        autocapitalize="off"
        autocorrect="off"
        spellcheck="false"
        aria-autocomplete="none"
        inputmode="url"
        data-lpignore="true"
        data-1p-ignore="true"
        required
        readonly
        onfocus="this.removeAttribute('readonly')"
        onpointerdown="this.removeAttribute('readonly')"
        ontouchstart="this.removeAttribute('readonly')"
        onblur="this.setAttribute('readonly','readonly')"
    />
    <small id="smart-embed-example" class="text-muted d-block mt-1"></small>
    @error('embed_source_url')
        <small class="text-danger d-block mt-1">{{ $message }}</small>
    @enderror

    <label for="smart-embed-title" class="form-label mt-3">{{ __('messages.common.internal_title') }}</label>
    <small class="text-muted d-block mb-1">
        {{ __('messages.common.internal_title_hint') }}
    </small>
    <input
        id="smart-embed-title"
        type="text"
        name="internal_title"
        value="{{ $internalTitle }}"
        class="form-control"
        maxlength="120"
        placeholder="{{ __('messages.smart_embed.editor.internal_title_placeholder') }}"
    />
    @error('internal_title')
        <small class="text-danger d-block mt-1">{{ $message }}</small>
    @enderror
</div>

<script>
(function () {
    var serviceSelect = document.getElementById('smart-embed-service');
    var exampleText = document.getElementById('smart-embed-example');
    if (!serviceSelect || !exampleText) {
        return;
    }

    var examples = @json($serviceExamples);

    function updateExample() {
        var key = serviceSelect.value;
        if (!examples[key]) {
            exampleText.textContent = '';
            return;
        }

        exampleText.textContent = @json(__('messages.common.example')) + ': ' + examples[key];
    }

    serviceSelect.addEventListener('change', updateExample);
    updateExample();
})();
</script>
