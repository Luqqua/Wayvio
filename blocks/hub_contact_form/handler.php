<?php

use App\Models\Link;
use App\Models\User;
use App\Services\Forms\FormsAccess;

function handleLinkType_hub_contact_form($request, $linkType)
{
    $activeHubUserId = (int) ($request->input('page_id') ?: auth()->id());
    $currentLinkId = (int) $request->input('linkid', 0);
    $hub = $activeHubUserId > 0 ? User::query()->find($activeHubUserId) : null;
    $formsAccess = app(FormsAccess::class);

    $internalTitle = trim(strip_tags((string) $request->input('internal_title', '')));
    $defaultTitle = (string) __('messages.hub_contact_form.editor.default_title');
    $title = trim(strip_tags((string) $request->input('title', $defaultTitle)));
    if ($title === '') {
        $title = $defaultTitle;
    }
    $formDescription = trim(strip_tags((string) $request->input('form_description', '')));
    $formStyle = normalizeContentBlockStyle($request->input('form_style', 'bold'), 'bold');

    $rules = [
        'internal_title' => ['nullable', 'string', 'max:120'],
        'title' => ['nullable', 'string', 'max:120'],
        'form_description' => ['nullable', 'string', 'max:300'],
        'form_style' => ['nullable', 'string', contentBlockStyleInRule()],
        'typename' => [
            function ($attribute, $value, $fail) use ($hub, $formsAccess, $activeHubUserId, $currentLinkId): void {
                if (!$hub || !$formsAccess->formsAllowedForHub($hub)) {
                    $fail('Forms are available from the Basic tier.');
                    return;
                }

                $exists = Link::query()
                    ->where('user_id', $activeHubUserId)
                    ->where('type', 'hub_contact_form')
                    ->when($currentLinkId > 0, fn ($query) => $query->where('id', '!=', $currentLinkId))
                    ->exists();

                if ($exists) {
                    $fail('Each hub can contain only one contact form block.');
                }
            },
        ],
    ];

    return [
        'rules' => $rules,
        'linkData' => [
            'title' => $title,
            'internal_title' => $internalTitle,
            'form_description' => $formDescription,
            'form_style' => $formStyle,
            'button_id' => '1',
            'link' => null,
        ],
    ];
}
