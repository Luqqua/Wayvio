<?php

/**
 * Handles the logic for "imprint" link type.
 *
 * @param \Illuminate\Http\Request $request The incoming request.
 * @param mixed $linkType The link type information.
 * @return array The prepared link data.
 */
function handleLinkType($request, $linkType) {
    $rules = [
        'imprint_name' => [
            'required',
            'string',
            'max:255',
        ],
        'imprint_legal_form' => [
            'nullable',
            'string',
            'max:255',
        ],
        'imprint_represented_by' => [
            'nullable',
            'string',
            'max:500',
        ],
        'imprint_street' => [
            'required',
            'string',
            'max:255',
        ],
        'imprint_postal_code' => [
            'required',
            'string',
            'max:64',
        ],
        'imprint_city' => [
            'required',
            'string',
            'max:255',
        ],
        'imprint_country' => [
            'required',
            'string',
            'max:255',
        ],
        'imprint_email' => [
            'required',
            'email',
            'max:255',
        ],
        'imprint_phone' => [
            'nullable',
            'string',
            'max:255',
        ],
        'imprint_fax' => [
            'nullable',
            'string',
            'max:255',
        ],
        'imprint_supervisory_authority' => [
            'nullable',
            'string',
            'max:2000',
        ],
        'imprint_register_name' => [
            'nullable',
            'string',
            'max:255',
        ],
        'imprint_register_court' => [
            'nullable',
            'string',
            'max:255',
        ],
        'imprint_register_number' => [
            'nullable',
            'string',
            'max:255',
        ],
        'imprint_professional_title' => [
            'nullable',
            'string',
            'max:255',
        ],
        'imprint_professional_state' => [
            'nullable',
            'string',
            'max:255',
        ],
        'imprint_chamber' => [
            'nullable',
            'string',
            'max:500',
        ],
        'imprint_professional_rules' => [
            'nullable',
            'string',
            'max:2000',
        ],
        'imprint_vat_id' => [
            'nullable',
            'string',
            'max:255',
        ],
        'imprint_business_id' => [
            'nullable',
            'string',
            'max:255',
        ],
        'imprint_liquidation_notice' => [
            'nullable',
            'string',
            'max:1000',
        ],
        'imprint_additional_text' => [
            'nullable',
            'string',
            'max:4000',
        ],
        'imprint_contact_form_enabled' => [
            'nullable',
            'boolean',
        ],
    ];

    $trimmedOptionalField = static function ($value) {
        $trimmed = trim((string) $value);
        return $trimmed === '' ? null : $trimmed;
    };

    $linkData = [
        'title' => __('messages.imprint.default_title'),
        'button_id' => '1',
        'imprint_name' => trim((string) $request->imprint_name),
        'imprint_legal_form' => $trimmedOptionalField($request->imprint_legal_form),
        'imprint_represented_by' => $trimmedOptionalField($request->imprint_represented_by),
        'imprint_street' => trim((string) $request->imprint_street),
        'imprint_postal_code' => trim((string) $request->imprint_postal_code),
        'imprint_city' => trim((string) $request->imprint_city),
        'imprint_country' => trim((string) $request->imprint_country),
        'imprint_email' => trim((string) $request->imprint_email),
        'imprint_phone' => $trimmedOptionalField($request->imprint_phone),
        'imprint_fax' => $trimmedOptionalField($request->imprint_fax),
        'imprint_supervisory_authority' => $trimmedOptionalField($request->imprint_supervisory_authority),
        'imprint_register_name' => $trimmedOptionalField($request->imprint_register_name),
        'imprint_register_court' => $trimmedOptionalField($request->imprint_register_court),
        'imprint_register_number' => $trimmedOptionalField($request->imprint_register_number),
        'imprint_professional_title' => $trimmedOptionalField($request->imprint_professional_title),
        'imprint_professional_state' => $trimmedOptionalField($request->imprint_professional_state),
        'imprint_chamber' => $trimmedOptionalField($request->imprint_chamber),
        'imprint_professional_rules' => $trimmedOptionalField($request->imprint_professional_rules),
        'imprint_vat_id' => $trimmedOptionalField($request->imprint_vat_id),
        'imprint_business_id' => $trimmedOptionalField($request->imprint_business_id),
        'imprint_liquidation_notice' => $trimmedOptionalField($request->imprint_liquidation_notice),
        'imprint_additional_text' => $trimmedOptionalField($request->imprint_additional_text),
        'imprint_contact_form_enabled' => $request->boolean('imprint_contact_form_enabled'),
    ];

    return ['rules' => $rules, 'linkData' => $linkData];
}
