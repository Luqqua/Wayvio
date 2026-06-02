<style>
    .imprint-editor-form .alert {
        margin-bottom: 20px !important;
    }

    .imprint-editor-form .imprint-editor-layout {
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    .imprint-editor-form .imprint-section-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 12px;
        flex-wrap: wrap;
    }

    .imprint-editor-form .imprint-section-title {
        margin: 0 !important;
        font-size: 1.05rem;
    }

    .imprint-editor-form .imprint-cards-stack {
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    .imprint-editor-form .imprint-card {
        margin-bottom: 0;
        border-radius: 12px;
    }

    .imprint-editor-form .imprint-card .card-body {
        padding: 16px;
    }

    .imprint-editor-form .imprint-card-title {
        margin: 0 0 14px !important;
        font-size: 1rem;
    }

    .imprint-editor-form .form-label {
        display: block;
        margin-bottom: 8px;
    }

    .imprint-editor-form .form-control {
        margin-bottom: 0;
    }

    .imprint-editor-form .field-hint {
        margin-top: 6px !important;
        margin-bottom: 0 !important;
    }

    .imprint-editor-form .imprint-card-intro {
        margin: -8px 0 14px !important;
    }

    .imprint-editor-form .toggle-inline {
        display: inline-flex;
        align-items: center;
        gap: 10px;
    }

    .imprint-editor-form .advanced-body {
        margin-top: 12px;
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    @media (max-width: 767.98px) {
        .imprint-editor-form .imprint-card .card-body {
            padding: 14px;
        }

        .imprint-editor-form .advanced-toggle {
            width: 100%;
        }

        .imprint-editor-form .advanced-toggle .btn {
            width: 100%;
        }
    }
</style>

<div class="imprint-editor-form">
<div class="alert alert-warning mb-3" role="alert">
    <strong>{{ __('messages.imprint.editor.notice_title') }}</strong> {{ __('messages.imprint.editor.notice_intro') }}
    <a href="https://www.leipzig.ihk.de/mb-02-101-pflichtangaben-im-internet-die-impressumspflicht/" target="_blank" rel="noopener noreferrer">{{ __('messages.imprint.editor.notice_link_text') }}</a>.
    {{ __('messages.imprint.editor.notice_line1') }}
    {{ __('messages.imprint.editor.notice_line2') }}
</div>

<div class="imprint-editor-layout">
    <section>
        <div class="imprint-section-head">
            <h5 class="imprint-section-title">{{ __('messages.imprint.editor.section.basic') }}</h5>
        </div>

        <div class="imprint-cards-stack">
        <div class="card border imprint-card">
            <div class="card-body">
                <h5 class="imprint-card-title">{{ __('messages.imprint.editor.card.name') }}</h5>
                <label for="imprint_name" class="form-label">{{ __('messages.imprint.editor.label.company_name') }}</label>
                <input type="text" name="imprint_name" id="imprint_name" value="{{ $imprint_name ?? '' }}" class="form-control" required />
                <small class="field-hint text-muted d-block">{{ __('messages.imprint.editor.hint.company_name_required') }}</small>
            </div>
        </div>

        <div class="card border imprint-card">
            <div class="card-body">
                <h5 class="imprint-card-title">{{ __('messages.imprint.editor.card.address') }}</h5>
                <div class="row g-3">
                    <div class="col-12">
                        <label for="imprint_street" class="form-label">{{ __('messages.Street') }}</label>
                        <input type="text" name="imprint_street" id="imprint_street" value="{{ $imprint_street ?? '' }}" class="form-control" required />
                    </div>
                    <div class="col-md-4">
                        <label for="imprint_postal_code" class="form-label">{{ __('messages.Zip/Postal Code') }}</label>
                        <input type="text" name="imprint_postal_code" id="imprint_postal_code" value="{{ $imprint_postal_code ?? '' }}" class="form-control" required />
                    </div>
                    <div class="col-md-8">
                        <label for="imprint_city" class="form-label">{{ __('messages.City') }}</label>
                        <input type="text" name="imprint_city" id="imprint_city" value="{{ $imprint_city ?? '' }}" class="form-control" required />
                    </div>
                    <div class="col-12">
                        <label for="imprint_country" class="form-label">{{ __('messages.Country') }}</label>
                        <input type="text" name="imprint_country" id="imprint_country" value="{{ $imprint_country ?? '' }}" class="form-control" required />
                    </div>
                </div>
            </div>
        </div>

        <div class="card border imprint-card">
            <div class="card-body">
                <h5 class="imprint-card-title">{{ __('messages.imprint.editor.card.contact') }}</h5>
                <p class="imprint-card-intro text-muted">{{ __('messages.imprint.editor.hint.contact_minimum_two') }}</p>
                <div class="row g-3">
                    <div class="col-12">
                        <label for="imprint_email" class="form-label">{{ __('messages.Email') }}</label>
                        <input type="email" name="imprint_email" id="imprint_email" value="{{ $imprint_email ?? '' }}" class="form-control" required />
                    </div>
                    <div class="col-12">
                        <label for="imprint_phone" class="form-label">{{ __('messages.imprint.editor.label.phone_optional') }}</label>
                        <input type="text" name="imprint_phone" id="imprint_phone" value="{{ $imprint_phone ?? '' }}" class="form-control" />
                    </div>
                    <div class="col-12">
                        <div class="form-label mb-2">{{ __('messages.imprint.editor.card.contact_form') }}</div>
                        <div class="form-check form-switch toggle-inline m-0">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                id="imprint_contact_form_enabled"
                                name="imprint_contact_form_enabled"
                                value="1"
                                @checked(old('imprint_contact_form_enabled', $imprint_contact_form_enabled ?? false))
                            >
                            <label class="form-check-label" for="imprint_contact_form_enabled">{{ __('messages.imprint.editor.label.enable_imprint_contact_form') }}</label>
                        </div>
                        <small class="field-hint text-muted d-block">{{ __('messages.imprint.editor.hint.contact_form_note') }}</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border imprint-card">
            <div class="card-body">
                <h5 class="imprint-card-title">{{ __('messages.imprint.editor.card.vat') }}</h5>
                <label for="imprint_vat_id" class="form-label">{{ __('messages.imprint.editor.label.vat_optional') }}</label>
                <input type="text" name="imprint_vat_id" id="imprint_vat_id" value="{{ $imprint_vat_id ?? '' }}" class="form-control" />
                <small class="field-hint text-muted d-block">{{ __('messages.imprint.editor.hint.vat_optional_note') }}</small>
            </div>
        </div>
        </div>{{-- /imprint-cards-stack --}}
    </section>

    <section>
        <div class="imprint-section-head">
            <h5 class="imprint-section-title">{{ __('messages.imprint.editor.section.advanced') }}</h5>
            <div class="advanced-toggle">
                <button class="btn btn-outline-secondary btn-sm collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#imprint-advanced-fields" aria-expanded="false" aria-controls="imprint-advanced-fields">
                    {{ __('messages.imprint.editor.toggle_advanced') }}
                </button>
            </div>
        </div>

        <div class="collapse" id="imprint-advanced-fields">
            <div class="advanced-body">
                <div class="card border imprint-card">
                    <div class="card-body">
                        <h5 class="imprint-card-title">{{ __('messages.imprint.editor.card.name_extended') }}</h5>
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="imprint_legal_form" class="form-label">{{ __('messages.imprint.editor.label.legal_form_optional') }}</label>
                                <input type="text" name="imprint_legal_form" id="imprint_legal_form" value="{{ $imprint_legal_form ?? '' }}" class="form-control" />
                            </div>
                            <div class="col-12">
                                <label for="imprint_represented_by" class="form-label">{{ __('messages.imprint.editor.label.represented_by_optional') }}</label>
                                <input type="text" name="imprint_represented_by" id="imprint_represented_by" value="{{ $imprint_represented_by ?? '' }}" class="form-control" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border imprint-card">
                    <div class="card-body">
                        <h5 class="imprint-card-title">{{ __('messages.imprint.editor.card.register') }}</h5>
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="imprint_register_name" class="form-label">{{ __('messages.imprint.editor.label.register_type') }}</label>
                                <input type="text" name="imprint_register_name" id="imprint_register_name" value="{{ $imprint_register_name ?? '' }}" class="form-control" placeholder="{{ __('messages.imprint.editor.placeholder.register_type') }}" />
                            </div>
                            <div class="col-md-6">
                                <label for="imprint_register_court" class="form-label">{{ __('messages.imprint.editor.label.register_court') }}</label>
                                <input type="text" name="imprint_register_court" id="imprint_register_court" value="{{ $imprint_register_court ?? '' }}" class="form-control" />
                            </div>
                            <div class="col-md-6">
                                <label for="imprint_register_number" class="form-label">{{ __('messages.imprint.editor.label.register_number') }}</label>
                                <input type="text" name="imprint_register_number" id="imprint_register_number" value="{{ $imprint_register_number ?? '' }}" class="form-control" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border imprint-card">
                    <div class="card-body">
                        <h5 class="imprint-card-title">{{ __('messages.imprint.editor.card.supervisory') }}</h5>
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="imprint_supervisory_authority" class="form-label">{{ __('messages.imprint.editor.label.supervisory_authority') }}</label>
                                <textarea name="imprint_supervisory_authority" id="imprint_supervisory_authority" class="form-control" rows="3">{{ $imprint_supervisory_authority ?? '' }}</textarea>
                            </div>
                            <div class="col-md-6">
                                <label for="imprint_chamber" class="form-label">{{ __('messages.imprint.editor.label.chamber') }}</label>
                                <input type="text" name="imprint_chamber" id="imprint_chamber" value="{{ $imprint_chamber ?? '' }}" class="form-control" />
                            </div>
                            <div class="col-md-6">
                                <label for="imprint_professional_title" class="form-label">{{ __('messages.imprint.editor.label.professional_title') }}</label>
                                <input type="text" name="imprint_professional_title" id="imprint_professional_title" value="{{ $imprint_professional_title ?? '' }}" class="form-control" />
                            </div>
                            <div class="col-12">
                                <label for="imprint_professional_state" class="form-label">{{ __('messages.imprint.editor.label.professional_state') }}</label>
                                <input type="text" name="imprint_professional_state" id="imprint_professional_state" value="{{ $imprint_professional_state ?? '' }}" class="form-control" />
                            </div>
                            <div class="col-12">
                                <label for="imprint_professional_rules" class="form-label">{{ __('messages.imprint.editor.label.professional_rules') }}</label>
                                <textarea name="imprint_professional_rules" id="imprint_professional_rules" class="form-control" rows="3">{{ $imprint_professional_rules ?? '' }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border imprint-card">
                    <div class="card-body">
                        <h5 class="imprint-card-title">{{ __('messages.imprint.editor.card.tax_company') }}</h5>
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="imprint_business_id" class="form-label">{{ __('messages.imprint.editor.label.business_id') }}</label>
                                <input type="text" name="imprint_business_id" id="imprint_business_id" value="{{ $imprint_business_id ?? '' }}" class="form-control" />
                            </div>
                            <div class="col-12">
                                <label for="imprint_liquidation_notice" class="form-label">{{ __('messages.imprint.editor.label.liquidation') }}</label>
                                <input type="text" name="imprint_liquidation_notice" id="imprint_liquidation_notice" value="{{ $imprint_liquidation_notice ?? '' }}" class="form-control" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border imprint-card mb-0">
                    <div class="card-body">
                        <h5 class="imprint-card-title">{{ __('messages.imprint.editor.card.additional') }}</h5>
                        <label for="imprint_additional_text" class="form-label">{{ __('messages.imprint.editor.label.additional_text') }}</label>
                        <textarea name="imprint_additional_text" id="imprint_additional_text" class="form-control" rows="5">{{ $imprint_additional_text ?? '' }}</textarea>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
</div>
