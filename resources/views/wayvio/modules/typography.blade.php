@once
<style>
    :root {
        --ls-type-profile-title-size: var(--ls-template-type-profile-title-size, clamp(2.9rem, 6.1vw, 4.85rem));
        --ls-type-profile-title-line: var(--ls-template-type-profile-title-line, 1.04);
        --ls-type-profile-title-weight: var(--ls-template-type-profile-title-weight, 800);
        --ls-type-profile-title-secondary-size: var(--ls-template-type-profile-title-secondary-size, clamp(1.05rem, 1.8vw, 1.45rem));
        --ls-type-profile-title-secondary-line: var(--ls-template-type-profile-title-secondary-line, 1.16);
        --ls-type-profile-title-secondary-weight: var(--ls-template-type-profile-title-secondary-weight, 700);
        --ls-type-profile-description-focus-size: var(--ls-template-type-profile-description-focus-size, clamp(3.1rem, 6.9vw, 5.2rem));
        --ls-type-profile-description-size: var(--ls-template-type-profile-description-size, clamp(1.2rem, 2.5vw, 1.9rem));
        --ls-type-profile-description-line: var(--ls-template-type-profile-description-line, 1.28);
        --ls-type-profile-description-weight: var(--ls-template-type-profile-description-weight, 500);

        --ls-type-block-title-size: var(--ls-template-type-block-title-size, clamp(1.2rem, 1.08rem + 0.55vw, 1.48rem));
        --ls-type-block-title-line: var(--ls-template-type-block-title-line, 1.16);
        --ls-type-block-title-weight: var(--ls-template-type-block-title-weight, 750);
        --ls-type-section-size: var(--ls-template-type-section-size, clamp(1rem, 0.96rem + 0.18vw, 1.07rem));
        --ls-type-section-line: var(--ls-template-type-section-line, 1.28);
        --ls-type-section-weight: var(--ls-template-type-section-weight, 700);
        --ls-type-body-size: var(--ls-template-type-body-size, 0.96rem);
        --ls-type-body-line: var(--ls-template-type-body-line, 1.5);
        --ls-type-body-weight: var(--ls-template-type-body-weight, 400);
        --ls-type-small-size: var(--ls-template-type-small-size, 0.84rem);
        --ls-type-small-line: var(--ls-template-type-small-line, 1.45);
        --ls-type-meta-size: var(--ls-template-type-meta-size, 0.72rem);
        --ls-type-meta-line: var(--ls-template-type-meta-line, 1.25);
        --ls-type-button-size: var(--ls-template-type-button-size, 1rem);
        --ls-type-button-line: var(--ls-template-type-button-line, 1.35);
        --ls-type-button-weight: var(--ls-template-type-button-weight, 700);
        --ls-type-footer-size: var(--ls-template-type-footer-size, 0.84rem);
    }

    .button.ls-link-interactive,
    .ls-link-interactive {
        font-size: var(--ls-type-button-size);
        line-height: var(--ls-type-button-line);
        font-weight: var(--ls-type-button-weight);
        letter-spacing: 0;
    }

    .ls-link-interactive > .button-text-wrapper {
        font: inherit;
        letter-spacing: 0;
    }

    .sharebutton,
    sharebutton {
        font-size: var(--ls-type-button-size);
        line-height: var(--ls-type-button-line);
        font-weight: var(--ls-type-button-weight);
        letter-spacing: 0;
    }

    .ls-heading-block__title {
        font-size: var(--ls-type-block-title-size);
        line-height: var(--ls-type-block-title-line);
        font-weight: var(--ls-type-block-title-weight);
        letter-spacing: 0;
        text-wrap: balance;
        word-break: normal;
        overflow-wrap: break-word;
        hyphens: none;
    }

    .ls-text-block__content {
        font-size: var(--ls-type-body-size);
        line-height: var(--ls-type-body-line);
        font-weight: var(--ls-type-body-weight);
        letter-spacing: 0;
        text-wrap: pretty;
        word-break: normal;
        overflow-wrap: break-word;
        hyphens: none;
    }
</style>
@endonce
