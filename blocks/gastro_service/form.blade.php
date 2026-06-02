@php
    $blockTitle = old('title', $title ?? '');
    $gastroStyle = normalizeContentBlockStyle(old('gastro_style', $gastro_style ?? 'clean'), 'clean');
    $itemsFromData = old('gastro_items', $gastro_items ?? []);
    $badgeOptions = [
        'vegan' => __('messages.gastro_service.badge.vegan'),
        'organic' => __('messages.gastro_service.badge.organic'),
        'spicy' => __('messages.gastro_service.badge.spicy'),
        'new' => __('messages.gastro_service.badge.new'),
    ];
    $badgeOptionsForJs = [];
    foreach ($badgeOptions as $badgeKey => $badgeLabel) {
        $badgeOptionsForJs[] = [
            'key' => (string) $badgeKey,
            'label' => (string) $badgeLabel,
        ];
    }
    $legacyBadgeMap = [
        'vegan' => 'vegan',
        'bio' => 'organic',
        'organic' => 'organic',
        'scharf' => 'spicy',
        'spicy' => 'spicy',
        'neu' => 'new',
        'new' => 'new',
    ];

    if (!is_array($itemsFromData) || count($itemsFromData) === 0) {
        $itemsFromData = [
            ['title' => '', 'description' => '', 'price' => '', 'badges' => []],
        ];
    }
    $errorsBag = $errors ?? new \Illuminate\Support\ViewErrorBag();
@endphp

<style>
    #gastro-items-editor .form-label {
        margin-bottom: 0.35rem;
    }

    #gastro-items-editor [data-item-row] {
        padding: 1rem !important;
        margin-bottom: 1rem !important;
    }
</style>

