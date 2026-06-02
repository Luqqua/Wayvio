<!doctype html>
@php
  $homeUrl = Route::has('home') ? route('home') : url('/');
  $loginEnabled = Route::has('login');
  $registerEnabled = Route::has('register') && config('auth.allow_registration') && !config('linkstack.single_user_mode');

  // Locale detection via URL prefix
  $helpLocale = str_starts_with(request()->path(), 'help/en') ? 'en' : 'de';
  $isEn = $helpLocale === 'en';

  // Language toggle URL — slug mapping for articles with different DE/EN path segments
  $currentPath = request()->path();
  $slugMap = [
    'help/rechtssicherheit-dsgvo' => 'help/en/legal-gdpr',
    'help/account-abrechnung'     => 'help/en/account-billing',
    'help/seitenaufbau'           => 'help/en/page-structure',
    'help/header-modi'            => 'help/en/header-modes',
    'help/en/legal-gdpr'          => 'help/rechtssicherheit-dsgvo',
    'help/en/account-billing'     => 'help/account-abrechnung',
    'help/en/page-structure'      => 'help/seitenaufbau',
    'help/en/header-modes'        => 'help/header-modi',
  ];
  if (isset($slugMap[$currentPath])) {
    $toggleUrl = url($slugMap[$currentPath]);
  } else {
    $toggleUrl = $isEn
      ? url(preg_replace('#^help/en(/|$)#', 'help$1', $currentPath))
      : url(preg_replace('#^help(/|$)#', 'help/en$1', $currentPath));
  }

  // Canonical DE/EN paths for hreflang
  if ($isEn) {
    $deHreflang = isset($slugMap[$currentPath]) ? url($slugMap[$currentPath]) : url(preg_replace('#^help/en(/|$)#', 'help$1', $currentPath));
    $enHreflang = url()->current();
  } else {
    $deHreflang = url()->current();
    $enHreflang = isset($slugMap[$currentPath]) ? url($slugMap[$currentPath]) : url(preg_replace('#^help(/|$)#', 'help/en$1', $currentPath));
  }

  $defaultMeta = $isEn
    ? config('app.name') . ' — Help Center'
    : config('app.name') . ' — Hilfezentrum';
  $defaultDesc = $isEn
    ? 'Guides, step-by-step instructions and answers — from your first block stack to white-label agency setup.'
    : 'Anleitungen, Schritt-für-Schritt-Guides und Antworten — für deinen ersten Block-Stapel bis zum White-Label-Setup für Agenturen.';

  $metaTitle = trim($__env->yieldContent('meta_title', $defaultMeta));
  $metaDescription = trim($__env->yieldContent('meta_description', $defaultDesc));
  $canonicalUrl = trim($__env->yieldContent('meta_canonical', url()->current()));

  $publicLegalLinks = legalDocumentLinks($helpLocale);
  $footerLocale = $publicLegalLinks['locale'];
  $footerIsEn = $footerLocale === 'en';
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
<html lang="{{ $helpLocale }}">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>{{ $metaTitle }}</title>
<meta name="description" content="{{ $metaDescription }}">
<meta name="robots" content="index,follow">
<link rel="canonical" href="{{ $canonicalUrl }}">
<link rel="alternate" hreflang="de" href="{{ $deHreflang }}">
<link rel="alternate" hreflang="en" href="{{ $enHreflang }}">
<meta property="og:url" content="{{ $canonicalUrl }}">
<meta property="og:type" content="article">
<meta property="og:title" content="{{ $metaTitle }}">
<meta property="og:description" content="{{ $metaDescription }}">
<meta property="og:locale" content="{{ $helpLocale }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $metaTitle }}">
<meta name="twitter:description" content="{{ $metaDescription }}">

@if(file_exists(base_path('assets/wayvio/images/').findFile('favicon')))
  <link rel="icon" type="image/png" href="{{ asset('assets/wayvio/images/'.findFile('favicon')) }}">
@else
  <link rel="icon" type="image/svg+xml" href="{{ asset('assets/wayvio/images/logo.svg') }}">
