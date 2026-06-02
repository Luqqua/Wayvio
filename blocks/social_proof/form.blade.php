@php
    $blockTitle = old('title', $title ?? '');
    $socialStyle = normalizeContentBlockStyle(old('social_style', $social_style ?? 'glass'), 'glass');
    $testimonialsData = old('testimonials', $testimonials ?? []);

    if (!is_array($testimonialsData) || count($testimonialsData) === 0) {
        $testimonialsData = [
            [
                'customer_name' => '',
                'rating' => 5,
                'review_text' => '',
                'avatar_url' => '',
            ],
        ];
    }

    if (count($testimonialsData) > 5) {
        $testimonialsData = array_slice($testimonialsData, 0, 5);
    }
@endphp

<div id="social-proof-editor" class="p-3 rounded border bg-white">
    <div class="alert alert-warning mb-3" role="alert">{{ __('messages.social_proof.editor.notice') }}</div>

    <label for="social-proof-title" class="form-label">{{ __('messages.common.internal_title') }}</label>
    <input
        id="social-proof-title"
        type="text"
        name="title"
        class="form-control"
        maxlength="120"
        value="{{ $blockTitle }}"
        placeholder="{{ __('messages.social_proof.editor.internal_title_placeholder') }}"
    />
    <small class="text-muted d-block mt-1 mb-3">{{ __('messages.common.internal_title_hint') }}</small>

    <div class="row g-3 mb-3">
        <div class="col-md-8">
            <label for="social-style" class="form-label">{{ __('messages.common.visual_style') }}</label>
            <select id="social-style" name="social_style" class="form-control">
                <option value="clean" @selected($socialStyle === 'clean')>Clean</option>
                <option value="glass" @selected($socialStyle === 'glass')>Glass</option>
                <option value="bold" @selected($socialStyle === 'bold')>Bold</option>
            </select>
            <small class="text-muted d-block mt-1">{{ __('messages.common.visual_style_hint') }}</small>
        </div>
    </div>

    <div class="d-flex align-items-center justify-content-between mb-2">
        <h6 class="mb-0">{{ __('messages.social_proof.editor.testimonials') }} <small class="text-muted" data-testimonial-count></small></h6>
        <button type="button" class="btn btn-sm btn-outline-primary" data-add-testimonial>{{ __('messages.social_proof.editor.add_testimonial') }}</button>
    </div>

    <div data-testimonials-container data-next-index="{{ count($testimonialsData) }}">
        @foreach($testimonialsData as $index => $testimonial)
            @php
                $customerName = trim((string) ($testimonial['customer_name'] ?? ''));
                $rating = (int) ($testimonial['rating'] ?? 5);
                if ($rating < 1 || $rating > 5) {
                    $rating = 5;
                }
                $reviewText = trim((string) ($testimonial['review_text'] ?? ''));
                $avatarUrl = trim((string) ($testimonial['avatar_url'] ?? ''));
            @endphp

            <div class="border rounded p-3 mb-3" data-testimonial-row>
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <strong data-testimonial-label>{{ __('messages.social_proof.editor.testimonial') }} {{ $loop->iteration }}</strong>
                    <button type="button" class="btn btn-sm btn-light text-danger" data-remove-testimonial>{{ __('messages.common.remove') }}</button>
                </div>

                <label class="form-label" for="testimonial-name-{{ $index }}">{{ __('messages.common.name') }}</label>
                <input
                    id="testimonial-name-{{ $index }}"
                    type="text"
                    name="testimonials[{{ $index }}][customer_name]"
                    class="form-control"
                    maxlength="80"
                    value="{{ $customerName }}"
                    placeholder="{{ __('messages.social_proof.editor.name_placeholder') }}"
                />

                <label class="form-label mt-2" for="testimonial-rating-{{ $index }}">{{ __('messages.social_proof.editor.rating') }}</label>
                <select id="testimonial-rating-{{ $index }}" name="testimonials[{{ $index }}][rating]" class="form-control">
                    @for($stars = 1; $stars <= 5; $stars++)
                        <option value="{{ $stars }}" @selected($rating === $stars)>{{ $stars }} / 5</option>
                    @endfor
                </select>

                <label class="form-label mt-2" for="testimonial-text-{{ $index }}">{{ __('messages.social_proof.editor.review_text') }}</label>
                <textarea
                    id="testimonial-text-{{ $index }}"
                    name="testimonials[{{ $index }}][review_text]"
                    class="form-control"
                    rows="3"
                    maxlength="600"
                    placeholder="{{ __('messages.social_proof.editor.review_placeholder') }}"
                >{{ $reviewText }}</textarea>

                <label class="form-label mt-2" for="testimonial-avatar-{{ $index }}">{{ __('messages.social_proof.editor.avatar_optional') }}</label>
                <input
                    id="testimonial-avatar-{{ $index }}"
                    type="text"
                    name="testimonials[{{ $index }}][avatar_url]"
                    class="form-control"
                    maxlength="2048"
                    value="{{ $avatarUrl }}"
                    placeholder="{{ __('messages.social_proof.editor.avatar_placeholder') }}"
                    autocomplete="new-password"
                    autocapitalize="off"
                    autocorrect="off"
                    spellcheck="false"
                    aria-autocomplete="none"
                    inputmode="url"
                    data-lpignore="true"
                    data-1p-ignore="true"
                    readonly
                    onfocus="this.removeAttribute('readonly')"
                    onpointerdown="this.removeAttribute('readonly')"
                    ontouchstart="this.removeAttribute('readonly')"
                    onblur="this.setAttribute('readonly','readonly')"
                />
            </div>
        @endforeach
    </div>

    @if($errors->has('social_style') || $errors->has('testimonials') || $errors->has('testimonials.*.rating') || $errors->has('testimonials.*.review_text'))
        <small class="text-danger d-block mt-1">
            {{ $errors->first('social_style') ?: $errors->first('testimonials') ?: $errors->first('testimonials.*.rating') ?: $errors->first('testimonials.*.review_text') }}
        </small>
    @endif