<div id="gastro-items-editor" class="p-3 rounded border bg-white">
    <label for="gastro-block-title" class="form-label">{{ __('messages.common.internal_title') }}</label>
    <input
        id="gastro-block-title"
        type="text"
        name="title"
        class="form-control"
        maxlength="120"
        value="{{ $blockTitle }}"
        placeholder="{{ __('messages.gastro_service.editor.internal_title_placeholder') }}"
    />
    <small class="text-muted d-block mt-1 mb-3">{{ __('messages.common.internal_title_hint') }}</small>

    <div class="row g-3 mb-3">
        <div class="col-md-8">
            <label for="gastro-style" class="form-label">{{ __('messages.common.visual_style') }}</label>
            <input type="hidden" id="gastro-style-hidden" name="gastro_style" value="{{ $gastroStyle }}">
            <select id="gastro-style" name="gastro_style_ui" class="form-control">
                <option value="clean" @selected($gastroStyle === 'clean')>Clean</option>
                <option value="glass" @selected($gastroStyle === 'glass')>Glass</option>
                <option value="bold" @selected($gastroStyle === 'bold')>Bold</option>
            </select>
            <small class="text-muted d-block mt-1">{{ __('messages.common.visual_style_hint') }}</small>
        </div>
    </div>

    <div class="d-flex align-items-center justify-content-between mb-2">
        <h6 class="mb-0">{{ __('messages.gastro_service.editor.items') }}</h6>
        <button type="button" class="btn btn-sm btn-outline-primary" data-add-item>{{ __('messages.gastro_service.editor.add_item') }}</button>
    </div>

    <div data-items-container data-next-index="{{ count($itemsFromData) }}">
        @foreach($itemsFromData as $index => $item)
            @php
                $titleValue = trim((string) ($item['title'] ?? ''));
                $descriptionValue = trim((string) ($item['description'] ?? ''));
                $priceValue = trim((string) ($item['price'] ?? ''));
                $rawBadges = is_array($item['badges'] ?? null) ? $item['badges'] : [];
                $badges = [];
                foreach ($rawBadges as $rawBadge) {
                    $normalized = strtolower(trim((string) $rawBadge));
                    $mapped = $legacyBadgeMap[$normalized] ?? null;
                    if ($mapped && !in_array($mapped, $badges, true)) {
                        $badges[] = $mapped;
                    }
                }
            @endphp

            <div class="border rounded p-3 mb-3" data-item-row>
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <strong data-item-label>{{ __('messages.gastro_service.editor.item') }} {{ $loop->iteration }}</strong>
                    <button type="button" class="btn btn-sm btn-light text-danger" data-remove-item>{{ __('messages.common.remove') }}</button>
                </div>

                <label class="form-label" for="gastro-item-title-{{ $index }}">{{ __('messages.common.title') }}</label>
                <input id="gastro-item-title-{{ $index }}" type="text" name="gastro_items[{{ $index }}][title]" class="form-control" maxlength="120" value="{{ $titleValue }}" placeholder="{{ __('messages.common.example_cappuccino') }}" />

                <label class="form-label mt-2" for="gastro-item-description-{{ $index }}">{{ __('messages.common.description_optional') }}</label>
                <textarea id="gastro-item-description-{{ $index }}" name="gastro_items[{{ $index }}][description]" class="form-control" rows="2" maxlength="500" placeholder="{{ __('messages.gastro_service.editor.description_placeholder') }}">{{ $descriptionValue }}</textarea>

                <label class="form-label mt-2" for="gastro-item-price-{{ $index }}">{{ __('messages.gastro_service.editor.price_optional') }}</label>
                <small class="text-muted d-block mb-1">{{ __('messages.gastro_service.editor.price_hint') }}</small>
                <input id="gastro-item-price-{{ $index }}" type="text" name="gastro_items[{{ $index }}][price]" class="form-control" maxlength="32" inputmode="text" value="{{ $priceValue }}" placeholder="{{ __('messages.gastro_service.editor.price_placeholder') }}" />

                <div class="mt-2">
                    <label class="form-label d-block mb-1">{{ __('messages.gastro_service.editor.badges') }}</label>
                    <div class="d-flex flex-wrap" style="gap: 6px 10px;">
                        @foreach($badgeOptions as $badgeKey => $badgeLabel)
                            @php $checkboxId = 'gastro-badge-' . $index . '-' . $badgeKey; @endphp
                            <div class="custom-control custom-checkbox">
                                <input
                                    id="{{ $checkboxId }}"
                                    type="checkbox"
                                    class="custom-control-input"
                                    name="gastro_items[{{ $index }}][badges][]"
                                    value="{{ $badgeKey }}"
                                    @checked(in_array($badgeKey, $badges, true))
                                >
                                <label class="custom-control-label" for="{{ $checkboxId }}">{{ $badgeLabel }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if($errorsBag->has('gastro_style') || $errorsBag->has('gastro_items') || $errorsBag->has('gastro_items.*.title') || $errorsBag->has('gastro_items.*.badges.*'))
        <small class="text-danger d-block mt-1">
            {{ $errorsBag->first('gastro_style') ?: $errorsBag->first('gastro_items') ?: $errorsBag->first('gastro_items.*.title') ?: $errorsBag->first('gastro_items.*.badges.*') }}
        </small>
    @endif
</div>

<script>
(function () {
    var editor = document.getElementById('gastro-items-editor');
    if (!editor) {
        return;
    }

    var container = editor.querySelector('[data-items-container]');
    var addButton = editor.querySelector('[data-add-item]');
    var styleSelect = editor.querySelector('#gastro-style');
    var styleHiddenInput = editor.querySelector('#gastro-style-hidden');
    if (!container || !addButton) {
        return;
    }

    if (styleSelect && styleHiddenInput) {
        styleHiddenInput.value = styleSelect.value || styleHiddenInput.value || 'clean';
        styleSelect.addEventListener('change', function () {
            styleHiddenInput.value = styleSelect.value || 'clean';
        });
    }

    var badgeOptions = @json($badgeOptionsForJs);
    var nextIndex = Number(container.getAttribute('data-next-index') || 0);

    function updateLabels() {
        var rows = container.querySelectorAll('[data-item-row]');
        rows.forEach(function (row, rowIndex) {
            var label = row.querySelector('[data-item-label]');
            if (label) {
                label.textContent = @json(__('messages.gastro_service.editor.item')) + ' ' + (rowIndex + 1);
            }
        });
    }

    function badgeMarkup(index) {
        return badgeOptions.map(function (badge) {
            var id = 'gastro-badge-' + index + '-' + badge.key;
            return '' +
                '<div class="custom-control custom-checkbox">' +
                    '<input id="' + id + '" type="checkbox" class="custom-control-input" name="gastro_items[' + index + '][badges][]" value="' + badge.key + '">' +
                    '<label class="custom-control-label" for="' + id + '">' + badge.label + '</label>' +
                '</div>';
        }).join('');
    }

    function createRow(index) {
        var wrapper = document.createElement('div');
        wrapper.className = 'border rounded p-3 mb-3';
        wrapper.setAttribute('data-item-row', '');
        wrapper.innerHTML = '' +
            '<div class="d-flex align-items-center justify-content-between mb-2">' +
                '<strong data-item-label>' + @json(__('messages.gastro_service.editor.item')) + '</strong>' +
                '<button type="button" class="btn btn-sm btn-light text-danger" data-remove-item>' + @json(__('messages.common.remove')) + '</button>' +
            '</div>' +
            '<label class="form-label" for="gastro-item-title-' + index + '">' + @json(__('messages.common.title')) + '</label>' +
            '<input id="gastro-item-title-' + index + '" type="text" name="gastro_items[' + index + '][title]" class="form-control" maxlength="120" placeholder="' + @json(__('messages.common.example_cappuccino')) + '">' +
            '<label class="form-label mt-2" for="gastro-item-description-' + index + '">' + @json(__('messages.common.description_optional')) + '</label>' +
            '<textarea id="gastro-item-description-' + index + '" name="gastro_items[' + index + '][description]" class="form-control" rows="2" maxlength="500" placeholder="' + @json(__('messages.gastro_service.editor.description_placeholder')) + '"></textarea>' +
            '<label class="form-label mt-2" for="gastro-item-price-' + index + '">' + @json(__('messages.gastro_service.editor.price_optional')) + '</label>' +
            '<small class="text-muted d-block mb-1">' + @json(__('messages.gastro_service.editor.price_hint')) + '</small>' +
            '<input id="gastro-item-price-' + index + '" type="text" name="gastro_items[' + index + '][price]" class="form-control" maxlength="32" inputmode="text" placeholder="' + @json(__('messages.gastro_service.editor.price_placeholder')) + '">' +
            '<div class="mt-2">' +
                '<label class="form-label d-block mb-1">' + @json(__('messages.gastro_service.editor.badges')) + '</label>' +
                '<div class="d-flex flex-wrap" style="gap: 6px 10px;">' + badgeMarkup(index) + '</div>' +
            '</div>';

        return wrapper;
    }

    addButton.addEventListener('click', function () {
        container.appendChild(createRow(nextIndex));
        nextIndex += 1;
        updateLabels();
    });

    container.addEventListener('click', function (event) {
        var removeButton = event.target.closest('[data-remove-item]');
        if (!removeButton) {
            return;
        }

        var row = removeButton.closest('[data-item-row]');
        if (!row) {
            return;
        }

        row.remove();
        if (!container.querySelector('[data-item-row]')) {
            container.appendChild(createRow(nextIndex));
            nextIndex += 1;
        }

        updateLabels();
    });

    updateLabels();
})();
</script>
