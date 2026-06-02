@php
    $blockTitle = old('title', $title ?? '');
    $uspStyle = normalizeContentBlockStyle(old('usp_style', $usp_style ?? 'glass'), 'glass');

    $uspItems = old('usp_items', $usp_items ?? []);
    if (!is_array($uspItems) || count($uspItems) === 0) {
        $uspItems = [
            ['title' => 'Fast Setup', 'description' => 'Start in minutes with a clean onboarding flow.', 'icon' => 'fa fa-bolt'],
            ['title' => 'Trusted Security', 'description' => 'Secure defaults and stable production behavior.', 'icon' => 'fa fa-shield'],
            ['title' => 'Always Available', 'description' => 'Reliable performance for your visitors.', 'icon' => 'fa fa-check-circle'],
        ];
    }

    if (count($uspItems) > 3) {
        $uspItems = array_slice($uspItems, 0, 3);
    }

    $iconOptions = [
        ['value' => 'fa fa-bolt', 'label' => 'Bolt'],
        ['value' => 'fa fa-shield', 'label' => 'Shield'],
        ['value' => 'fa fa-check-circle', 'label' => 'Check'],
        ['value' => 'fa fa-star', 'label' => 'Star'],
        ['value' => 'fa fa-heart', 'label' => 'Heart'],
        ['value' => 'fa fa-rocket', 'label' => 'Rocket'],
        ['value' => 'bi bi-lightning-charge-fill', 'label' => 'Lightning'],
        ['value' => 'bi bi-shield-check', 'label' => 'Shield Check'],
        ['value' => 'bi bi-graph-up-arrow', 'label' => 'Growth'],
        ['value' => 'bi bi-award-fill', 'label' => 'Award'],
    ];
@endphp

<div id="usp-cards-editor" class="p-3 rounded border bg-white">
    <label for="usp-cards-title" class="form-label">{{ __('messages.common.internal_title') }}</label>
    <input
        id="usp-cards-title"
        type="text"
        name="title"
        class="form-control"
        maxlength="120"
        value="{{ $blockTitle }}"
        placeholder="{{ __('messages.common.example_why_choose_us') }}"
    />
    <small class="text-muted d-block mt-1 mb-3">{{ __('messages.common.internal_title_hint') }}</small>

    <div class="row g-3 mb-3">
        <div class="col-md-8">
            <label for="usp-style" class="form-label">{{ __('messages.common.visual_style') }}</label>
            <select id="usp-style" name="usp_style" class="form-control">
                <option value="clean" @selected($uspStyle === 'clean')>Clean</option>
                <option value="glass" @selected($uspStyle === 'glass')>Glass</option>
                <option value="bold" @selected($uspStyle === 'bold')>Bold</option>
            </select>
            <small class="text-muted d-block mt-1">{{ __('messages.common.visual_style_hint') }}</small>
        </div>
    </div>

    <div class="d-flex align-items-center justify-content-between mb-2">
        <h6 class="mb-0">{{ __('messages.usp_cards.editor.cards_max_3') }} <small class="text-muted" data-usp-count></small></h6>
        <button type="button" class="btn btn-sm btn-outline-primary" data-add-usp>{{ __('messages.usp_cards.editor.add_card') }}</button>
    </div>

    <div data-usp-items-container data-next-index="{{ count($uspItems) }}">
        @foreach($uspItems as $index => $item)
            @php
                $itemTitle = trim(strip_tags((string) ($item['title'] ?? '')));
                $itemDescription = trim(strip_tags((string) ($item['description'] ?? '')));
                $itemIcon = trim((string) ($item['icon'] ?? 'fa fa-star'));
                if ($itemIcon === '') {
                    $itemIcon = 'fa fa-star';
                }
            @endphp
            <div class="border rounded p-3 mb-3" data-usp-item-row>
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <strong data-usp-item-label>{{ __('messages.usp_cards.editor.card') }} {{ $loop->iteration }}</strong>
                    <button type="button" class="btn btn-sm btn-light text-danger" data-remove-usp>{{ __('messages.common.remove') }}</button>
                </div>

                <label class="form-label" for="usp-item-title-{{ $index }}">{{ __('messages.common.title') }}</label>
                <input
                    id="usp-item-title-{{ $index }}"
                    type="text"
                    name="usp_items[{{ $index }}][title]"
                    class="form-control"
                    maxlength="80"
                    value="{{ $itemTitle }}"
                    placeholder="{{ __('messages.common.example_fast_launch') }}"
                />

                <label class="form-label mt-2" for="usp-item-description-{{ $index }}">{{ __('messages.hub_contact_form.editor.description') }}</label>
                <textarea
                    id="usp-item-description-{{ $index }}"
                    name="usp_items[{{ $index }}][description]"
                    class="form-control"
                    rows="2"
                    maxlength="220"
                    placeholder="{{ __('messages.usp_cards.editor.benefit_hint') }}"
                >{{ $itemDescription }}</textarea>

                <label class="form-label mt-2" for="usp-item-icon-{{ $index }}">{{ __('messages.common.icon') }}</label>
                <select id="usp-item-icon-{{ $index }}" name="usp_items[{{ $index }}][icon]" class="form-control">
                    @foreach($iconOptions as $icon)
                        <option value="{{ $icon['value'] }}" @selected($itemIcon === $icon['value'])>{{ $icon['label'] }}</option>
                    @endforeach
                </select>
            </div>
        @endforeach
    </div>

    @if($errors->has('usp_items') || $errors->has('usp_items.*.title') || $errors->has('usp_style'))
        <small class="text-danger d-block mt-1">
            {{ $errors->first('usp_items') ?: $errors->first('usp_items.*.title') ?: $errors->first('usp_style') }}
        </small>
    @endif
