@php
    $internalTitle = old('internal_title', $internal_title ?? '');
    $title = old('title', $title ?? __('messages.hub_contact_form.editor.default_title'));
    $formDescription = old('form_description', $form_description ?? '');
    $formStyle = normalizeContentBlockStyle(old('form_style', $form_style ?? 'bold'), 'bold');
    $formStyleError = isset($errors) ? (string) $errors->first('form_style') : '';
@endphp

<label for="internal_title" class="form-label">{{ __('messages.hub_contact_form.editor.internal_title') }}</label>
<span class="small text-muted d-block mb-1">{{ __('messages.hub_contact_form.editor.internal_title_hint') }}</span>
<input
    id="internal_title"
    type="text"
    name="internal_title"
    value="{{ $internalTitle }}"
    class="form-control"
    maxlength="120"
    placeholder="{{ __('messages.hub_contact_form.editor.internal_title_placeholder') }}"
>

<label for="title" class="form-label mt-3">{{ __('messages.hub_contact_form.editor.public_title') }}</label>
<span class="small text-muted d-block mb-1">{{ __('messages.hub_contact_form.editor.public_title_hint') }}</span>
<input id="title" type="text" name="title" value="{{ $title }}" class="form-control" maxlength="120">

<label for="form_description" class="form-label mt-3">{{ __('messages.hub_contact_form.editor.description') }}</label>
<span class="small text-muted d-block mb-1">{{ __('messages.hub_contact_form.editor.description_hint') }}</span>
<textarea id="form_description" name="form_description" class="form-control" rows="3" maxlength="300">{{ $formDescription }}</textarea>

<label for="form_style" class="form-label mt-3">{{ __('messages.hub_contact_form.editor.visual_style') }}</label>
<span class="small text-muted d-block mb-1">{{ __('messages.hub_contact_form.editor.visual_style_hint') }}</span>
<select id="form_style" name="form_style" class="form-control">
    <option value="clean" @selected($formStyle === 'clean')>Clean</option>
    <option value="glass" @selected($formStyle === 'glass')>Glass</option>
    <option value="bold" @selected($formStyle === 'bold')>Bold</option>
</select>

@if($formStyleError !== '')
    <small class="text-danger d-block mt-1">{{ $formStyleError }}</small>
@endif