</div>

<script>
(function () {
    var editor = document.getElementById('social-proof-editor');
    if (!editor) {
        return;
    }

    var maxItems = 5;
    var container = editor.querySelector('[data-testimonials-container]');
    var addButton = editor.querySelector('[data-add-testimonial]');
    var countLabel = editor.querySelector('[data-testimonial-count]');
    if (!container || !addButton || !countLabel) {
        return;
    }

    var nextIndex = Number(container.getAttribute('data-next-index') || 0);

    function rowCount() {
        return container.querySelectorAll('[data-testimonial-row]').length;
    }

    function updateState() {
        var rows = container.querySelectorAll('[data-testimonial-row]');
        rows.forEach(function (row, index) {
            var label = row.querySelector('[data-testimonial-label]');
            if (label) {
                label.textContent = @json(__('messages.social_proof.editor.testimonial')) + ' ' + (index + 1);
            }
        });

        var count = rows.length;
        countLabel.textContent = '(' + count + '/' + maxItems + ')';
        addButton.disabled = count >= maxItems;
    }

    function createRow(index) {
        var row = document.createElement('div');
        row.className = 'border rounded p-3 mb-3';
        row.setAttribute('data-testimonial-row', '');
        row.innerHTML = '' +
            '<div class="d-flex align-items-center justify-content-between mb-2">' +
                '<strong data-testimonial-label>' + @json(__('messages.social_proof.editor.testimonial')) + '</strong>' +
                '<button type="button" class="btn btn-sm btn-light text-danger" data-remove-testimonial>' + @json(__('messages.common.remove')) + '</button>' +
            '</div>' +
            '<label class="form-label" for="testimonial-name-' + index + '">' + @json(__('messages.common.name')) + '</label>' +
            '<input id="testimonial-name-' + index + '" type="text" name="testimonials[' + index + '][customer_name]" class="form-control" maxlength="80" placeholder="' + @json(__('messages.social_proof.editor.name_placeholder')) + '">' +
            '<label class="form-label mt-2" for="testimonial-rating-' + index + '">' + @json(__('messages.social_proof.editor.rating')) + '</label>' +
            '<select id="testimonial-rating-' + index + '" name="testimonials[' + index + '][rating]" class="form-control">' +
                '<option value="5">5 / 5</option>' +
                '<option value="4">4 / 5</option>' +
                '<option value="3">3 / 5</option>' +
                '<option value="2">2 / 5</option>' +
                '<option value="1">1 / 5</option>' +
            '</select>' +
            '<label class="form-label mt-2" for="testimonial-text-' + index + '">' + @json(__('messages.social_proof.editor.review_text')) + '</label>' +
            '<textarea id="testimonial-text-' + index + '" name="testimonials[' + index + '][review_text]" class="form-control" rows="3" maxlength="600" placeholder="' + @json(__('messages.social_proof.editor.review_placeholder')) + '"></textarea>' +
            '<label class="form-label mt-2" for="testimonial-avatar-' + index + '">' + @json(__('messages.social_proof.editor.avatar_optional')) + '</label>' +
            '<input id="testimonial-avatar-' + index + '" type="text" name="testimonials[' + index + '][avatar_url]" class="form-control" maxlength="2048" placeholder="' + @json(__('messages.social_proof.editor.avatar_placeholder')) + '" autocomplete="new-password" autocapitalize="off" autocorrect="off" spellcheck="false" aria-autocomplete="none" inputmode="url" data-lpignore="true" data-1p-ignore="true" readonly onfocus="this.removeAttribute(\'readonly\')" onpointerdown="this.removeAttribute(\'readonly\')" ontouchstart="this.removeAttribute(\'readonly\')" onblur="this.setAttribute(\'readonly\',\'readonly\')">';

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
        var removeButton = event.target.closest('[data-remove-testimonial]');
        if (!removeButton) {
            return;
        }

        var row = removeButton.closest('[data-testimonial-row]');
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