</div>

<script>
(function () {
    var editor = document.getElementById('usp-cards-editor');
    if (!editor) {
        return;
    }

    var maxItems = 3;
    var container = editor.querySelector('[data-usp-items-container]');
    var addButton = editor.querySelector('[data-add-usp]');
    var countLabel = editor.querySelector('[data-usp-count]');
    if (!container || !addButton || !countLabel) {
        return;
    }

    var iconOptions = [
        { value: 'fa fa-bolt', label: 'Bolt' },
        { value: 'fa fa-shield', label: 'Shield' },
        { value: 'fa fa-check-circle', label: 'Check' },
        { value: 'fa fa-star', label: 'Star' },
        { value: 'fa fa-heart', label: 'Heart' },
        { value: 'fa fa-rocket', label: 'Rocket' },
        { value: 'bi bi-lightning-charge-fill', label: 'Lightning' },
        { value: 'bi bi-shield-check', label: 'Shield Check' },
        { value: 'bi bi-graph-up-arrow', label: 'Growth' },
        { value: 'bi bi-award-fill', label: 'Award' }
    ];

    var nextIndex = Number(container.getAttribute('data-next-index') || 0);

    function rowCount() {
        return container.querySelectorAll('[data-usp-item-row]').length;
    }

    function iconOptionsMarkup() {
        return iconOptions.map(function (icon) {
            return '<option value="' + icon.value + '">' + icon.label + '</option>';
        }).join('');
    }

    function updateState() {
        var rows = container.querySelectorAll('[data-usp-item-row]');
        rows.forEach(function (row, index) {
            var label = row.querySelector('[data-usp-item-label]');
            if (label) {
                label.textContent = @json(__('messages.usp_cards.editor.card')) + ' ' + (index + 1);
            }
        });

        countLabel.textContent = '(' + rows.length + '/' + maxItems + ')';
        addButton.disabled = rows.length >= maxItems;
    }

    function createRow(index) {
        var row = document.createElement('div');
        row.className = 'border rounded p-3 mb-3';
        row.setAttribute('data-usp-item-row', '');
        row.innerHTML = '' +
            '<div class="d-flex align-items-center justify-content-between mb-2">' +
                '<strong data-usp-item-label>' + @json(__('messages.usp_cards.editor.card')) + '</strong>' +
                '<button type="button" class="btn btn-sm btn-light text-danger" data-remove-usp>' + @json(__('messages.common.remove')) + '</button>' +
            '</div>' +
            '<label class="form-label" for="usp-item-title-' + index + '">' + @json(__('messages.common.title')) + '</label>' +
            '<input id="usp-item-title-' + index + '" type="text" name="usp_items[' + index + '][title]" class="form-control" maxlength="80" placeholder="' + @json(__('messages.common.example_fast_launch')) + '">' +
            '<label class="form-label mt-2" for="usp-item-description-' + index + '">' + @json(__('messages.hub_contact_form.editor.description')) + '</label>' +
            '<textarea id="usp-item-description-' + index + '" name="usp_items[' + index + '][description]" class="form-control" rows="2" maxlength="220" placeholder="' + @json(__('messages.usp_cards.editor.benefit_hint')) + '"></textarea>' +
            '<label class="form-label mt-2" for="usp-item-icon-' + index + '">' + @json(__('messages.common.icon')) + '</label>' +
            '<select id="usp-item-icon-' + index + '" name="usp_items[' + index + '][icon]" class="form-control">' + iconOptionsMarkup() + '</select>';

        return row;
    }

    addButton.addEventListener('click', function () {
        if (rowCount() >= maxItems) {
            return;
        }

        container.appendChild(createRow(nextIndex));
        nextIndex += 1;
        updateState();
    });

    container.addEventListener('click', function (event) {
        var removeButton = event.target.closest('[data-remove-usp]');
        if (!removeButton) {
            return;
        }

        var row = removeButton.closest('[data-usp-item-row]');
        if (!row) {
            return;
        }

        row.remove();

        if (rowCount() === 0) {
            container.appendChild(createRow(nextIndex));
            nextIndex += 1;
        }

        updateState();
    });

    updateState();
})();
</script>
