@php
    $rawTestimonials = is_array($link->testimonials ?? null) ? $link->testimonials : [];
    $testimonials = [];

    foreach ($rawTestimonials as $testimonial) {
        if (!is_array($testimonial)) {
            continue;
        }

        $name = trim(strip_tags((string) ($testimonial['customer_name'] ?? '')));
        $reviewText = trim(strip_tags((string) ($testimonial['review_text'] ?? '')));
        $rating = (int) ($testimonial['rating'] ?? 0);
        if ($rating < 1 || $rating > 5) {
            continue;
        }

        $avatarUrl = trim((string) ($testimonial['avatar_url'] ?? ''));
        if ($avatarUrl !== '' && !str_starts_with($avatarUrl, '/') && filter_var($avatarUrl, FILTER_VALIDATE_URL) === false) {
            $avatarUrl = '';
        }

        if ($name === '' || $reviewText === '') {
            continue;
        }

        $testimonials[] = [
            'customer_name' => $name,
            'rating' => $rating,
            'review_text' => $reviewText,
            'avatar_url' => $avatarUrl,
        ];

        if (count($testimonials) >= 5) {
            break;
        }
    }

    $testimonialCount = count($testimonials);
    $useSlider = $testimonialCount >= 4;
    $style = normalizeContentBlockStyle($link->social_style ?? 'glass', 'glass');

    $profileUserId = $userinfo->id ?? ($link->user_id ?? null);
    $themeForCapabilities = empty($GLOBALS['themeName'] ?? null) ? (string) ($userinfo->theme ?? 'default') : (string) $GLOBALS['themeName'];
    $applyUserTextColors = templateCapability($themeForCapabilities, 'text_accent', $themeForCapabilities === 'default');
    $textColorSettings = resolveUserTextColorSettings($profileUserId);

    $socialTextColor = $applyUserTextColors ? ($textColorSettings['color'] ?? '#FFFFFF') : 'var(--textColor, #FFFFFF)';
    $socialAccentColor = $applyUserTextColors ? ($textColorSettings['color'] ?? '#FFFFFF') : 'var(--accentColor, var(--textColor, #FFFFFF))';
    $socialTextIsDark = $applyUserTextColors ? ($textColorSettings['is_dark'] ?? false) : false;
    $socialMutedColor = $applyUserTextColors
        ? hexToRgba((string) $socialTextColor, $socialTextIsDark ? 0.74 : 0.78)
        : 'color-mix(in srgb, var(--textColor, #FFFFFF) 78%, transparent)';
    $socialHintColor = $applyUserTextColors
        ? hexToRgba((string) $socialTextColor, $socialTextIsDark ? 0.68 : 0.72)
        : 'color-mix(in srgb, var(--textColor, #FFFFFF) 72%, transparent)';
    $socialContextClass = $socialTextIsDark ? 'is-light' : 'is-dark';

    $socialAvatarBg = $applyUserTextColors
        ? hexToRgba((string) $socialAccentColor, $socialTextIsDark ? 0.14 : 0.2)
        : 'color-mix(in srgb, var(--accentColor, var(--textColor, #FFFFFF)) 20%, transparent)';
    $socialAvatarBorder = $applyUserTextColors
        ? hexToRgba((string) $socialAccentColor, $socialTextIsDark ? 0.26 : 0.3)
        : 'color-mix(in srgb, var(--accentColor, var(--textColor, #FFFFFF)) 30%, transparent)';
@endphp

