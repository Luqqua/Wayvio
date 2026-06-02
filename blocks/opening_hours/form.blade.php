@php
    $blockTitle = old('title', $title ?? '');

    $dayOrder = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    $dayLabels = [
        'monday' => __('messages.opening_hours.day.monday'),
        'tuesday' => __('messages.opening_hours.day.tuesday'),
        'wednesday' => __('messages.opening_hours.day.wednesday'),
        'thursday' => __('messages.opening_hours.day.thursday'),
        'friday' => __('messages.opening_hours.day.friday'),
        'saturday' => __('messages.opening_hours.day.saturday'),
        'sunday' => __('messages.opening_hours.day.sunday'),
    ];

    $defaults = [
        'locale' => app()->getLocale(),
        'group_days' => true,
        'preset' => 'clean',
        'days' => [
            'monday' => ['open' => true, 'slots' => [['09:00', '18:00']]],
            'tuesday' => ['open' => true, 'slots' => [['09:00', '12:00'], ['14:00', '18:00']]],
            'wednesday' => ['open' => false, 'slots' => []],
            'thursday' => ['open' => true, 'slots' => [['09:00', '18:00']]],
            'friday' => ['open' => true, 'slots' => [['09:00', '18:00']]],
            'saturday' => ['open' => true, 'slots' => [['10:00', '14:00']]],
            'sunday' => ['open' => false, 'slots' => []],
        ],
    ];

    $toBool = static function ($value, bool $default = false): bool {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return ((int) $value) === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'on', 'yes'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'off', 'no', ''], true)) {
                return false;
            }
        }

        return $default;
    };

    $resolveLocale = static function ($value, ?string $default = null): string {
        $supported = array_values(array_filter((array) config('app.supported_locales', []), 'strlen'));
        $fallback = (string) config('app.fallback_locale', 'en');
        $defaultLocale = $default ?: app()->getLocale();
        $defaultLocale = is_string($defaultLocale) && trim($defaultLocale) !== '' ? trim($defaultLocale) : $fallback;

        $supportedMap = [];
        foreach ($supported as $locale) {
            $supportedMap[strtolower((string) $locale)] = (string) $locale;
        }

        $normalize = static function ($candidate) use ($supportedMap): ?string {
            if (!is_string($candidate) && !is_numeric($candidate)) {
                return null;
            }

            $normalized = strtolower(trim((string) $candidate));
            if ($normalized === '') {
                return null;
            }

            if (isset($supportedMap[$normalized])) {
                return $supportedMap[$normalized];
            }

            $base = strtolower((string) strtok($normalized, '-'));
            if ($base !== '' && isset($supportedMap[$base])) {
                return $supportedMap[$base];
            }

            return null;
        };

        return $normalize($value)
            ?? $normalize($defaultLocale)
            ?? $fallback;
    };

    $rawOpeningHours = old('opening_hours', $opening_hours ?? []);
    if (!is_array($rawOpeningHours)) {
        $rawOpeningHours = [];
    }

    $openingHoursConfig = [
        'locale' => $resolveLocale($rawOpeningHours['locale'] ?? null, (string) ($defaults['locale'] ?? app()->getLocale())),
        'group_days' => $toBool($rawOpeningHours['group_days'] ?? $defaults['group_days'], true),
        'preset' => normalizeContentBlockStyle($rawOpeningHours['preset'] ?? $defaults['preset'], 'clean'),
        'days' => [],
    ];

    $rawDays = is_array($rawOpeningHours['days'] ?? null) ? $rawOpeningHours['days'] : [];
    foreach ($dayOrder as $dayKey) {
        $defaultDay = $defaults['days'][$dayKey];
        $dayInput = is_array($rawDays[$dayKey] ?? null) ? $rawDays[$dayKey] : $defaultDay;

        $open = $toBool($dayInput['open'] ?? $defaultDay['open'], $defaultDay['open']);
        $rawSlots = is_array($dayInput['slots'] ?? null) ? $dayInput['slots'] : $defaultDay['slots'];
        $slotValues = [];

        foreach ($rawSlots as $slot) {
            if (count($slotValues) >= 2 || !is_array($slot)) {
                continue;
            }

            $from = trim((string) ($slot['from'] ?? ($slot[0] ?? '')));
            $to = trim((string) ($slot['to'] ?? ($slot[1] ?? '')));
            if ($from === '' && $to === '' && count($slotValues) > 0) {
                continue;
            }

            $slotValues[] = [$from, $to];
        }

        if (count($slotValues) === 0) {
            $slotValues[] = ['', ''];
        }

        $slotOne = $slotValues[0] ?? ['', ''];
        $slotTwo = $slotValues[1] ?? ['', ''];
        $slotTwoActive = $slotTwo[0] !== '' || $slotTwo[1] !== '';

        if (!$open) {
            $slotOne = ['', ''];
            $slotTwo = ['', ''];
            $slotTwoActive = false;
        }

        $openingHoursConfig['days'][$dayKey] = [
            'open' => $open,
            'slot_one' => $slotOne,
            'slot_two' => $slotTwo,
            'slot_two_active' => $slotTwoActive,
        ];
    }

    $editorLabels = [
        'invalid_time' => __('messages.opening_hours.editor.time_validation'),
        'invalid_range' => __('messages.opening_hours.editor.range_validation'),
        'slot_required' => __('messages.opening_hours.editor.open_day_requires_slot'),
    ];
