<label for='title' class='form-label'>{{__('messages.Title')}}</label>
<input type='text' name='title' value='{{$title}}' placeholder="Leave blank for default video title" class='form-control' />

<label for='link' class='form-label'>{{__('messages.URL')}}</label>
<input type='text' name='link' value='{{$link}}' class='form-control' autocomplete='new-password' autocapitalize='off' autocorrect='off' spellcheck='false' inputmode='url' aria-autocomplete='none' data-lpignore='true' data-1p-ignore='true' readonly onfocus="this.removeAttribute('readonly')" onpointerdown="this.removeAttribute('readonly')" ontouchstart="this.removeAttribute('readonly')" onblur="this.setAttribute('readonly', 'readonly')" />
<span class='small text-muted'>{{__('messages.URL to the video')}}</span>