@endif

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700;800&family=Geist+Mono:wght@400&display=swap" rel="stylesheet">

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
    color:var(--ink);background:var(--bg);
    -webkit-font-smoothing:antialiased;line-height:1.55;font-size:16px;text-wrap:pretty;
  }
  img{max-width:100%;display:block}
  a{color:inherit;text-decoration:none}
  button{font-family:inherit;cursor:pointer;border:0;background:none;color:inherit}
  .container{max-width:var(--maxw);margin:0 auto;padding:0 24px}

  /* ===== NAV ===== */
  .nav{
    position:sticky;top:0;z-index:50;
    backdrop-filter:saturate(140%) blur(14px);
    -webkit-backdrop-filter:saturate(140%) blur(14px);
    background:rgba(243,247,247,.78);
    border-bottom:1px solid rgba(220,229,229,.7);
  }
  .nav-inner{display:flex;align-items:center;justify-content:space-between;height:68px;gap:24px}
  .logo{display:flex;align-items:center;gap:10px;font-weight:700;font-size:20px;letter-spacing:-.01em;color:var(--ink)}
  .logo svg{width:28px;height:28px;flex:0 0 auto}
  .nav-links{display:flex;align-items:center;gap:8px}
  .nav-links a{color:var(--ink-2);font-weight:500;font-size:15px;padding:8px 14px;border-radius:10px;}
  .nav-links a:hover{color:var(--ink);background:rgba(15,61,62,.05)}
  .nav-links a.active{color:var(--ink);background:var(--bg-2);font-weight:600}
  .btn{
    display:inline-flex;align-items:center;justify-content:center;gap:8px;
    padding:10px 18px;border-radius:10px;font-weight:600;font-size:15px;
    transition:transform .12s ease,background .15s ease,box-shadow .15s ease,color .15s ease;
    white-space:nowrap;
  }
  .btn-ghost{color:var(--ink);border:1px solid var(--border);background:var(--white)}
  .btn-ghost:hover{background:var(--bg)}
  .btn-primary{background:var(--primary);color:#fff;box-shadow:var(--shadow-sm)}
  .btn-primary:hover{background:#0a2c2d;transform:translateY(-1px)}
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
  @media(max-width:880px){
    .container{padding:0 28px}
    .article table{display:block;overflow-x:auto;-webkit-overflow-scrolling:touch}
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

  /* ===== HERO (index) ===== */
  .hero{
    position:relative;overflow:hidden;padding:64px 0 48px;
    background:
      radial-gradient(1100px 500px at 85% -10%,rgba(20,184,166,.18),transparent 60%),
      radial-gradient(700px 380px at -10% 30%,rgba(15,61,62,.07),transparent 60%),
      linear-gradient(180deg,var(--bg) 0%,#EAF2F2 100%);
  }
  .eyebrow{
    display:inline-flex;align-items:center;gap:8px;padding:6px 12px 6px 8px;border-radius:999px;
    background:rgba(20,184,166,.12);color:#0a655a;font-size:13px;font-weight:600;letter-spacing:.02em;
    border:1px solid rgba(20,184,166,.25);
  }
  .eyebrow .dot{width:8px;height:8px;border-radius:999px;background:var(--accent);box-shadow:0 0 0 3px rgba(20,184,166,.25)}
  h1.hero-title{
    font-size:clamp(36px,4.6vw,56px);line-height:1.05;letter-spacing:-.025em;font-weight:700;
    margin:18px 0 16px;color:var(--primary);max-width:820px;
  }
  h1.hero-title em{font-style:normal;background:linear-gradient(120deg,var(--accent) 0%,#0a655a 100%);-webkit-background-clip:text;background-clip:text;color:transparent}
  .hero-sub{font-size:18px;color:var(--ink-2);max-width:620px;margin:0 0 28px}
  /* ===== SECTION BASE ===== */
  section.block{padding:72px 0}
  .section-head{max-width:760px;margin:0 0 32px}
  .section-eyebrow{text-transform:uppercase;letter-spacing:.14em;font-size:12px;font-weight:600;color:var(--accent-deep);margin-bottom:12px;}
  .section-title{font-size:clamp(26px,3.2vw,38px);line-height:1.1;letter-spacing:-.02em;font-weight:700;color:var(--primary);margin:0 0 10px;}
  .section-sub{font-size:17px;color:var(--ink-2);margin:0}

  /* ===== POPULAR ===== */
  .popular-row{display:flex;align-items:flex-end;justify-content:space-between;gap:24px;flex-wrap:wrap;margin-bottom:24px;}
  .popular-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;}
  .pop{
    background:#fff;border:1px solid var(--border);border-radius:var(--radius-lg);
    padding:24px;display:flex;flex-direction:column;gap:14px;
    transition:transform .15s ease,box-shadow .15s ease,border-color .15s;position:relative;overflow:hidden;
  }
  .pop:hover{transform:translateY(-3px);box-shadow:var(--shadow);border-color:#cfe1de}
  .pop-mini{display:inline-flex;align-items:center;gap:8px;font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--accent-deep);}
  .pop-mini svg{width:14px;height:14px}
  .pop h3{margin:0;font-size:18px;color:var(--primary);letter-spacing:-.01em;line-height:1.3}
  .pop p{margin:0;color:var(--ink-2);font-size:14px}
  .pop-foot{display:flex;align-items:center;justify-content:space-between;margin-top:auto;padding-top:8px;color:var(--ink-3);font-size:13px;font-weight:500;}
  .pop-foot .arrow{width:28px;height:28px;border-radius:8px;display:grid;place-items:center;background:var(--bg);color:var(--primary);transition:background .15s,transform .15s;}
  .pop:hover .pop-foot .arrow{background:var(--accent);color:#0a2520;transform:translateX(2px)}
  .pop-foot .arrow svg{width:14px;height:14px}
  @media(max-width:880px){.popular-grid{grid-template-columns:1fr}}

  /* ===== CATEGORY GRID ===== */
  .cat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;}
  .cat{
    background:#fff;border:1px solid var(--border);border-radius:var(--radius-lg);
    padding:28px;display:flex;flex-direction:column;gap:14px;
    transition:transform .15s ease,box-shadow .15s ease,border-color .15s;text-align:left;position:relative;
  }
  .cat:hover{transform:translateY(-3px);box-shadow:var(--shadow);border-color:#cfe1de}
  .cat-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;}
  .cat .badge{width:46px;height:46px;border-radius:12px;display:grid;place-items:center;background:var(--bg);color:var(--primary);flex:0 0 auto;}
  .cat .badge.dark{background:var(--primary);color:#7eddcb}
  .cat .badge svg{width:22px;height:22px}
  .cat h3{margin:0;font-size:20px;color:var(--primary);letter-spacing:-.01em;line-height:1.25}
  .cat > p{margin:0;color:var(--ink-2);font-size:15px}
  .cat-list{list-style:none;padding:0;margin:6px 0 0;display:flex;flex-direction:column;gap:1px;border-top:1px solid var(--border);}
  .cat-list li{border-bottom:1px solid var(--border)}
  .cat-list li:last-child{border-bottom:0}
  .cat-list a{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:11px 0;font-size:14px;color:var(--ink-2);font-weight:500;transition:color .12s,padding .12s;}
  .cat-list a:hover{color:var(--primary);padding-left:4px}
  .cat-list a .chev{width:14px;height:14px;color:var(--ink-3);flex:0 0 auto;transition:transform .15s,color .15s}
  .cat-list a:hover .chev{color:var(--accent-deep);transform:translateX(2px)}
  @media(max-width:980px){.cat-grid{grid-template-columns:repeat(2,1fr)}}
  @media(max-width:640px){.cat-grid{grid-template-columns:1fr}}

  /* ===== QUICK START ===== */
  .quick{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
  .quick-card{
    background:linear-gradient(140deg,#0F3D3E 0%,#0a2c2d 60%,#114443 100%);
    color:#dceeec;border-radius:var(--radius-lg);padding:36px;position:relative;overflow:hidden;
    display:flex;flex-direction:column;gap:14px;min-height:220px;
  }
  .quick-card.alt{background:#fff;color:var(--ink);border:1px solid var(--border);}
  .quick-card:before{content:"";position:absolute;inset:0;background:radial-gradient(500px 300px at 110% 0%,rgba(20,184,166,.3),transparent 60%);pointer-events:none;}
  .quick-card.alt:before{display:none}
  .quick-card .ico{width:48px;height:48px;border-radius:12px;display:grid;place-items:center;background:rgba(20,184,166,.18);color:#7eddcb;flex:0 0 auto;position:relative;z-index:1;}
  .quick-card.alt .ico{background:var(--bg);color:var(--primary)}
  .quick-card .ico svg{width:24px;height:24px}
  .quick-card h3{margin:0;font-size:24px;letter-spacing:-.01em;color:#fff;position:relative;z-index:1}
  .quick-card.alt h3{color:var(--primary)}
  .quick-card p{margin:0;font-size:15px;color:#bcd6d2;max-width:380px;position:relative;z-index:1}
  .quick-card.alt p{color:var(--ink-2)}
  .quick-card .qcta{margin-top:auto;display:inline-flex;align-items:center;gap:6px;font-size:14px;font-weight:600;color:var(--accent);position:relative;z-index:1;}
  .quick-card.alt .qcta{color:var(--accent-deep)}
  .quick-card .qcta svg{width:14px;height:14px;transition:transform .15s}
  .quick-card:hover .qcta svg{transform:translateX(3px)}
  @media(max-width:780px){.quick{grid-template-columns:1fr}}

  /* ===== CONTACT ===== */
  .contact{
    background:#fff;border:1px solid var(--border);border-radius:var(--radius-lg);
    padding:40px 48px;display:grid;grid-template-columns:1fr auto;gap:32px;align-items:center;
    box-shadow:var(--shadow-sm);
  }
  .contact h3{margin:0 0 8px;font-size:24px;letter-spacing:-.01em;color:var(--primary)}
  .contact p{margin:0;color:var(--ink-2);font-size:15px;max-width:520px}
  .contact-actions{display:flex;gap:10px;flex-wrap:wrap}
  @media(max-width:780px){.contact{grid-template-columns:1fr;padding:28px}}

  /* ===== ARTICLE HEADER ===== */
  .art-head{
    position:relative;overflow:hidden;padding:48px 0 56px;
    background:
      radial-gradient(900px 380px at 90% -10%,rgba(20,184,166,.14),transparent 60%),
      radial-gradient(700px 380px at -10% 30%,rgba(15,61,62,.06),transparent 60%),
      linear-gradient(180deg,var(--bg) 0%,#EAF2F2 100%);
  }
  .breadcrumb{display:flex;align-items:center;gap:8px;flex-wrap:wrap;color:var(--ink-3);font-size:13px;font-weight:500;margin-bottom:20px;}
  .breadcrumb a{color:var(--ink-3)}
  .breadcrumb a:hover{color:var(--ink)}
  .breadcrumb svg{width:12px;height:12px;color:var(--ink-3)}
  .breadcrumb .here{color:var(--ink);font-weight:600}
  .art-cat{
    display:inline-flex;align-items:center;gap:8px;padding:6px 12px 6px 8px;border-radius:999px;
    background:rgba(20,184,166,.12);color:#0a655a;font-size:13px;font-weight:600;letter-spacing:.02em;
    border:1px solid rgba(20,184,166,.25);
  }
  .art-cat .ico{width:16px;height:16px;color:var(--accent-deep)}
  .art-title{
    font-size:clamp(32px,4.2vw,48px);line-height:1.06;letter-spacing:-.025em;
    font-weight:700;margin:18px 0 14px;color:var(--primary);max-width:840px;
  }
  .art-lede{font-size:18px;color:var(--ink-2);margin:0 0 24px;max-width:720px}

  /* ===== ARTICLE BODY ===== */
  .art-shell{padding:48px 0 96px;max-width:840px;margin:0 auto}
  .article{background:#fff;border:1px solid var(--border);border-radius:var(--radius-lg);padding:48px;box-shadow:var(--shadow-sm);}
  .article > * + *{margin-top:1em}
  .article h2{font-size:26px;letter-spacing:-.02em;color:var(--primary);font-weight:700;margin:48px 0 12px;line-height:1.2;scroll-margin-top:88px;}
  .article h2:first-child{margin-top:0}
  .article h3{font-size:19px;letter-spacing:-.01em;color:var(--primary);font-weight:600;margin:32px 0 8px;line-height:1.3;scroll-margin-top:88px;}
  .article p{font-size:16px;color:var(--ink-2);margin:0 0 1em;line-height:1.65}
  .article p:last-child{margin-bottom:0}
  .article a:not(.btn){color:var(--accent-deep);font-weight:500;border-bottom:1px solid rgba(14,161,146,.25)}
  .article a:not(.btn):hover{border-bottom-color:var(--accent-deep)}
  .article strong{color:var(--ink);font-weight:600}
  .article ul,.article ol{margin:0 0 1em;padding-left:0;list-style:none}
  .article ul li,.article ol li{position:relative;padding:6px 0 6px 28px;color:var(--ink-2);font-size:16px;line-height:1.55;}
  .article ul li:before{content:"";position:absolute;left:8px;top:14px;width:6px;height:6px;border-radius:999px;background:var(--accent);}
  .article ol{counter-reset:n}
  .article ol li{counter-increment:n;padding-left:36px}
  .article ol li:before{content:counter(n);position:absolute;left:0;top:6px;width:24px;height:24px;border-radius:7px;background:var(--bg-2);color:var(--primary);font-size:12px;font-weight:700;display:grid;place-items:center;}
  .article code{font-family:'Geist Mono',monospace;font-size:13px;background:var(--bg-2);color:var(--primary);padding:2px 7px;border-radius:6px;border:1px solid var(--border);}
  .article pre{background:#0F3D3E;color:#cfe5e1;border-radius:14px;padding:18px 20px;font-family:'Geist Mono',monospace;font-size:13px;line-height:1.65;overflow:auto;border:1px solid #0a2c2d;}
  .article pre code{background:transparent;border:0;color:inherit;padding:0}
  .callout{display:grid;grid-template-columns:auto 1fr;gap:14px;padding:18px 20px;border-radius:14px;border:1px solid var(--border);background:var(--bg);margin:24px 0;}
  .callout .ico{width:32px;height:32px;border-radius:9px;display:grid;place-items:center;background:#fff;border:1px solid var(--border);color:var(--accent-deep);}
  .callout .ico svg{width:18px;height:18px}
  .callout h4{margin:0 0 4px;font-size:15px;color:var(--primary);font-weight:700}
  .callout p{margin:0;font-size:14px;color:var(--ink-2)}
  .callout.warn{background:#FEF7E6;border-color:#F4E4B0}
  .callout.warn .ico{color:#8B6B0F;border-color:#F4E4B0;background:#fff}
  .callout.warn h4{color:#6F5409}
  .callout.tip{background:rgba(20,184,166,.08);border-color:rgba(20,184,166,.25)}
  .callout.tip .ico{color:var(--accent-deep);border-color:rgba(20,184,166,.3)}
  .article table{width:100%;border-collapse:collapse;margin:18px 0;font-size:14px}
  .article th,.article td{padding:12px 14px;text-align:left;border-bottom:1px solid var(--border);color:var(--ink-2);}
  .article th{background:var(--bg);color:var(--primary);font-weight:600;font-size:12px;text-transform:uppercase;letter-spacing:.06em;}
  .article td code{font-size:12px}
  .figure{margin:24px 0;border-radius:14px;overflow:hidden;border:1px solid var(--border);background:repeating-linear-gradient(135deg,#EAF2F2 0 12px,#E0EBEB 12px 24px);aspect-ratio:16/9;display:grid;place-items:center;color:var(--ink-3);font-family:'Geist Mono',monospace;font-size:13px;}
  .figure span{background:#fff;padding:6px 12px;border-radius:8px;border:1px solid var(--border)}
  .figure + .caption{margin:8px 0 0;font-size:13px;color:var(--ink-3);text-align:center;}
  .related{margin-top:64px}
  .related h3{font-size:22px;letter-spacing:-.01em;color:var(--primary);margin:0 0 18px;}
  .related-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
  .rel{background:#fff;border:1px solid var(--border);border-radius:14px;padding:20px;display:flex;flex-direction:column;gap:8px;transition:transform .15s,box-shadow .15s,border-color .15s;}
  .rel:hover{transform:translateY(-2px);box-shadow:var(--shadow);border-color:#cfe1de}
  .rel .mini{font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--accent-deep);}
  .rel h4{margin:0;font-size:16px;color:var(--primary);letter-spacing:-.01em;line-height:1.3}
  .rel p{margin:0;color:var(--ink-3);font-size:13px;font-weight:500}
  .rel .arr{margin-top:6px;display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:var(--accent-deep);}
  .rel .arr svg{width:14px;height:14px;transition:transform .15s}
  .rel:hover .arr svg{transform:translateX(3px)}
  @media(max-width:880px){.related-grid{grid-template-columns:1fr}}
  @media(max-width:780px){.article{padding:28px}}

  /* ===== FOOTER ===== */
  footer{background:var(--bg);padding:48px 0 32px;border-top:1px solid var(--border);color:var(--ink-3);font-size:14px;margin-top:32px;}
  .footer-inner{display:flex;justify-content:space-between;gap:24px;flex-wrap:wrap;align-items:center}
  .footer-links{display:flex;gap:20px;flex-wrap:wrap}
  .footer-links a:hover{color:var(--ink)}

  .reveal{opacity:0;transform:translateY(14px);transition:all .8s cubic-bezier(.2,.8,.2,1)}
  .reveal.visible{opacity:1;transform:none}
</style>
@stack('help-head')
</head>
<body>

<header class="nav">
  <div class="container nav-inner">
    <a href="{{ $homeUrl }}" class="logo">
      <img src="{{ asset('assets/wayvio/images/logo_color.svg') }}" width="28" height="28" alt="{{ config('app.name') }} Logo" aria-hidden="true">
      WAYVIO
    </a>
    <nav class="nav-links">
      <a href="{{ $homeUrl }}#showcase">{{ $isEn ? 'Examples' : 'Beispiele' }}</a>
      <a href="{{ $homeUrl }}#features">Features</a>
      <a href="{{ $homeUrl }}#pricing">{{ $isEn ? 'Pricing' : 'Preise' }}</a>
      <a href="{{ $isEn ? route('help.en.index') : route('help.index') }}" class="{{ request()->routeIs('help.*') ? 'active' : '' }}">{{ $isEn ? 'Help Center' : 'Hilfezentrum' }}</a>
    </nav>
    <div class="lang-toggle" aria-label="{{ $isEn ? 'Language' : 'Sprache' }}">
      @if($isEn)
        <a href="{{ $toggleUrl }}">DE</a>
        <div class="lang-sep"></div>
        <span class="active-lang">EN</span>
      @else
        <span class="active-lang">DE</span>
        <div class="lang-sep"></div>
        <a href="{{ $toggleUrl }}">EN</a>
      @endif
    </div>
    <div class="nav-cta">
      @if($loginEnabled)
        @auth
          <a href="{{ url('dashboard') }}" class="btn btn-primary">Dashboard</a>
        @else
          <a href="{{ route('login') }}" class="btn btn-ghost">{{ $isEn ? 'Login' : 'Anmelden' }}</a>
          @if($registerEnabled)
            <a href="{{ route('register') }}" class="btn btn-primary">{{ $isEn ? 'Sign up' : 'Registrieren' }}</a>
          @else
            <a href="{{ url('dashboard') }}" class="btn btn-primary">Dashboard</a>
          @endif
        @endauth
      @else
        <a href="{{ url('dashboard') }}" class="btn btn-primary">Dashboard</a>
      @endif
    </div>
  </div>
</header>

@yield('main')

<footer>
  <div class="container footer-inner">
    <div style="display:flex;align-items:center;gap:10px">
      <a href="{{ $homeUrl }}" class="logo" style="font-size:16px">
        <img src="{{ asset('assets/wayvio/images/logo_color.svg') }}" width="24" height="24" alt="{{ config('app.name') }} Logo" aria-hidden="true">
        WAYVIO
      </a>
      <span style="color:var(--ink-3)">© {{ date('Y') }}</span>
    </div>
    <div class="footer-links">
      @if($publicLegalLinks['agb'] !== '')<a href="{{ $publicLegalLinks['agb'] }}">{{ $footerLabels['agb'] }}</a>@endif
      @if($publicLegalLinks['avv'] !== '')<a href="{{ $publicLegalLinks['avv'] }}">{{ $footerLabels['avv'] }}</a>@endif
      @if($publicLegalLinks['privacy'] !== '')<a href="{{ $publicLegalLinks['privacy'] }}">{{ $footerLabels['privacy'] }}</a>@endif
      @if($publicLegalLinks['imprint'] !== '')<a href="{{ $publicLegalLinks['imprint'] }}">{{ $footerLabels['imprint'] }}</a>@endif
      @if($contactUrl !== '')<a href="{{ $contactUrl }}">{{ $footerLabels['contact'] }}</a>@endif
      @if($helpCenterUrl !== '')<a href="{{ $helpCenterUrl }}">{{ $footerLabels['help'] }}</a>@endif
      <a href="https://github.com/Luqqua/wayvio" target="_blank" rel="noreferrer">{{ $footerLabels['license'] }}</a>
    </div>
    <div style="color:var(--ink-3)">Made in Germany 🇩🇪</div>
  </div>
</footer>

<script>
  const io = new IntersectionObserver((entries) => {
    entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('visible'); });
  }, { threshold: .12 });
  document.querySelectorAll('.section-head,.pop,.cat,.quick-card,.contact').forEach(el => {
    el.classList.add('reveal'); io.observe(el);
  });
</script>
@stack('help-scripts')
</body>
</html>
