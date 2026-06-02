<!doctype html>
@php
  $GLOBALS['themeName'] = config('advanced-config.home_theme');
  $locale = (string) app()->getLocale();
  $isGerman = str_starts_with($locale, 'de');
  $loginEnabled = Route::has('login');
  $registerEnabled = Route::has('register') && config('auth.allow_registration') && !config('linkstack.single_user_mode');

  $metaDescription = __('Build your professional business page with drag and drop - GDPR-ready, without developers or agencies.');

  $formatPrice = static function ($amount) use ($isGerman): string {
      $value = max(0, (int) $amount) / 100;
      return $isGerman
          ? number_format($value, 2, ',', '.')
          : number_format($value, 2, '.', ',');
  };

  $plans = collect(config('tiers.plans', []))->keyBy('slug');
  $orderedPlans = collect(config('tiers.order', ['free', 'basic', 'pro', 'agency']))
      ->map(fn ($slug) => $plans->get($slug))
      ->filter();


  $planNameOverrides = [
      'free' => __('Free'),
      'basic' => __('Basic'),
      'pro' => __('Pro'),
      'agency' => __('Agency'),
  ];

  $registerTarget = $registerEnabled
      ? route('register')
      : ($loginEnabled ? route('login') : url(''));

  // Language toggle URLs for the landing page
  $currentUrlBase = url()->current();
  $langToggleDE = $currentUrlBase . '?lang=de';
  $langToggleEN = $currentUrlBase . '?lang=en';

  $publicLegalLinks = legalDocumentLinks($locale);
  $legalLocale = $publicLegalLinks['locale'];
  $footerIsEn = $legalLocale === 'en';
  $footerLabels = [
      'agb' => $footerIsEn ? 'Terms' : 'AGB',
      'avv' => $footerIsEn ? 'DPA' : 'AVV',
      'privacy' => $footerIsEn ? 'Privacy' : 'Privatsphäre',
      'imprint' => $footerIsEn ? 'Imprint' : 'Impressum',
      'contact' => $footerIsEn ? 'Contact' : 'Kontakt',
      'help' => $footerIsEn ? 'Help Center' : 'Hilfecenter',
      'license' => $footerIsEn ? 'License' : 'Lizenz',
  ];
  $helpCenterUrl = $footerIsEn
      ? (Route::has('help.en.index') ? route('help.en.index') : '')
      : (Route::has('help.index') ? route('help.index') : '');
  $contactUrl = (string) ($publicLegalLinks['imprint'] ?? '');
@endphp
<html lang="{{ $locale }}">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
@if(env('CUSTOM_META_TAGS') == 'true' and config('advanced-config.title') != '')
  <title>{{ config('advanced-config.title') }}</title>
@else
  <title>{{ config('app.name') }} — {{ __('Your professional digital presence') }}</title>
@endif

<meta name="description" content="{{ strip_tags($metaDescription) }}">
<meta name="robots" content="index,follow">
<link rel="canonical" href="{{ url()->current() }}">
<meta property="og:url" content="{{ url('') }}">
<meta property="og:type" content="website">
<meta property="og:title" content="{{ env('APP_NAME') }}">
<meta property="og:locale" content="{{ $isGerman ? 'de_DE' : 'en_US' }}">
<meta property="og:description" content="{{ strip_tags($metaDescription) }}">
@if(file_exists(base_path('assets/wayvio/images/').findFile('avatar')))
  <meta property="og:image" content="{{ asset('assets/wayvio/images/'.findFile('avatar')) }}">
@else
  <meta property="og:image" content="{{ asset('assets/wayvio/images/logo.svg') }}">
@endif
<meta name="twitter:card" content="summary_large_image">
<meta property="twitter:domain" content="{{ url('') }}">
<meta property="twitter:url" content="{{ url('') }}">
<meta name="twitter:title" content="{{ env('APP_NAME') }}">
<meta name="twitter:description" content="{{ strip_tags($metaDescription) }}">
@if(file_exists(base_path('assets/wayvio/images/').findFile('avatar')))
  <meta name="twitter:image" content="{{ asset('assets/wayvio/images/'.findFile('avatar')) }}">
@else
  <meta name="twitter:image" content="{{ asset('assets/wayvio/images/logo.svg') }}">
@endif
@if(file_exists(base_path('assets/wayvio/images/').findFile('favicon')))
  <link rel="icon" type="image/png" href="{{ asset('assets/wayvio/images/'.findFile('favicon')) }}">
@else
  <link rel="icon" type="image/svg+xml" href="{{ asset('assets/wayvio/images/logo.svg') }}">
