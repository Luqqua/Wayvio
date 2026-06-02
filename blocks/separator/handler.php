<?php

/**
 * Handles the logic for "separator" link type.
 *
 * @param \Illuminate\Http\Request $request The incoming request.
 * @param mixed $linkType The link type information.
 * @return array The prepared link data.
 */
function handleLinkType($request, $linkType)
{
    $rules = [];

    $linkData = [
        'title' => trim((string) ($linkType->title ?? __('Separator'))),
    ];

    return ['rules' => $rules, 'linkData' => $linkData];
}