@endphp

<div id="opening-hours-editor" class="p-3 rounded border bg-white">
    <input type="hidden" name="opening_hours[locale]" value="{{ $openingHoursConfig['locale'] }}">

    <label for="opening-hours-title" class="form-label">{{ __('messages.opening_hours.editor.internal_title') }}</label>
    <input
        id="opening-hours-title"
        type="text"
        name="title"
        class="form-control"
        maxlength="120"
        value="{{ $blockTitle }}"
        placeholder="{{ __('messages.opening_hours.editor.internal_title_placeholder') }}"
    />
    <small class="text-muted d-block mt-1 mb-3">{{ __('messages.opening_hours.editor.internal_title_hint') }}</small>

    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="opening-hours-inline-toggle mt-1">
                <input type="hidden" name="opening_hours[group_days]" value="0">
                <input
                    id="opening-hours-group-days"
                    type="checkbox"
                    class="opening-hours-inline-toggle__input"
                    name="opening_hours[group_days]"
                    value="1"
                    @checked($openingHoursConfig['group_days'])
                >
                <label class="opening-hours-inline-toggle__label mb-0" for="opening-hours-group-days">{{ __('messages.opening_hours.editor.group_days') }}</label>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-8">
            <label for="opening-hours-preset" class="form-label">{{ __('messages.opening_hours.editor.visual_style') }}</label>
            <select id="opening-hours-preset" name="opening_hours[preset]" class="form-control">
                <option value="clean" @selected($openingHoursConfig['preset'] === 'clean')>Clean</option>
                <option value="glass" @selected($openingHoursConfig['preset'] === 'glass')>Glass</option>
                <option value="bold" @selected($openingHoursConfig['preset'] === 'bold')>Bold</option>
            </select>
            <small class="text-muted d-block mt-1">{{ __('messages.opening_hours.editor.visual_style_hint') }}</small>
        </div>
    </div>

    <h6 class="mb-3">{{ __('messages.opening_hours.editor.days_heading') }}</h6>

    @foreach($dayOrder as $dayKey)
        @php
            $dayData = $openingHoursConfig['days'][$dayKey];
            $slotOne = $dayData['slot_one'];
            $slotTwo = $dayData['slot_two'];
            $slotTwoVisible = $dayData['slot_two_active'];
            $dayRowClasses = 'border rounded p-3 mb-3 opening-hours-day-row';
            if (!$dayData['open']) {
                $dayRowClasses .= ' opening-hours-day-row--closed';
            }
        @endphp

        <div class="{{ $dayRowClasses }}" data-day-row>
            <div class="d-flex align-items-center justify-content-between flex-wrap" style="gap: 8px 12px;">
                <div>
                    <strong>{{ $dayLabels[$dayKey] }}</strong>
                </div>

                <div class="custom-control custom-switch">
                    <input type="hidden" name="opening_hours[days][{{ $dayKey }}][open]" value="0">
                    <input
                        id="opening-hours-open-{{ $dayKey }}"
                        type="checkbox"
                        class="custom-control-input"
                        name="opening_hours[days][{{ $dayKey }}][open]"
                        value="1"
                        data-open-toggle
                        @checked($dayData['open'])
                    >
                    <label class="custom-control-label" for="opening-hours-open-{{ $dayKey }}">
                        {{ $dayData['open'] ? __('messages.opening_hours.editor.open') : __('messages.opening_hours.editor.closed') }}
                    </label>
                </div>
            </div>

            <div class="mt-3" data-day-settings @if(!$dayData['open']) style="display:none" @endif>
                <div data-slot-section>
                    <div class="row g-2 align-items-end mb-2" data-slot-row="0">
                        <div class="col-6">
                            <label class="form-label" for="opening-hours-{{ $dayKey }}-slot-0-from">{{ __('messages.opening_hours.editor.from') }}</label>
                            <input
                                id="opening-hours-{{ $dayKey }}-slot-0-from"
                                type="text"
                                class="form-control"
                                name="opening_hours[days][{{ $dayKey }}][slots][0][from]"
                                value="{{ $slotOne[0] }}"
                                placeholder="09:00"
                                maxlength="5"
                                inputmode="numeric"
                                autocomplete="off"
                                data-slot-from="0"
                            >
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="opening-hours-{{ $dayKey }}-slot-0-to">{{ __('messages.opening_hours.editor.to') }}</label>
                            <input
                                id="opening-hours-{{ $dayKey }}-slot-0-to"
                                type="text"
                                class="form-control"
                                name="opening_hours[days][{{ $dayKey }}][slots][0][to]"
                                value="{{ $slotOne[1] }}"
                                placeholder="18:00"
                                maxlength="5"
                                inputmode="numeric"
                                autocomplete="off"
                                data-slot-to="0"
                            >
                        </div>
                    </div>

                    <div class="row g-2 align-items-end mb-2 @if(!$slotTwoVisible) d-none @endif" data-slot-row="1">
                        <div class="col-5">
                            <label class="form-label" for="opening-hours-{{ $dayKey }}-slot-1-from">{{ __('messages.opening_hours.editor.from') }}</label>
                            <input
                                id="opening-hours-{{ $dayKey }}-slot-1-from"
                                type="text"
                                class="form-control"
                                name="opening_hours[days][{{ $dayKey }}][slots][1][from]"
                                value="{{ $slotTwo[0] }}"
                                placeholder="14:00"
                                maxlength="5"
                                inputmode="numeric"
                                autocomplete="off"
                                data-slot-from="1"
                            >
                        </div>
                        <div class="col-5">
                            <label class="form-label" for="opening-hours-{{ $dayKey }}-slot-1-to">{{ __('messages.opening_hours.editor.to') }}</label>
                            <input
                                id="opening-hours-{{ $dayKey }}-slot-1-to"
                                type="text"
                                class="form-control"
                                name="opening_hours[days][{{ $dayKey }}][slots][1][to]"
                                value="{{ $slotTwo[1] }}"
                                placeholder="18:00"
                                maxlength="5"
                                inputmode="numeric"
                                autocomplete="off"
                                data-slot-to="1"
                            >
                        </div>
                        <div class="col-2 d-flex justify-content-end">
                            <button type="button" class="btn btn-sm btn-light text-danger" data-remove-break aria-label="{{ __('messages.opening_hours.editor.remove_break') }}">&times;</button>
                        </div>
                    </div>

                    <button type="button" class="btn btn-sm btn-outline-secondary @if($slotTwoVisible) d-none @endif" data-add-break>
                        {{ __('messages.opening_hours.editor.add_break') }}
                    </button>
                </div>

                <small class="text-danger d-none mt-2" data-day-error></small>
            </div>
        </div>
    @endforeach

    @if(
        $errors->has('opening_hours') ||
        $errors->has('opening_hours.preset') ||
        $errors->has('opening_hours.days')
    )
        <small class="text-danger d-block mt-1">
            {{ $errors->first('opening_hours') ?: $errors->first('opening_hours.preset') ?: $errors->first('opening_hours.days') }}
        </small>
    @endif