@endif

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{
    --primary:#0F3D3E;
    --accent:#14B8A6;
    --accent-deep:#0EA192;
    --bg:#F3F7F7;
    --bg-2:#E5EFEF;
    --border:#DCE5E5;
    --ink:#0C2A2A;
    --ink-2:#3A5252;
    --ink-3:#637979;
    --white:#fff;
    --radius:14px;
    --radius-lg:22px;
    --shadow-sm:0 1px 2px rgba(15,61,62,.04),0 1px 3px rgba(15,61,62,.05);
    --shadow:0 6px 24px -6px rgba(15,61,62,.10),0 2px 6px rgba(15,61,62,.05);
    --shadow-lg:0 30px 80px -20px rgba(15,61,62,.25),0 10px 30px -10px rgba(15,61,62,.15);
    --maxw:1200px;
  }
  *{box-sizing:border-box}
  html,body{margin:0;padding:0}
  body{
    font-family:'Geist','Inter',system-ui,-apple-system,sans-serif;
    color:var(--ink);
    background:var(--bg);
    -webkit-font-smoothing:antialiased;
    line-height:1.55;
    font-size:16px;
    text-wrap:pretty;
  }
  img{max-width:100%;display:block}
  a{color:inherit;text-decoration:none}
  button{font-family:inherit;cursor:pointer;border:0;background:none;color:inherit}

  .container{max-width:var(--maxw);margin:0 auto;padding:0 24px}
  @media (max-width:880px){.container{padding:0 28px}}

  /* ========== NAV ========== */
  .nav{
    position:sticky;top:0;z-index:50;
    backdrop-filter:saturate(140%) blur(14px);
    -webkit-backdrop-filter:saturate(140%) blur(14px);
    background:rgba(243,247,247,.78);
    border-bottom:1px solid rgba(220,229,229,.7);
  }
  .nav-inner{display:flex;align-items:center;justify-content:space-between;height:68px;gap:24px}
  .logo{display:flex;align-items:center;gap:10px;font-weight:700;font-size:20px;letter-spacing:-.01em;color:var(--ink)}
  .logo img{width:28px;height:28px;flex:0 0 auto;object-fit:contain}
  .nav-links{display:flex;align-items:center;gap:28px}
  .nav-links a{color:var(--ink-2);font-weight:500;font-size:15px}
  .nav-links a:hover{color:var(--ink)}
  .lang{
    display:inline-flex;align-items:center;gap:6px;
    padding:6px 10px;border:1px solid var(--border);border-radius:999px;
    font-size:13px;color:var(--ink-2);background:var(--white);font-weight:500;
  }
  .lang svg{width:12px;height:12px}
  .nav-cta{display:flex;align-items:center;gap:10px;margin-left:auto}
  .lang-toggle{
    display:inline-flex;align-items:center;border-radius:8px;
    padding:3px;gap:2px;
    border:1px solid var(--border);background:var(--bg);font-size:13px;font-weight:600;
  }
  .lang-toggle a,.lang-toggle span{
    padding:4px 10px;line-height:1;border-radius:5px;color:var(--ink-3);transition:background .12s,color .12s;
    display:inline-block;
  }
  .lang-toggle a:hover{background:var(--white);color:var(--ink)}
  .lang-toggle .active-lang{background:var(--primary);color:#fff;cursor:default}
  .lang-sep{display:none}
  .btn{
    display:inline-flex;align-items:center;justify-content:center;gap:8px;
    padding:10px 18px;border-radius:10px;font-weight:600;font-size:15px;
    transition:transform .12s ease, background .15s ease, box-shadow .15s ease, color .15s ease;
    white-space:nowrap;
  }
  .btn-ghost{color:var(--ink);border:1px solid var(--border);background:var(--white)}
  .btn-ghost:hover{background:var(--bg)}
  .btn-primary{background:var(--primary);color:#fff;box-shadow:var(--shadow-sm)}
  .btn-primary:hover{background:#0a2c2d;transform:translateY(-1px)}
  .btn-accent{background:var(--accent);color:#0a2520;box-shadow:0 8px 22px -8px rgba(20,184,166,.55)}
  .btn-accent:hover{background:var(--accent-deep);transform:translateY(-1px)}
  .btn-lg{padding:14px 24px;font-size:16px;border-radius:12px}

  @media (max-width:880px){
    .nav-inner{
      display:grid;
      grid-template-columns:1fr auto;
      grid-template-rows:auto auto;
      height:auto;padding-top:10px;padding-bottom:10px;gap:0;row-gap:8px;
    }
    .logo{grid-column:1;grid-row:1;align-self:center}
    .lang-toggle{grid-column:2;grid-row:1;align-self:center;margin-left:0}
    .nav-links{display:none}
    .nav-cta{
      grid-column:1/3;grid-row:2;
      justify-content:flex-end;gap:8px;margin-left:0;
    }
    .nav-cta .btn{padding:9px 14px;font-size:14px}
  }

  /* ========== HERO ========== */
  .hero{
    position:relative;overflow:hidden;
    padding:72px 0 40px;
    background:
      radial-gradient(1100px 500px at 85% -10%,rgba(20,184,166,.18),transparent 60%),
      radial-gradient(700px 380px at -10% 30%,rgba(15,61,62,.07),transparent 60%),
      linear-gradient(180deg,var(--bg) 0%,#EAF2F2 100%);
  }
  .hero-grid{
    display:grid;grid-template-columns:1.1fr 1fr;gap:56px;align-items:center;
  }
  .eyebrow{
    display:inline-flex;align-items:center;gap:8px;
    padding:6px 12px 6px 8px;border-radius:999px;
    background:rgba(20,184,166,.12);color:#0a655a;
    font-size:13px;font-weight:600;letter-spacing:.02em;
    border:1px solid rgba(20,184,166,.25);
  }
  .eyebrow .dot{width:8px;height:8px;border-radius:999px;background:var(--accent);box-shadow:0 0 0 3px rgba(20,184,166,.25)}
  h1.hero-title{
    font-size:clamp(40px,5.6vw,68px);
    line-height:1.02;letter-spacing:-.025em;font-weight:700;
    margin:18px 0 18px;color:var(--primary);
  }
  h1.hero-title .accent{color:var(--accent-deep)}
  h1.hero-title em{font-style:normal;background:linear-gradient(120deg,var(--accent) 0%,#0a655a 100%);-webkit-background-clip:text;background-clip:text;color:transparent}
  .hero-sub{font-size:19px;color:var(--ink-2);max-width:560px;margin:0 0 30px}
  .hero-cta{display:flex;gap:12px;flex-wrap:wrap;align-items:center}
  .hero-meta{display:flex;align-items:center;gap:18px;margin-top:24px;color:var(--ink-3);font-size:13px;flex-wrap:wrap}
  .hero-meta .chip{display:inline-flex;gap:6px;align-items:center}
  .hero-meta .chip svg{width:14px;height:14px;color:var(--accent-deep)}

  .hero-visual{
    position:relative;justify-self:end;width:100%;max-width:480px;
    aspect-ratio:4/5;
  }
  .blob{
    position:absolute;inset:0;border-radius:42% 58% 50% 50%/45% 45% 55% 55%;
    background:linear-gradient(140deg,#9FE5DC 0%,#5BC9BB 60%,#14B8A6 100%);
    filter:blur(2px);opacity:.7;transform:scale(1.08) translate(2%,-2%);
  }
  .blob:after{
    content:"";position:absolute;inset:0;border-radius:inherit;
    background:radial-gradient(60% 50% at 30% 20%,rgba(255,255,255,.6),transparent 60%);
    mix-blend-mode:overlay;
  }
  .portrait{
    position:absolute;inset:0;
    border-radius:24px;overflow:hidden;
    background:#fff;
    box-shadow:var(--shadow-lg),0 0 0 1px rgba(15,61,62,.06);
    transform:rotate(-1.5deg);
  }
  .portrait img{width:100%;height:100%;object-fit:cover;object-position:top center;display:block}
  .float-chip{
    position:absolute;background:#fff;border:1px solid var(--border);
    border-radius:14px;padding:10px 12px;display:flex;align-items:center;gap:10px;
    box-shadow:var(--shadow-lg);font-size:13px;font-weight:500;color:var(--ink);
  }
  .float-chip .ico{
    width:32px;height:32px;border-radius:9px;display:grid;place-items:center;
    background:rgba(20,184,166,.12);color:var(--accent-deep);
  }
  .float-chip .ico svg{width:16px;height:16px}
  .float-chip small{display:block;color:var(--ink-3);font-size:11px;font-weight:500;margin-top:2px}
  .chip-1{top:8%;left:-6%}
  .chip-2{bottom:18%;right:-8%}
  .chip-3{bottom:-2%;left:8%}

  @media (max-width:980px){
    .hero{padding-top:48px}
    .hero-grid{grid-template-columns:1fr;gap:40px}
    .hero-visual{justify-self:center;max-width:420px}
    .chip-1{left:-2%}
    .chip-2{right:-2%}
  }

  /* ========== Section base ========== */
  section.block{padding:96px 0}
  .section-head{text-align:center;max-width:760px;margin:0 auto 56px}
  .section-eyebrow{
    text-transform:uppercase;letter-spacing:.14em;font-size:12px;font-weight:600;
    color:var(--accent-deep);margin-bottom:14px;
  }
  .section-title{
    font-size:clamp(30px,3.8vw,46px);line-height:1.08;letter-spacing:-.02em;
    font-weight:700;color:var(--primary);margin:0 0 14px;
  }
  .section-sub{font-size:18px;color:var(--ink-2);margin:0}

  /* ========== POSITIONING SCALE ========== */
  .scale-wrap{
    background:#fff;border:1px solid var(--border);border-radius:var(--radius-lg);
    padding:48px 40px 56px;box-shadow:var(--shadow-sm);
  }
  .scale{
    position:relative;margin:36px auto 0;max-width:980px;
  }
  .scale-line{
    position:relative;height:6px;border-radius:999px;
    background:linear-gradient(90deg,#F0C8C0 0%,#FAE3CB 25%,#C8EFE7 50%,#FAE3CB 75%,#F0C8C0 100%);
  }
  .scale-mark{
    position:absolute;top:50%;transform:translate(-50%,-50%);
    width:18px;height:18px;border-radius:999px;background:#fff;
    border:3px solid var(--ink-3);box-shadow:0 0 0 4px #fff;
  }
  .scale-mark.center{
    width:28px;height:28px;background:var(--accent);border-color:var(--accent);
    box-shadow:0 0 0 6px #fff,0 8px 20px -4px rgba(20,184,166,.55);
  }
  .scale-labels{
    display:grid;grid-template-columns:1fr 1fr 1fr;gap:24px;margin-top:48px;
  }
  .scale-label{text-align:center;padding:0 12px}
  .scale-label.center .name{color:var(--primary);font-size:22px}
  .scale-label .name{
    font-weight:700;font-size:18px;letter-spacing:-.01em;margin-bottom:6px;color:var(--ink-2);
  }
  .scale-label .desc{font-size:14px;color:var(--ink-3);max-width:240px;margin:0 auto}
  .scale-label.center .desc{color:var(--ink-2)}
  .scale-foot{
    text-align:center;max-width:680px;margin:48px auto 0;color:var(--ink-2);font-size:17px;
  }

  /* vertical scale — mobile only */
  .scale-vert{display:none}
  @media (max-width:720px){
    .scale-wrap{padding:28px 20px 36px}
    .scale,.scale-labels{display:none}
    .scale-foot{font-size:15px;margin-top:28px}
    .scale-vert{
      display:grid;grid-template-columns:28px 1fr;column-gap:16px;row-gap:36px;
      position:relative;padding:20px 0;
    }
    .scale-vert::before{
      content:'';position:absolute;left:11px;width:6px;top:0;bottom:0;
      background:linear-gradient(180deg,#F0C8C0 0%,#FAE3CB 25%,#C8EFE7 50%,#FAE3CB 75%,#F0C8C0 100%);
    }
    .sv-dot-wrap{display:flex;justify-content:center;align-items:flex-start;position:relative;z-index:1}
    .sv-dot{
      width:16px;height:16px;border-radius:50%;flex-shrink:0;
      border:3px solid var(--ink-3);background:#fff;margin-top:2px;
    }
    .sv-dot.center{
      width:26px;height:26px;margin-top:0;
      background:var(--accent);border-color:var(--accent);
      box-shadow:0 6px 16px -4px rgba(20,184,166,.55);
    }
    .sv-label .name{font-weight:700;font-size:16px;color:var(--ink-2);margin-bottom:4px;letter-spacing:-.01em}
    .sv-label.center .name{color:var(--primary);font-size:18px}
    .sv-label .desc{font-size:14px;color:var(--ink-3)}
    .sv-label.center .desc{color:var(--ink-2)}
  }

  /* ========== AUDIENCE CARDS ========== */
  .audience-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px}
  .a-card{
    background:#fff;border:1px solid var(--border);border-radius:var(--radius-lg);
    padding:28px;display:flex;flex-direction:column;gap:14px;transition:transform .15s ease, box-shadow .15s ease, border-color .15s;
  }
  .a-card:hover{transform:translateY(-3px);box-shadow:var(--shadow);border-color:#cfe1de}
  .a-card .badge{
    width:44px;height:44px;border-radius:12px;display:grid;place-items:center;
    background:var(--bg);color:var(--primary);
  }
  .a-card .badge svg{width:22px;height:22px}
  .a-card h3{margin:0;font-size:20px;color:var(--primary);letter-spacing:-.01em}
  .a-card p{margin:0;color:var(--ink-2);font-size:15px}
  .a-card ul{margin:8px 0 0;padding:0;list-style:none;display:flex;flex-wrap:wrap;gap:6px}
  .a-card ul li{
    font-size:12px;font-weight:500;color:var(--ink-2);
    background:var(--bg);padding:5px 10px;border-radius:999px;border:1px solid var(--border);
  }
  @media (max-width:880px){.audience-grid{grid-template-columns:1fr}}

  /* ========== SHOWCASE ========== */
  .showcase{
    background:linear-gradient(180deg,#0F3D3E 0%,#0a2c2d 100%);
    color:#dceeec;border-radius:0;
    padding:96px 0;
    position:relative;overflow:hidden;
  }
  .showcase:before{
    content:"";position:absolute;inset:0;
    background:
      radial-gradient(900px 400px at 90% 10%,rgba(20,184,166,.25),transparent 60%),
      radial-gradient(700px 400px at 0% 100%,rgba(20,184,166,.15),transparent 60%);
    pointer-events:none;
  }
  .showcase .section-eyebrow{color:#7eddcb}
  .showcase .section-title{color:#fff}
  .showcase .section-sub{color:#bcd6d2}
  .showcase-tabs{
    display:flex;flex-wrap:wrap;justify-content:center;gap:8px;margin:0 auto 40px;max-width:880px
  }
  .showcase-tab{
    padding:10px 18px;border-radius:999px;font-size:14px;font-weight:500;
    color:#bcd6d2;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);
    transition:all .15s;
  }
  .showcase-tab:hover{color:#fff;background:rgba(255,255,255,.08)}
  .showcase-tab.active{background:var(--accent);color:#0a2520;border-color:var(--accent);font-weight:600}
  .showcase-stage{
    position:relative;margin:0 auto;max-width:1100px;
    display:grid;grid-template-columns:1fr 1.1fr;gap:48px;align-items:center;
  }
  .showcase-info h3{
    font-size:32px;letter-spacing:-.02em;margin:0 0 12px;color:#fff;font-weight:700;
  }
  .showcase-info .url{
    display:inline-flex;align-items:center;gap:6px;
    font-family:'Geist Mono',monospace;font-size:13px;color:#7eddcb;
    background:rgba(20,184,166,.12);padding:5px 10px;border-radius:8px;
    margin-bottom:16px;
  }
  .showcase-info p{font-size:16px;color:#bcd6d2;margin:0 0 22px;max-width:440px}
  .showcase-info .tags{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:24px}
  .showcase-info .tags span{
    font-size:12px;color:#cfe5e1;border:1px solid rgba(255,255,255,.12);
    padding:5px 10px;border-radius:999px;
  }
  .showcase-card-wrap{
    position:relative;justify-self:center;
    width:100%;max-width:420px;aspect-ratio:4/5;
  }
  .showcase-card{
    position:absolute;inset:0;
    background:#fff;border-radius:20px;overflow:hidden;
    box-shadow:var(--shadow-lg),0 60px 120px -40px rgba(20,184,166,.4);
    transform:translateZ(0);
  }
  .showcase-screen{
    width:100%;height:100%;overflow:hidden;background:#fff;position:relative;
  }
  .showcase-screen img{width:100%;height:100%;object-fit:contain;object-position:center;display:block;transition:opacity .25s ease;background:#fff}

  @media (max-width:880px){
    .showcase-stage{grid-template-columns:1fr;gap:24px;text-align:center}
    .showcase-info p{margin-left:auto;margin-right:auto}
    .showcase-info .tags,.showcase-info .url{justify-content:center}
    .showcase-card-wrap{max-width:340px}
  }

  /* ========== DSGVO ========== */
  .dsgvo{
    background:#fff;border:1px solid var(--border);border-radius:var(--radius-lg);
    padding:56px;display:grid;grid-template-columns:.9fr 1.1fr;gap:48px;align-items:center;
    box-shadow:var(--shadow-sm);
  }
  .dsgvo-side .seal{
    display:inline-flex;align-items:center;gap:10px;
    padding:8px 14px;border-radius:999px;
    background:#FEF7E6;color:#8B6B0F;border:1px solid #F4E4B0;
    font-size:13px;font-weight:600;
  }
  .dsgvo-side h2{font-size:36px;letter-spacing:-.02em;margin:18px 0 14px;color:var(--primary);line-height:1.1}
  .dsgvo-side p{color:var(--ink-2);font-size:16px;margin:0}
  .dsgvo-list{list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:14px}
  .dsgvo-item{
    display:flex;gap:16px;padding:18px;border-radius:14px;
    background:var(--bg);border:1px solid var(--border);
  }
  .dsgvo-item .ico{
    flex:0 0 auto;width:42px;height:42px;border-radius:11px;
    background:var(--primary);color:#fff;display:grid;place-items:center;
  }
  .dsgvo-item .ico svg{width:20px;height:20px}
  .dsgvo-item h4{margin:0 0 4px;font-size:16px;color:var(--primary)}
  .dsgvo-item p{margin:0;font-size:14px;color:var(--ink-2)}
  @media (max-width:880px){
    .dsgvo{grid-template-columns:1fr;padding:32px}
    .dsgvo-side h2{font-size:30px}
  }

  /* ========== FEATURES ========== */
  .feat-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:20px}
  .feat{
    background:#fff;border:1px solid var(--border);border-radius:var(--radius-lg);
    padding:32px;display:flex;flex-direction:column;gap:14px;
    position:relative;overflow:hidden;
  }
  .feat.dark{background:var(--primary);color:#cfe5e1;border-color:var(--primary)}
  .feat.dark h3{color:#fff}
  .feat.dark .feat-tags span{background:rgba(255,255,255,.08);color:#cfe5e1;border-color:rgba(255,255,255,.12)}
  .feat .ico{
    width:46px;height:46px;border-radius:12px;display:grid;place-items:center;
    background:var(--bg);color:var(--primary);
  }
  .feat.dark .ico{background:rgba(20,184,166,.18);color:#7eddcb}
  .feat .ico svg{width:22px;height:22px}
  .feat h3{margin:0;font-size:22px;color:var(--primary);letter-spacing:-.01em}
  .feat p{margin:0;color:var(--ink-2);font-size:15px}
  .feat.dark p{color:#bcd6d2}
  .feat-tags{display:flex;flex-wrap:wrap;gap:6px;margin-top:6px}
  .feat-tags span{
    font-size:12px;font-weight:500;color:var(--ink-2);
    background:var(--bg);padding:5px 10px;border-radius:999px;border:1px solid var(--border);
  }
  .feat.wide{grid-column:1/-1;flex-direction:row;align-items:center;gap:40px}
  .feat.wide .feat-main{flex:1;display:flex;flex-direction:column;gap:14px}
  .feat.wide .feat-stat{
    flex-shrink:0;text-align:center;background:var(--bg);border:1px solid var(--border);
    border-radius:var(--radius-lg);padding:28px 36px;
  }
  .feat.wide .feat-stat .stat-num{font-size:52px;font-weight:800;color:var(--primary);line-height:1;letter-spacing:-.03em}
  .feat.wide .feat-stat .stat-label{font-size:13px;color:var(--ink-3);margin-top:6px;max-width:120px}
  @media (max-width:780px){
    .feat-grid{grid-template-columns:1fr}
    .feat.wide{flex-direction:column;align-items:stretch}
    .feat.wide .feat-stat{text-align:left;display:flex;align-items:center;gap:16px;padding:20px 24px}
    .feat.wide .feat-stat .stat-label{margin-top:0;max-width:none}
  }

  /* ========== EMBEDS CALLOUT ========== */
  .embeds{
    background:#fff;border:1px solid var(--border);border-radius:var(--radius-lg);
    padding:36px 40px;margin-top:24px;
    display:grid;grid-template-columns:1.4fr 1fr;gap:32px;align-items:center;
  }
  .embeds h3{margin:0 0 8px;font-size:22px;color:var(--primary);letter-spacing:-.01em}
  .embeds p{margin:0;color:var(--ink-2);font-size:15px}
  .embeds-logos{display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-end}
  .elogo{
    display:inline-flex;align-items:center;gap:8px;
    padding:8px 14px;border-radius:10px;
    background:var(--bg);border:1px solid var(--border);
    font-size:13px;font-weight:600;color:var(--ink);
  }
  .elogo svg{width:16px;height:16px}
  @media (max-width:780px){
    .embeds{grid-template-columns:1fr;padding:28px}
    .embeds-logos{justify-content:flex-start}
  }

  /* ========== PRICING ========== */
  .pricing{background:#fff;padding:96px 0;border-top:1px solid var(--border)}
  .price-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
  .price{
    background:#fff;border:1px solid var(--border);border-radius:var(--radius-lg);
    padding:28px;display:flex;flex-direction:column;gap:18px;position:relative;
  }
  .price.featured{
    background:var(--primary);color:#cfe5e1;border-color:var(--primary);
    transform:translateY(-8px);box-shadow:var(--shadow-lg);
  }
  .price.featured .price-name{color:#7eddcb;background:rgba(20,184,166,.18);border-color:transparent}
  .price.featured .price-amount{color:#fff}
  .price.featured .price-per{color:#bcd6d2}
  .price.featured .price-list li{color:#cfe5e1}
  .price.featured .price-list li svg{color:var(--accent)}
  .price.featured .btn-price{background:var(--accent);color:#0a2520}
  .price.featured .btn-price:hover{background:#0EA192}
  .price-tag{
    position:absolute;top:-12px;right:18px;
    background:var(--accent);color:#0a2520;font-size:11px;font-weight:700;
    padding:5px 10px;border-radius:999px;letter-spacing:.04em;text-transform:uppercase;
  }
  .price-name{
    align-self:flex-start;
    font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;
    padding:5px 10px;border-radius:8px;background:var(--bg-2);color:var(--ink-2);
    border:1px solid var(--border);
  }
  .price-amount{font-size:42px;font-weight:700;letter-spacing:-.02em;color:var(--primary);line-height:1}
  .price-amount sup{font-size:18px;font-weight:600;vertical-align:0;margin-right:2px}
  .price-per{font-size:13px;color:var(--ink-3);margin-top:6px}
  .price-desc{font-size:14px;color:var(--ink-2);margin:0;min-height:42px}
  .price-list{list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:10px;flex:1}
  .price-list li{display:flex;gap:10px;font-size:14px;color:var(--ink-2);align-items:flex-start}
  .price-list li svg{width:16px;height:16px;color:var(--accent-deep);flex:0 0 auto;margin-top:3px}
  .btn-price{
    width:100%;padding:12px;border-radius:10px;font-weight:600;font-size:14px;
    background:var(--bg);color:var(--ink);border:1px solid var(--border);transition:all .15s;
    display:block;text-align:center;text-decoration:none;
  }
  .btn-price:hover{background:var(--bg-2)}
  .btn-price.dark{background:var(--primary);color:#fff;border-color:var(--primary)}
  .btn-price.dark:hover{background:#0a2c2d}
  .partner-link{text-align:center;margin-top:32px;font-size:14px;color:var(--ink-3)}
  .partner-link a{color:var(--accent-deep);font-weight:600;text-decoration:underline;text-decoration-color:rgba(14,161,146,.3);text-underline-offset:3px}

  @media (max-width:980px){.price-grid{grid-template-columns:repeat(2,1fr)} .price.featured{transform:none}}
  @media (max-width:540px){.price-grid{grid-template-columns:1fr}}

  /* ========== TRUST ========== */
  .trust{background:var(--bg);padding:72px 0;border-top:1px solid var(--border)}
  .trust-grid{
    display:grid;grid-template-columns:repeat(6,1fr);gap:14px;max-width:1100px;margin:0 auto;
  }
  .trust-item{
    background:#fff;border:1px solid var(--border);border-radius:14px;
    padding:18px 16px;display:flex;flex-direction:column;gap:10px;align-items:flex-start;
  }
  .trust-item .ico{
    width:36px;height:36px;border-radius:10px;display:grid;place-items:center;
    background:var(--bg);color:var(--accent-deep);
  }
  .trust-item .ico svg{width:18px;height:18px}
  .trust-item span{font-size:13px;font-weight:600;color:var(--primary);line-height:1.3}
  @media (max-width:980px){.trust-grid{grid-template-columns:repeat(3,1fr)}}
  @media (max-width:540px){.trust-grid{grid-template-columns:repeat(2,1fr)}}

  /* ========== CTA FOOTER ========== */
  .cta-foot{
    background:linear-gradient(140deg,#0F3D3E 0%,#0a2c2d 60%,#114443 100%);
    color:#fff;padding:96px 0;position:relative;overflow:hidden;
  }
  .cta-foot:before{
    content:"";position:absolute;inset:0;
    background:
      radial-gradient(700px 400px at 100% 0%,rgba(20,184,166,.3),transparent 60%),
      radial-gradient(500px 300px at 0% 100%,rgba(20,184,166,.15),transparent 60%);
    pointer-events:none;
  }
  .cta-inner{position:relative;text-align:center;max-width:780px;margin:0 auto}
  .cta-inner h2{font-size:clamp(34px,4.6vw,56px);letter-spacing:-.02em;line-height:1.05;margin:0 0 16px;color:#fff}
  .cta-inner p{font-size:18px;color:#bcd6d2;margin:0 0 28px}
  .cta-inner .hero-meta{justify-content:center;color:#bcd6d2}
  .cta-inner .hero-meta svg{color:var(--accent)}

  /* ========== FOOTER ========== */
  footer.site-footer{
    background:var(--bg);padding:48px 0 32px;border-top:1px solid var(--border);
    color:var(--ink-3);font-size:14px;
  }
  .footer-inner{display:flex;justify-content:space-between;gap:24px;flex-wrap:wrap;align-items:center}
  .footer-links{display:flex;gap:20px;flex-wrap:wrap}
  .footer-links a:hover{color:var(--ink)}

  .reveal{opacity:0;transform:translateY(14px);transition:all .8s cubic-bezier(.2,.8,.2,1)}
  .reveal.visible{opacity:1;transform:none}
</style>
</head>
<body>

<!-- ========== NAV ========== -->
<header class="nav">
  <div class="container nav-inner">
    <a href="{{ url('') }}" class="logo">
      <img src="{{ asset('assets/landingpage/logo_color.svg') }}" alt="" aria-hidden="true" />
      WAYVIO
    </a>
    <nav class="nav-links">
      <a href="#showcase">{{ __('Examples') }}</a>
      <a href="#features">{{ __('Features') }}</a>
      <a href="#pricing">{{ __('Pricing') }}</a>
      @if(Route::has('help.index'))
        <a href="{{ route('help.index') }}">{{ __('Help center') }}</a>
      @endif
    </nav>
    <div class="lang-toggle" aria-label="{{ $isGerman ? 'Sprache' : 'Language' }}">
      @if($isGerman)
        <span class="active-lang">DE</span>
        <div class="lang-sep"></div>
        <a href="{{ $langToggleEN }}">EN</a>
      @else
        <a href="{{ $langToggleDE }}">DE</a>
        <div class="lang-sep"></div>
        <span class="active-lang">EN</span>
      @endif
    </div>
    <div class="nav-cta">
      @if($loginEnabled)
        <a href="{{ route('login') }}" class="btn btn-ghost">{{ __('Sign in') }}</a>
      @endif
      @if($registerEnabled)
        <a href="{{ $registerTarget }}" class="btn btn-primary">{{ __('Register') }}</a>
      @endif
    </div>
  </div>
</header>

<!-- ========== HERO ========== -->
<section class="hero">
  <div class="container hero-grid">
    <div>
      <span class="eyebrow"><span class="dot"></span>{{ __('GDPR-ready · Made for DACH') }}</span>
      <h1 class="hero-title">{{ __('Your professional') }}<br>{{ __('digital presence') }} — <em>{{ __('without') }}</em> {{ __('the effort of a website.') }}</h1>
      <p class="hero-sub">{{ __('The all-in-one solution for your digital presence — out of the box, without complex website programming or hours of research. Pre-made templates and a pure block-stacking mechanism: select blocks, stack them on top of each other, done. Live in 10 minutes.') }}</p>
      <div class="hero-cta">
        <a href="{{ $registerTarget }}" class="btn btn-accent btn-lg">
          {{ __('Start for free') }}
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </a>
        <a href="#showcase" class="btn btn-ghost btn-lg">{{ __('View examples') }}</a>
      </div>
      <div class="hero-meta">
        <span class="chip">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
          {{ __('Hosting in Germany') }}
        </span>
        <span class="chip">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
          {{ __('Pre-made templates') }}
        </span>
        <span class="chip">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
          {{ __('No coding required') }}
        </span>
      </div>
    </div>

    <div class="hero-visual">
      <div class="blob"></div>
      <div class="portrait">
        <img
          src="{{ asset('assets/landingpage/example_wayvio_hero.webp') }}"
          alt="{{ __('Wayvio preview') }}"
          width="1480"
          height="1560"
          fetchpriority="high"
          decoding="async"
        />
      </div>

      <div class="float-chip chip-1">
        <span class="ico">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        </span>
        <div>{{ __('Imprint') }}<small>{{ __('Auto-generated') }}</small></div>
      </div>

      <div class="float-chip chip-2" style="margin:0 18px 0 0">
        <span class="ico">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 010 18M12 3a14 14 0 000 18"/></svg>
        </span>
        <div>yourname.de<small>{{ __('Custom domain') }}</small></div>
      </div>

      <div class="float-chip chip-3">
        <span class="ico">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        </span>
        <div>+184 {{ __('Clicks') }}<small>{{ __('this week') }}</small></div>
      </div>
    </div>
  </div>
</section>

<!-- ========== POSITIONING ========== -->
<section class="block">
  <div class="container">
    <div class="section-head">
      <div class="section-eyebrow">{{ __('Positioning') }}</div>
      <h2 class="section-title">{{ __('Exactly the right middle.') }}</h2>
      <p class="section-sub">{{ __('Between "too simple" and "too complex" — Wayvio is the all-in-one solution that works out of the box. No web designer, no coding, no hours of research.') }}</p>
    </div>

    <div class="scale-wrap">
      <div class="scale">
        <div class="scale-line"></div>
        <span class="scale-mark" style="left:8%"></span>
        <span class="scale-mark center" style="left:50%"></span>
        <span class="scale-mark" style="left:92%"></span>
      </div>
      <div class="scale-labels">
        <div class="scale-label">
          <div class="name">Linktree</div>
          <div class="desc">{{ __('Too simple, no imprint workflow, no legal setup support.') }}</div>
        </div>
        <div class="scale-label center">
          <div class="name">{{ config('app.name') }} — {{ __('Just right') }}</div>
          <div class="desc">{{ __('Professional, legally prepared, online in 10 minutes.') }}</div>
        </div>
        <div class="scale-label">
          <div class="name">{{ __('Own website') }}</div>
          <div class="desc">{{ __('Too complex, too expensive, too much effort.') }}</div>
        </div>
      </div>
      <div class="scale-vert">
        <div class="sv-dot-wrap"><span class="sv-dot"></span></div>
        <div class="sv-label">
          <div class="name">Linktree</div>
          <div class="desc">{{ __('Too simple, no imprint workflow, no legal setup support.') }}</div>
        </div>
        <div class="sv-dot-wrap"><span class="sv-dot center"></span></div>
        <div class="sv-label center">
          <div class="name">{{ config('app.name') }} — {{ __('Just right') }}</div>
          <div class="desc">{{ __('Professional, legally prepared, online in 10 minutes.') }}</div>
        </div>
        <div class="sv-dot-wrap"><span class="sv-dot"></span></div>
        <div class="sv-label">
          <div class="name">{{ __('Own website') }}</div>
          <div class="desc">{{ __('Too complex, too expensive, too much effort.') }}</div>
        </div>
      </div>

      <p class="scale-foot">{{ __('Wayvio is for everyone who wants to be professionally online — with support for legal setup and without the effort of their own website.') }}</p>
    </div>
  </div>
</section>

<!-- ========== AUDIENCE ========== -->
<section class="block" style="padding-top:0">
  <div class="container">
    <div class="section-head">
      <div class="section-eyebrow">{{ __('Who is Wayvio for') }}</div>
      <h2 class="section-title">{{ __('For everyone who wants a professional online presence.') }}</h2>
      <p class="section-sub">{{ __('You start from a ready-made template, adjust the blocks and go live. Analytics and GDPR-ready included.') }}</p>
    </div>

    <div class="audience-grid">
      <article class="a-card">
        <div class="badge">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V8l7-5 7 5v13"/><path d="M9 21v-6h6v6"/></svg>
        </div>
        <h3>{{ __('Local businesses') }}</h3>
        <p>{{ __('Restaurants, cafés, tradespeople, practices — all who need to be professionally online without IT effort. Opening hours, maps, menu, imprint and GDPR — all included.') }}</p>
        <ul>
          <li>Maps</li><li>{{ __('Opening hours') }}</li><li>{{ __('Menu') }}</li><li>{{ __('Imprint') }}</li>
        </ul>
      </article>

      <article class="a-card">
        <div class="badge">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="5"/><path d="M3 21a9 9 0 0118 0"/></svg>
        </div>
        <h3>{{ __('Freelancers & Creatives') }}</h3>
        <p>{{ __('Photographers, designers, consultants — a professional presence that shows your work. Portfolio links, booking, social media, custom domain.') }}</p>
        <ul>
          <li>{{ __('Portfolio') }}</li><li>{{ __('Booking') }}</li><li>Socials</li><li>{{ __('Custom domain') }}</li>
        </ul>
      </article>

      <article class="a-card">
        <div class="badge">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18"/><path d="M8 14l2 2 4-4"/></svg>
        </div>
        <h3>{{ __('For agencies') }}</h3>
        <p>{{ __('Your domain. Your logo. Your price. Create white-label presences for every client – individually or centrally branded – and manage everything in one place.') }}</p>
        <ul>
          <li>White-Label</li><li>{{ __('Multi-client') }}</li><li>Upsell</li><li>Branding</li>
        </ul>
      </article>
    </div>
  </div>
</section>

<!-- ========== SHOWCASE ========== -->
<section class="showcase" id="showcase">
  <div class="container">
    <div class="section-head">
      <div class="section-eyebrow">Showcase</div>
      <h2 class="section-title">{{ __('Real examples — created in under 10 minutes.') }}</h2>
      <p class="section-sub">{{ __('From café to marketing agency. Switch between the templates and see what is possible with Wayvio.') }}</p>
    </div>

    <div class="showcase-tabs" id="showcaseTabs"></div>

    <div class="showcase-stage">
      <div class="showcase-info" id="showcaseInfo"></div>
      <div class="showcase-card-wrap">
        <div class="showcase-card">
          <div class="showcase-screen">
            <img
              id="showcaseImg"
              src="{{ asset('assets/landingpage/example_handwerk.webp') }}"
              alt="{{ __('Example') }}"
              width="1248"
              height="1560"
              loading="lazy"
              decoding="async"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ========== DSGVO ========== -->
<section class="block">
  <div class="container">
    <div class="dsgvo">
      <div class="dsgvo-side">
        <span class="seal">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
          Made &amp; built in Germany
        </span>
        <h2>{{ __('Built for the legal requirements in Germany — from the start.') }}</h2>
        <p>{{ __('Built specifically for the German market. We provide you with tools for a legally prepared online presence — without complex research or programming.') }}</p>
        <p style="font-size:13px;color:var(--ink-3);margin-top:14px;line-height:1.5"><strong style="color:var(--ink-2);font-weight:600">{{ __('Note:') }}</strong> {{ __('The tools are a support. Wayvio assumes no liability for completeness or accuracy — the final review and verification of your information is your responsibility.') }}</p>
      </div>
      <ul class="dsgvo-list">
        <li class="dsgvo-item">
          <span class="ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>
          </span>
          <div><h4>{{ __('Imprint generator') }}</h4><p>{{ __('Your imprint is automatically displayed on your page — based on your information.') }}</p></div>
        </li>
        <li class="dsgvo-item">
          <span class="ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12c0-5 4-9 9-9s9 4 9 9-4 9-9 9-9-4-9-9z"/><path d="M9 12l2 2 4-4"/></svg>
          </span>
          <div><h4>{{ __('GDPR cookie consent') }}</h4><p>{{ __('All third-party services are only loaded after explicit consent.') }}</p></div>
        </li>
        <li class="dsgvo-item">
          <span class="ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H6a2 2 0 00-2 2v14a2 2 0 002 2h12a2 2 0 002-2V9z"/><polyline points="14 3 14 9 20 9"/></svg>
          </span>
          <div><h4>{{ __('Privacy policy') }}</h4><p>{{ __('Automatically generated — based on the features you actually use.') }}</p></div>
        </li>
      </ul>
    </div>
  </div>
</section>

<!-- ========== FEATURES ========== -->
<section class="block" id="features" style="padding-top:0">
  <div class="container">
    <div class="section-head">
      <div class="section-eyebrow">{{ __('Feature highlights') }}</div>
      <h2 class="section-title">{{ __('Everything your presence needs.') }}</h2>
      <p class="section-sub">{{ __('Four building blocks that together make a complete digital presence.') }}</p>
    </div>

    <div class="feat-grid">
      <article class="feat">
        <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg></div>
        <h3>{{ __('Everything you need') }}</h3>
        <p>{{ __('Building blocks for every use case — assembled with the block-stack editor, no code. Including meta data and technical SEO adjustments per page.') }}</p>
        <div class="feat-tags">
          <span>Maps</span><span>{{ __('Opening hours') }}</span><span>{{ __('Menu') }}</span><span>Social Proof</span><span>Embeds</span><span>vCard</span><span>QR-Code</span><span>SEO &amp; Meta</span>
        </div>
      </article>

      <article class="feat dark">
        <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M2 12h20M12 2c2.5 3 4 6 4 10s-1.5 7-4 10c-2.5-3-4-6-4-10S9.5 5 12 2z"/></svg></div>
        <h3>{{ __('Your design') }}</h3>
        <p>{{ __('Pre-made templates as a starting point, customizable in detail — without a web designer. Not a classic editor, but a pure block-stacking mechanism: choose blocks, stack in the desired order, done.') }}</p>
        <div class="feat-tags">
          <span>{{ __('Templates') }}</span><span>Block-Stack</span><span>Drag &amp; Stack</span><span>{{ __('Custom colors') }}</span><span>{{ __('Custom domain') }}</span><span>{{ __('No Wayvio branding') }}</span>
        </div>
      </article>

      <article class="feat">
        <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><polyline points="7 14 11 10 14 13 21 6"/></svg></div>
        <h3>{{ __('Your data') }}</h3>
        <p>{{ __('Analytics, clicks, visitors — you see what works. Privacy-friendly, without third-party cookies.') }}</p>
        <div class="feat-tags">
          <span>Live-Analytics</span><span>{{ __('Clicks per link') }}</span><span>UTM</span><span>Export</span>
        </div>
      </article>

      <article class="feat">
        <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M3 21a6 6 0 0112 0"/><path d="M14 21a5 5 0 017-4.6"/></svg></div>
        <h3>{{ __('For agencies') }}</h3>
        <p>{{ __('Manage multiple clients centrally. White-label, easy upsell, clear margin.') }}</p>
        <div class="feat-tags">
          <span>{{ __('Multi-client') }}</span><span>White-Label</span><span>Branding</span><span>{{ __('Upsell-ready') }}</span>
        </div>
      </article>

      <article class="feat wide">
        <div class="feat-main">
          <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="7" y="2" width="10" height="20" rx="2"/><line x1="12" y1="18" x2="12" y2="18.01"/></svg></div>
          <h3>{{ __('Mobile-optimised — automatically') }}</h3>
          <p>{{ __('The majority of all web traffic today comes from smartphones. All Wayvio pages are built responsively from the ground up — no extra effort, no plugin, no rework. Your visitors get a perfect experience on every device.') }}</p>
          <div class="feat-tags">
            <span>{{ __('Responsive design') }}</span><span>Mobile First</span><span>{{ __('All devices') }}</span><span>{{ __('No extra work') }}</span>
          </div>
        </div>
        <div class="feat-stat">
          <div class="stat-num">&gt;60&nbsp;%</div>
          <div class="stat-label">{{ __('of all website visits come from mobile') }}</div>
        </div>
      </article>
    </div>

    <div class="embeds">
      <div>
        <h3>{{ __('Custom contact forms & embeds — out of the box.') }}</h3>
        <p>{{ __('Native forms without third-party services, plus embeds for Instagram, YouTube, Spotify, Google Maps, Calendly and many more — designed for privacy-conscious use with a consent layer. Out of the box, without custom programming.') }}</p>
      </div>
      <div class="embeds-logos">
        <span class="elogo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor"/></svg>Instagram</span>
        <span class="elogo"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M21.6 7.2a2.5 2.5 0 00-1.8-1.8C18.3 5 12 5 12 5s-6.3 0-7.8.4A2.5 2.5 0 002.4 7.2C2 8.7 2 12 2 12s0 3.3.4 4.8a2.5 2.5 0 001.8 1.8c1.5.4 7.8.4 7.8.4s6.3 0 7.8-.4a2.5 2.5 0 001.8-1.8c.4-1.5.4-4.8.4-4.8s0-3.3-.4-4.8zM10 15V9l5 3-5 3z"/></svg>YouTube</span>
        <span class="elogo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M7 9c4-1 8-1 11 1M7 13c3-1 6-1 9 1M8 17c2-1 4-1 6 .5"/></svg>Spotify</span>
        <span class="elogo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s7-7.5 7-13a7 7 0 10-14 0c0 5.5 7 13 7 13z"/><circle cx="12" cy="9" r="2.5"/></svg>Maps</span>
        <span class="elogo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 9h18M8 3v4M16 3v4"/></svg>Calendly</span>
      </div>
    </div>
  </div>
</section>

<!-- ========== PRICING ========== -->
<section class="pricing" id="pricing">
  <div class="container">
    <div class="section-head">
      <div class="section-eyebrow">{{ __('Pricing') }}</div>
      <h2 class="section-title">{{ __('Start free. Grow when you are ready.') }}</h2>
      <p class="section-sub">{{ __('Four plans, transparent pricing. Cancel anytime.') }}</p>
    </div>

    <div class="price-grid">
      @foreach($orderedPlans as $plan)
        @php
          $slug = $plan['slug'] ?? '';
          $isFreePlan = $slug === 'free' || ($plan['price_1m'] ?? 0) == 0;
          $isProPlan = ($slug === 'pro');
          $priceRaw = $formatPrice($plan['price_1m'] ?? 0);
          $sep = $isGerman ? ',' : '.';
          $priceParts = explode($sep, $priceRaw, 2);
          $priceInt = $priceParts[0] ?? '0';
          $priceDec = isset($priceParts[1]) ? $sep . $priceParts[1] : ($sep . '00');
          $name = $planNameOverrides[$slug] ?? ucfirst($slug);
          $blockCount = max(1, (int) ($plan['limits']['max_links_per_page'] ?? 10));
          $features = match($slug) {
            'free' => [
              __('1 hub included'),
              __('Up to :count blocks per hub', ['count' => $blockCount]),
              __('Drag-and-drop block editor'),
              __('Header and hero blocks'),
              __('Basic analytics'),
            ],
            'basic' => [
              __('Everything in Free, plus:'),
              __('Up to :count blocks per hub', ['count' => $blockCount]),
              __('Contact forms (90-day submission history)'),
              __('Remove Wayvio branding'),
              __('Advanced analytics (90-day history)'),
            ],
            'pro' => [
              __('Everything in Basic, plus:'),
              __('Custom domain'),
              __('Full SEO control (meta tags)'),
              __('UTM parameter analysis'),
              __('365-day analytics retention'),
              __('Form submissions (365-day history)'),
            ],
            'agency' => [
              __('Everything in Pro, plus:'),
              __(':count hubs included, up to :max total', ['count' => $plan['included_hubs'] ?? 2, 'max' => $plan['max_hubs'] ?? 10]),
              __('Full white label (custom domain and logo)'),
              __('Managed hubs for teams and clients'),
            ],
            default => [],
          };
          $desc = match($slug) {
            'free' => __('Ideal to get to know Wayvio.'),
            'basic' => __('For creators who take their branding seriously.'),
            'pro' => __('Perfect for professionals and growing brands.'),
            'agency' => __('The complete solution for teams and agencies.'),
            default => '',
          };
        @endphp
        <article class="price {{ $isProPlan ? 'featured' : '' }}">
          @if($isProPlan)
            <span class="price-tag">{{ __('Popular') }}</span>
          @endif
          <span class="price-name">{{ $name }}</span>
          <div>
            <div class="price-amount"><sup>€</sup>{{ $isFreePlan ? '0' : $priceInt }}<span style="font-size:18px;color:{{ $isProPlan ? '#bcd6d2' : 'var(--ink-3)' }};font-weight:500">{{ $isFreePlan ? ($sep.'00') : $priceDec }} / {{ __('month') }}</span></div>
            <div class="price-per">{{ $isFreePlan ? __('Free forever') : __('Billed monthly') }}</div>
          </div>
          <p class="price-desc">{{ $desc }}</p>
          <ul class="price-list">
            @foreach($features as $feature)
              <li>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                {{ $feature }}
              </li>
            @endforeach
          </ul>
          <a href="{{ $registerTarget }}" class="btn-price {{ (!$isFreePlan && !$isProPlan) ? 'dark' : '' }}">
            {{ $isFreePlan ? __('Start for free') : __('Get started') }}
          </a>
        </article>
      @endforeach
    </div>
  </div>
</section>

<!-- ========== TRUST ========== -->
<section class="trust">
  <div class="container">
    <div class="trust-grid">
      <div class="trust-item"><span class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 8h18M3 14h18"/></svg></span><span>{{ __('Hosted in Germany') }}</span></div>
      <div class="trust-item"><span class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg></span><span>{{ __('GDPR-ready') }}</span></div>
      <div class="trust-item"><span class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="3" y1="3" x2="21" y2="21"/></svg></span><span>{{ __('No hidden costs') }}</span></div>
      <div class="trust-item"><span class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg></span><span>{{ __('Cancel anytime') }}</span></div>
      <div class="trust-item"><span class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 018 0v4"/></svg></span><span>{{ __('SSL encrypted') }}</span></div>
      <div class="trust-item"><span class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="4" rx="1"/><rect x="4" y="10" width="16" height="4" rx="1"/><rect x="4" y="16" width="16" height="4" rx="1"/></svg></span><span>{{ __('Stack blocks — no code') }}</span></div>
    </div>
  </div>
</section>

<!-- ========== CTA FOOTER ========== -->
<section class="cta-foot">
  <div class="container cta-inner">
    <h2>{{ __('Professionally online in 10 minutes.') }}</h2>
    <p>{{ __('No technical knowledge required. No credit card to start. Legally prepared from the start.') }}</p>
    <a href="{{ $registerTarget }}" class="btn btn-accent btn-lg">
      {{ __('Start for free now') }}
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
    </a>
    <div class="hero-meta" style="margin-top:24px">
      <span class="chip"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> {{ __('Ready to use immediately') }}</span>
      <span class="chip"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> {{ __('GDPR-ready') }}</span>
      <span class="chip"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> {{ __('Hosting in Germany') }}</span>
    </div>
  </div>
</section>

<!-- ========== FOOTER ========== -->
<footer class="site-footer">
  <div class="container footer-inner">
    <div style="display:flex;align-items:center;gap:10px">
      <a href="{{ url('') }}" class="logo" style="font-size:16px">
        <img src="{{ asset('assets/landingpage/logo_color.svg') }}" alt="" aria-hidden="true" />
        WAYVIO
      </a>
      <span style="color:var(--ink-3)">© @php echo date('Y'); @endphp</span>
    </div>
    <div class="footer-links">
      @if($publicLegalLinks['agb'] !== '')
        <a href="{{ $publicLegalLinks['agb'] }}">{{ $footerLabels['agb'] }}</a>
      @endif
      @if($publicLegalLinks['avv'] !== '')
        <a href="{{ $publicLegalLinks['avv'] }}">{{ $footerLabels['avv'] }}</a>
      @endif
      @if($publicLegalLinks['privacy'] !== '')
        <a href="{{ $publicLegalLinks['privacy'] }}">{{ $footerLabels['privacy'] }}</a>
      @endif
      @if($publicLegalLinks['imprint'] !== '')
        <a href="{{ $publicLegalLinks['imprint'] }}">{{ $footerLabels['imprint'] }}</a>
      @endif
      @if($contactUrl !== '')
        <a href="{{ $contactUrl }}">{{ $footerLabels['contact'] }}</a>
      @endif
      @if($helpCenterUrl !== '')
        <a href="{{ $helpCenterUrl }}">{{ $footerLabels['help'] }}</a>
      @endif
      <a href="https://github.com/Luqqua/wayvio" target="_blank" rel="noreferrer">{{ $footerLabels['license'] }}</a>
    </div>
    <div style="color:var(--ink-3)">Made in Germany 🇩🇪</div>
  </div>
</footer>

<script>
  const examples = [
    {
      key:'handwerk',
      tab:'{{ __('Trades & Crafts') }}',
      img:'{{ asset('assets/landingpage/example_handwerk.webp') }}',

      title:'{{ __('Electrician & Plumber') }}',
      copy:'{{ __('Emergency hotline, fixed prices and trust at first glance. With contact form for inquiries — all assembled from pre-made blocks.') }}',
      tags:['Hero','{{ __('Services') }}','{{ __('Contact form') }}','{{ __('Emergency CTA') }}']
    },
    {
      key:'cafe',
      tab:'{{ __('Café & Restaurant') }}',
      img:'{{ asset('assets/landingpage/example_gastro.webp') }}',

      title:'{{ __('Café & Restaurant') }}',
      copy:'{{ __('Opening hours, menu with allergens and maps — all on one page. Assembled with the block-stack editor, no code required.') }}',
      tags:['{{ __('Opening hours') }}','{{ __('Menu') }}','Maps','{{ __('Imprint') }}','SEO-Meta']
    },
    {
      key:'freelance',
      tab:'{{ __('Consultants & Coaches') }}',
      img:'{{ asset('assets/landingpage/example_consulting.webp') }}',

      title:'{{ __('Marketing & Strategy') }}',
      copy:'{{ __('Clear positioning, packages with prices and a first-meeting form. Started from a template, adapted in minutes — without programming.') }}',
      tags:['{{ __('Packages') }}','{{ __('First meeting') }}','Lead-Form','DACH']
    },
    {
      key:'linkbio',
      tab:'{{ __('Link in Bio') }}',
      img:'{{ asset('assets/landingpage/example_linkinbio.webp') }}',

      title:'{{ __('Link in Bio') }}',
      copy:'{{ __('All links in one place — with imprint workflow, categories and real branding instead of generic Linktree.') }}',
      tags:['Socials','Affiliates','{{ __('Categories') }}','{{ __('Custom colors') }}']
    }
  ];

  const tabsEl = document.getElementById('showcaseTabs');
  const infoEl = document.getElementById('showcaseInfo');
  const imgEl = document.getElementById('showcaseImg');
  let active = 0;

  function render() {
    tabsEl.innerHTML = examples.map((e,i)=>`<button class="showcase-tab${i===active?' active':''}" data-i="${i}">${e.tab}</button>`).join('');
    const e = examples[active];
    infoEl.innerHTML = `
      <h3>${e.title}</h3>
      <p>${e.copy}</p>
      <div class="tags">${e.tags.map(t=>`<span>${t}</span>`).join('')}</div>`;
    imgEl.style.opacity = '0';
    setTimeout(()=>{ imgEl.src = e.img; imgEl.style.opacity = '1'; }, 120);
  }
  tabsEl.addEventListener('click', e=>{
    const t = e.target.closest('.showcase-tab'); if(!t) return;
    active = +t.dataset.i; render();
  });
  render();

  const io = new IntersectionObserver((entries)=>{
    entries.forEach(e=>{ if(e.isIntersecting) e.target.classList.add('visible'); });
  },{threshold:.12});
  document.querySelectorAll('.section-head, .a-card, .feat, .price, .dsgvo, .scale-wrap, .embeds').forEach(el=>{
    el.classList.add('reveal'); io.observe(el);
  });
</script>

</body>
</html>
