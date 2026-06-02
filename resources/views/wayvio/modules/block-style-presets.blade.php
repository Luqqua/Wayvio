@once
<style>
    .block-preset-surface {
        border-radius: 16px;
        border: 0 solid transparent;
        background: transparent;
        box-shadow: none;
        -webkit-backdrop-filter: none;
        backdrop-filter: none;
    }

    .block-preset-clean .block-preset-surface,
    .block-preset-clean.block-preset-surface {
        background: transparent;
        border: 0 solid transparent;
        box-shadow: none;
        -webkit-backdrop-filter: none;
        backdrop-filter: none;
        border-radius: 0;
    }

    .block-preset-glass .block-preset-surface,
    .block-preset-glass.block-preset-surface {
        --ls-liquid-glass-background:
            radial-gradient(130% 88% at 0% 0%, rgba(244, 246, 249, 0.18) 0%, rgba(244, 246, 249, 0) 56%),
            linear-gradient(155deg, rgba(242, 244, 247, 0.17) 0%, rgba(236, 239, 243, 0.09) 50%, rgba(231, 235, 240, 0.064) 100%),
            rgba(229, 233, 238, 0.175);
        --ls-liquid-glass-border: rgba(233, 237, 241, 0.19);
        --ls-liquid-glass-inner-border: rgba(234, 238, 242, 0.118);
        --ls-liquid-glass-shadow:
            0 7px 20px rgba(0, 0, 0, 0.08),
            inset 0 1px 0 rgba(244, 246, 249, 0.25),
            inset 0 -1px 0 rgba(231, 235, 240, 0.062);
        --ls-liquid-glass-filter: blur(14px) saturate(1.06) brightness(0.985);
        position: relative;
        overflow: hidden;
        isolation: isolate;
        background: var(--ls-liquid-glass-background);
        border: 1px solid var(--ls-liquid-glass-border);
        box-shadow: var(--ls-liquid-glass-shadow);
        border-radius: 16px;
        -webkit-backdrop-filter: var(--ls-liquid-glass-filter);
        backdrop-filter: var(--ls-liquid-glass-filter);
    }

    .block-preset-glass .block-preset-surface::before,
    .block-preset-glass.block-preset-surface::before {
        content: "";
        position: absolute;
        inset: 0;
        border-radius: inherit;
        pointer-events: none;
        background:
            linear-gradient(120deg, rgba(246, 248, 250, 0.145) 0%, rgba(244, 246, 249, 0.036) 34%, rgba(244, 246, 249, 0) 64%, rgba(239, 242, 246, 0.062) 100%);
        opacity: 0.3;
        z-index: 0;
    }

    .block-preset-glass .block-preset-surface::after,
    .block-preset-glass.block-preset-surface::after {
        content: "";
        position: absolute;
        inset: 1px;
        border-radius: inherit;
        pointer-events: none;
        border: 1px solid var(--ls-liquid-glass-inner-border);
        opacity: 0.42;
        z-index: 0;
    }

    .block-preset-glass .block-preset-surface > *,
    .block-preset-glass.block-preset-surface > * {
        position: relative;
        z-index: 1;
    }

    @supports not ((-webkit-backdrop-filter: blur(2px)) or (backdrop-filter: blur(2px))) {
        .block-preset-glass .block-preset-surface,
        .block-preset-glass.block-preset-surface {
            background:
                linear-gradient(155deg, rgba(241, 243, 246, 0.18) 0%, rgba(235, 238, 242, 0.118) 52%, rgba(231, 235, 239, 0.082) 100%),
                rgba(229, 233, 238, 0.165);
        }
    }

    .block-preset-bold .block-preset-surface,
    .block-preset-bold.block-preset-surface {
        background: var(--ls-block-bold-bg, rgba(255, 255, 255, 0.95));
        border: 0 solid transparent;
        box-shadow: 0 3px 14px rgba(0, 0, 0, 0.11);
        border-radius: 16px;
        -webkit-backdrop-filter: none;
        backdrop-filter: none;
    }

    .button-entrance.ls-block-entrance {
        overflow: visible;
        border-radius: 0;
        -webkit-clip-path: none;
        clip-path: none;
        padding-bottom: 0;
        margin-bottom: var(--ls-content-block-gap, 41.4px);
    }

    @media (max-width: 768px) {
        .button-entrance.ls-block-entrance {
            margin-bottom: var(--ls-content-block-gap-mobile, 36.8px);
        }
    }
</style>
<script>
(function () {
    var DARK_BOLD_BACKGROUND = 'rgba(0, 0, 0, 0.86)';
    var WHITE_THRESHOLD = 245;

    function toChannel(value) {
        var raw = (value || '').trim();
        if (raw === '') {
            return NaN;
        }

        if (raw.slice(-1) === '%') {
            var percent = Number(raw.slice(0, -1));
            if (Number.isNaN(percent)) {
                return NaN;
            }

            return Math.round(Math.max(0, Math.min(100, percent)) * 2.55);
        }

        var number = Number(raw);
        if (Number.isNaN(number)) {
            return NaN;
        }

        return Math.max(0, Math.min(255, Math.round(number)));
    }

    function parseRgbColor(value) {
        if (!value) {
            return null;
        }

        var match = value.trim().match(/^rgba?\(([^)]+)\)$/i);
        if (!match) {
            return null;
        }

        var channels = match[1].split(',');
        if (channels.length < 3) {
            return null;
        }

        var r = toChannel(channels[0]);
        var g = toChannel(channels[1]);
        var b = toChannel(channels[2]);

        if (Number.isNaN(r) || Number.isNaN(g) || Number.isNaN(b)) {
            return null;
        }

        return {r: r, g: g, b: b};
    }

    function resolveColorToken(target, token) {
        var cleanedToken = (token || '').trim();
        if (cleanedToken === '') {
            return null;
        }

        var probe = document.createElement('span');
        probe.style.position = 'absolute';
        probe.style.opacity = '0';
        probe.style.pointerEvents = 'none';
        probe.style.color = '#000000';
        probe.style.color = cleanedToken;
        if (!probe.style.color) {
            return null;
        }

        target.appendChild(probe);
        var resolved = window.getComputedStyle(probe).color;
        probe.remove();

        return parseRgbColor(resolved);
    }

    function isNearWhite(color) {
        return !!color
            && color.r >= WHITE_THRESHOLD
            && color.g >= WHITE_THRESHOLD
            && color.b >= WHITE_THRESHOLD;
    }

    function adaptBoldBackground(block) {
        var styles = window.getComputedStyle(block);
        var textToken = styles.getPropertyValue('--ls-block-text-color');
        var accentToken = styles.getPropertyValue('--ls-block-accent-color');
        var textColor = resolveColorToken(block, textToken);
        var accentColor = resolveColorToken(block, accentToken);

        var shouldUseDarkBoldBackground = false;
        if (isNearWhite(textColor)) {
            shouldUseDarkBoldBackground = true;
        } else if (!textColor && isNearWhite(accentColor)) {
            shouldUseDarkBoldBackground = true;
        }

        if (shouldUseDarkBoldBackground) {
            block.style.setProperty('--ls-block-bold-bg', DARK_BOLD_BACKGROUND);
        }
    }

    function initAdaptiveBoldBlocks() {
        document.querySelectorAll('[data-ls-adaptive-bold]').forEach(function (block) {
            adaptBoldBackground(block);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAdaptiveBoldBlocks);
    } else {
        initAdaptiveBoldBlocks();
    }
})();
</script>
@endonce