@if($testimonialCount > 0)
    @include('wayvio.modules.block-style-presets')

    @once
    <style>
        .ls-social-proof {
            --ls-social-text: var(--textColor, #ffffff);
            --ls-social-muted: color-mix(in srgb, var(--textColor, #ffffff) 78%, transparent);
            --ls-social-hint: color-mix(in srgb, var(--textColor, #ffffff) 72%, transparent);
            --ls-social-accent: var(--accentColor, var(--textColor, #ffffff));
            --ls-social-star-size: 1rem;
            width: var(--ls-page-content-width, clamp(260px, 92vw, 520px));
            box-sizing: border-box;
            margin: 0 auto;
            overflow: visible;
        }

        .ls-social-proof__track {
            display: grid;
            grid-template-columns: 1fr;
            gap: 14px;
            overflow: visible;
        }

        .ls-social-proof__card {
            position: relative;
            padding: 14px;
            display: flex;
            flex-direction: column;
            border-radius: 16px;
        }

        .block-preset-clean .ls-social-proof__card {
            border: 0;
            border-radius: 0;
            background: transparent;
            box-shadow: none;
            -webkit-backdrop-filter: none;
            backdrop-filter: none;
            padding: 12px 0;
        }

        .ls-social-proof__avatar {
            width: 42px;
            height: 42px;
            border-radius: 999px;
            object-fit: cover;
            border: 1px solid var(--ls-social-avatar-border);
            background: var(--ls-social-avatar-bg);
            margin-bottom: 10px;
        }

        .ls-social-proof__stars {
            display: inline-flex;
            gap: 3px;
            margin-bottom: 12px;
        }

        .ls-social-proof__star {
            width: var(--ls-social-star-size);
            height: var(--ls-social-star-size);
            fill: var(--ls-social-accent);
            opacity: 0.28;
        }

        .ls-social-proof__star.is-filled {
            opacity: 1;
        }

        .ls-social-proof__quote {
            margin: 0;
            color: var(--ls-social-text);
            font-size: var(--ls-type-body-size, 0.95rem);
            line-height: var(--ls-type-body-line, 1.52);
            word-break: normal;
            overflow-wrap: break-word;
            hyphens: none;
        }

        .ls-social-proof__name {
            margin-top: 12px;
            text-align: right;
            color: var(--ls-social-muted);
            font-size: var(--ls-type-small-size, 0.84rem);
            line-height: var(--ls-type-small-line, 1.2);
            font-weight: 600;
        }

        .ls-social-proof__hint {
            margin: 10px 2px 0;
            color: var(--ls-social-hint);
            font-size: var(--ls-type-meta-size, 0.8rem);
            text-align: right;
        }

        @media (min-width: 840px) {
            .ls-social-proof:not(.is-slider) .ls-social-proof__track {
                grid-template-columns: repeat(var(--ls-social-columns, 3), minmax(0, 1fr));
            }
        }

        .ls-social-proof.is-slider .ls-social-proof__track {
            display: flex;
            overflow: visible;
            scroll-snap-type: x mandatory;
            gap: 12px;
            padding-bottom: 0;
            -webkit-overflow-scrolling: touch;
        }

        .ls-social-proof.is-slider .ls-social-proof__card {
            flex: 0 0 min(84vw, 320px);
            scroll-snap-align: start;
        }

        .ls-social-proof.is-slider.block-preset-clean .ls-social-proof__card {
            padding: 12px 0;
        }

        @media (max-width: 768px) {
            .ls-social-proof {
                width: min(var(--ls-page-content-width, clamp(260px, 92vw, 520px)), 90vw);
            }
        }
    </style>
    @endonce

    <div style="--delay: {{ $initial }}s" class="button-entrance ls-block-entrance">
        <section
            class="ls-social-proof fadein {{ $socialContextClass }} {{ $useSlider ? 'is-slider' : '' }} block-preset-{{ $style }}"
            data-ls-adaptive-bold
            style="
                --ls-social-columns: {{ min($testimonialCount, 3) }};
                --ls-social-text: {{ $socialTextColor }};
                --ls-social-muted: {{ $socialMutedColor }};
                --ls-social-hint: {{ $socialHintColor }};
                --ls-social-accent: {{ $socialAccentColor }};
                --ls-social-avatar-bg: {{ $socialAvatarBg }};
                --ls-social-avatar-border: {{ $socialAvatarBorder }};
                --ls-block-text-color: {{ $socialTextColor }};
                --ls-block-accent-color: {{ $socialAccentColor }};
                --ls-block-bold-bg: rgba(255, 255, 255, 0.95);
            "
            aria-label="Kundenbewertungen"
        >
            <div class="ls-social-proof__track">
                @foreach($testimonials as $testimonial)
                    <article class="block-preset-surface ls-social-proof__card">
                        @if($testimonial['avatar_url'] !== '')
                            <img
                                src="{{ $testimonial['avatar_url'] }}"
                                alt="Avatar von {{ $testimonial['customer_name'] }}"
                                class="ls-social-proof__avatar"
                                loading="lazy"
                                decoding="async"
                                referrerpolicy="no-referrer"
                            />
                        @endif

                        <div class="ls-social-proof__stars" aria-label="{{ $testimonial['rating'] }} von 5 Sternen">
                            @for($star = 1; $star <= 5; $star++)
                                <svg class="ls-social-proof__star {{ $star <= $testimonial['rating'] ? 'is-filled' : '' }}" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path d="M12 2.5l2.83 5.73 6.32.92-4.57 4.46 1.08 6.29L12 17.9 6.34 19.9l1.08-6.29L2.85 9.15l6.32-.92L12 2.5z"></path>
                                </svg>
                            @endfor
                        </div>

                        <blockquote class="ls-social-proof__quote">"{{ $testimonial['review_text'] }}"</blockquote>
                        <div class="ls-social-proof__name">{{ $testimonial['customer_name'] }}</div>
                    </article>
                @endforeach
            </div>

            @if($useSlider)
                <p class="ls-social-proof__hint">Wischen für weitere Bewertungen.</p>
            @endif
        </section>
    </div>
@endif