</div>

<style>
    .opening-hours-inline-toggle {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        white-space: nowrap;
    }

    .opening-hours-inline-toggle__input {
        margin: 0;
        flex-shrink: 0;
    }

    .opening-hours-day-row {
        transition: opacity 160ms ease, background-color 160ms ease;
    }

    .opening-hours-day-row--closed {
        opacity: 0.62;
    }
</style>

<script>
(function () {
    var editor = document.getElementById('opening-hours-editor');
    if (!editor) {
        return;
    }

    var form = editor.closest('form');
    var labels = {
        invalidTime: @json($editorLabels['invalid_time']),
        invalidRange: @json($editorLabels['invalid_range']),
        slotRequired: @json($editorLabels['slot_required']),
        open: @json(__('messages.opening_hours.editor.open')),
        closed: @json(__('messages.opening_hours.editor.closed'))
    };

    function isValidTime(value) {
        return /^(?:[01]\d|2[0-3]):[0-5]\d$/.test(value);
    }

    function toMinutes(value) {
        var parts = value.split(':');
        return (Number(parts[0]) * 60) + Number(parts[1]);
    }

    function setDayError(row, message) {
        var errorNode = row.querySelector('[data-day-error]');
        if (!errorNode) {
            return;
        }

        if (!message) {
            errorNode.textContent = '';
            errorNode.classList.add('d-none');
            return;
        }

        errorNode.textContent = message;
        errorNode.classList.remove('d-none');
    }

    function slotInputs(row, index) {
        return {
            from: row.querySelector('[data-slot-from="' + index + '"]'),
            to: row.querySelector('[data-slot-to="' + index + '"]')
        };
    }

    function normalizeInputValue(input) {
        if (!input) {
            return '';
        }

        input.value = String(input.value || '').trim();
        return input.value;
    }

    function updateDayUi(row) {
        var openToggle = row.querySelector('[data-open-toggle]');
        var openLabel = null;
        if (openToggle && openToggle.id) {
            openLabel = row.querySelector('.custom-control-label[for="' + openToggle.id + '"]');
        }
        var daySettings = row.querySelector('[data-day-settings]');
        var slotSection = row.querySelector('[data-slot-section]');
        var slotTwoRow = row.querySelector('[data-slot-row="1"]');
        var addBreakButton = row.querySelector('[data-add-break]');

        if (!openToggle || !daySettings || !slotSection || !slotTwoRow || !addBreakButton) {
            return;
        }

        var isOpen = !!openToggle.checked;
        if (openLabel) {
            openLabel.textContent = isOpen ? labels.open : labels.closed;
        }

        row.classList.toggle('opening-hours-day-row--closed', !isOpen);
        daySettings.style.display = isOpen ? '' : 'none';
        slotSection.style.display = isOpen ? '' : 'none';

        var slotTwoVisible = !slotTwoRow.classList.contains('d-none');
        addBreakButton.classList.toggle('d-none', slotTwoVisible || !isOpen);

        if (!isOpen) {
            setDayError(row, '');
        }
    }

    function validateRow(row) {
        var openToggle = row.querySelector('[data-open-toggle]');
        var slotTwoRow = row.querySelector('[data-slot-row="1"]');

        if (!openToggle || !slotTwoRow) {
            return true;
        }

        if (!openToggle.checked) {
            setDayError(row, '');
            return true;
        }

        var firstSlot = slotInputs(row, 0);
        var secondSlot = slotInputs(row, 1);

        var firstFrom = normalizeInputValue(firstSlot.from);
        var firstTo = normalizeInputValue(firstSlot.to);

        if (firstFrom === '' || firstTo === '') {
            setDayError(row, labels.slotRequired);
            return false;
        }

        if (!isValidTime(firstFrom) || !isValidTime(firstTo)) {
            setDayError(row, labels.invalidTime);
            return false;
        }

        if (toMinutes(firstTo) <= toMinutes(firstFrom)) {
            setDayError(row, labels.invalidRange);
            return false;
        }

        var secondFrom = normalizeInputValue(secondSlot.from);
        var secondTo = normalizeInputValue(secondSlot.to);
        var secondHasValue = secondFrom !== '' || secondTo !== '';

        if (secondHasValue) {
            if (secondFrom === '' || secondTo === '') {
                setDayError(row, labels.invalidTime);
                return false;
            }

            if (!isValidTime(secondFrom) || !isValidTime(secondTo)) {
                setDayError(row, labels.invalidTime);
                return false;
            }

            if (toMinutes(secondTo) <= toMinutes(secondFrom)) {
                setDayError(row, labels.invalidRange);
                return false;
            }
        }

        setDayError(row, '');
        return true;
    }

    function attachRowEvents(row) {
        var openToggle = row.querySelector('[data-open-toggle]');
        var addBreakButton = row.querySelector('[data-add-break]');
        var removeBreakButton = row.querySelector('[data-remove-break]');
        var slotTwoRow = row.querySelector('[data-slot-row="1"]');
        var timeInputs = row.querySelectorAll('[data-slot-from], [data-slot-to]');

        if (!openToggle || !addBreakButton || !removeBreakButton || !slotTwoRow) {
            return;
        }

        openToggle.addEventListener('change', function () {
            updateDayUi(row);
            validateRow(row);
        });

        addBreakButton.addEventListener('click', function () {
            slotTwoRow.classList.remove('d-none');
            updateDayUi(row);
        });

        removeBreakButton.addEventListener('click', function () {
            var secondSlot = slotInputs(row, 1);
            if (secondSlot.from) {
                secondSlot.from.value = '';
            }
            if (secondSlot.to) {
                secondSlot.to.value = '';
            }
            slotTwoRow.classList.add('d-none');
            updateDayUi(row);
            validateRow(row);
        });

        timeInputs.forEach(function (input) {
            input.addEventListener('blur', function () {
                validateRow(row);
            });
        });

        updateDayUi(row);
    }

    var dayRows = editor.querySelectorAll('[data-day-row]');
    dayRows.forEach(attachRowEvents);

    if (!form) {
        return;
    }

    form.addEventListener('submit', function (event) {
        var firstInvalidRow = null;

        dayRows.forEach(function (row) {
            var valid = validateRow(row);
            if (!valid && !firstInvalidRow) {
                firstInvalidRow = row;
            }
        });

        if (!firstInvalidRow) {
            return;
        }

        event.preventDefault();
        firstInvalidRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
})();
</script>
