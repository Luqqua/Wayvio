@once
<style>
  @font-face {
    font-family: 'Inter';
    font-style: normal;
    font-weight: 100 900;
    font-display: swap;
    src: url('{{ asset('assets/wayvio/fonts/Inter/inter-variable.ttf') }}') format('truetype');
  }

  @font-face {
    font-family: 'Inter';
    font-style: italic;
    font-weight: 100 900;
    font-display: swap;
    src: url('{{ asset('assets/wayvio/fonts/Inter/inter-italic-variable.ttf') }}') format('truetype');
  }

  :root {
    --wayvio-public-font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    --font: var(--wayvio-public-font-family) !important;
    --font2: var(--wayvio-public-font-family) !important;
  }

  html,
  body {
    font-family: var(--wayvio-public-font-family) !important;
  }

  body :where(*:not(#wayvio-adminbar):not(.fa):not(.fas):not(.far):not(.fal):not(.fab):not(.fa-solid):not(.fa-regular):not(.fa-brands):not(.bi):not(.logo-centered):not([class^="fa-"]):not([class*=" fa-"]):not([class^="bi-"]):not([class*=" bi-"])) {
    font-family: var(--wayvio-public-font-family) !important;
  }
</style>
@endonce
