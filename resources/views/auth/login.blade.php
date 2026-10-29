<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · {{ config('app.name') }}</title>
    @include('partials.assets')
    <style>
        /* Login page only. Plain CSS scoped to .login-*. Motion uses transform/opacity and is switched off for prefers-reduced-motion. */
        .login-page { background: #0b0e20; color: #e0e7ff; }

        /* ---------- Full-page animated scene ---------- */
        .login-scene { position: fixed; inset: 0; z-index: 0; overflow: hidden; pointer-events: none;
            background: radial-gradient(1100px 620px at 0% 0%, #2e2a7a 0%, transparent 60%),
                        radial-gradient(900px 560px at 100% 100%, #3b0a68 0%, transparent 60%), #0d1024; }
        .login-parallax { position: absolute; inset: 0; will-change: transform; }
        .login-orb { position: absolute; border-radius: 50%; mix-blend-mode: screen; will-change: transform, opacity; }
        .login-orb-1 { width: 52vmax; height: 52vmax; left: -14vmax; top: -18vmax;
            background: radial-gradient(circle, rgb(99 102 241 / .75) 0%, rgb(99 102 241 / .25) 38%, transparent 66%); animation: login-orb-1 15s ease-in-out infinite; }
        .login-orb-2 { width: 46vmax; height: 46vmax; right: -12vmax; bottom: -20vmax;
            background: radial-gradient(circle, rgb(168 85 247 / .70) 0%, rgb(168 85 247 / .22) 40%, transparent 66%); animation: login-orb-2 17s ease-in-out infinite; }
        .login-orb-3 { width: 36vmax; height: 36vmax; left: 30%; top: 30%;
            background: radial-gradient(circle, rgb(56 189 248 / .45) 0%, rgb(59 130 246 / .18) 42%, transparent 66%); animation: login-orb-3 13s ease-in-out infinite; }
        .login-orb-4 { width: 28vmax; height: 28vmax; left: 52%; top: -10vmax;
            background: radial-gradient(circle, rgb(217 70 239 / .40) 0%, transparent 64%); animation: login-orb-4 18s ease-in-out infinite; }
        .login-grid { position: absolute; inset: -48px; opacity: .55;
            background-image: radial-gradient(rgb(199 210 254 / .18) 1px, transparent 1px); background-size: 24px 24px;
            animation: login-grid 14s linear infinite; }
        .login-vignette { position: absolute; inset: 0; background: radial-gradient(ellipse 85% 75% at 50% 45%, transparent 40%, rgb(8 10 26 / .85) 100%); }
        @keyframes login-orb-1 { 0%, 100% { transform: translate3d(0, 0, 0) scale(1); opacity: .85; } 50% { transform: translate3d(22vw, 16vh, 0) scale(1.18); opacity: 1; } }
        @keyframes login-orb-2 { 0%, 100% { transform: translate3d(0, 0, 0) scale(1.05); opacity: 1; } 50% { transform: translate3d(-20vw, -18vh, 0) scale(.88); opacity: .7; } }
        @keyframes login-orb-3 { 0%, 100% { transform: translate3d(-12vw, 10vh, 0) scale(.9); opacity: .55; } 50% { transform: translate3d(14vw, -12vh, 0) scale(1.15); opacity: 1; } }
        @keyframes login-orb-4 { 0%, 100% { transform: translate3d(0, 0, 0); opacity: .4; } 50% { transform: translate3d(-18vw, 24vh, 0); opacity: .9; } }
        @keyframes login-grid { from { transform: translate3d(0, 0, 0); } to { transform: translate3d(24px, 24px, 0); } }

        /* ---------- Layout ---------- */
        .login-wrap { position: relative; z-index: 1; display: grid; grid-template-columns: 1fr; min-height: 100dvh; max-width: 80rem; margin: 0 auto; overflow: clip; }
        @media (min-width: 1024px) { .login-wrap { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); } }

        .login-brand { position: relative; display: none; flex-direction: column; justify-content: space-between; gap: 2rem;
            padding: 2.5rem 1.5rem 2.25rem clamp(3rem, 5vw, 6rem); }
        @media (min-width: 1024px) { .login-brand { display: flex; } }
        .login-brand-logo { position: relative; z-index: 1; align-self: flex-start; }
        .login-brand-main { position: relative; z-index: 1; max-width: 35rem; }
        .login-eyebrow { display: inline-flex; align-items: center; gap: .5rem; padding: .375rem .875rem .375rem .625rem; border-radius: 999px; font-size: .75rem; font-weight: 600;
            letter-spacing: .14em; text-transform: uppercase; color: #c7d2fe; background: rgb(255 255 255 / .06); border: 1px solid rgb(165 180 252 / .22); backdrop-filter: blur(6px); }
        .login-eyebrow-dot { width: .5rem; height: .5rem; border-radius: 50%; background: #34d399; box-shadow: 0 0 0 0 rgb(52 211 153 / .6); animation: login-ping 2.4s ease-out infinite; }
        .login-headline { margin-top: 1.25rem; font-size: clamp(2.25rem, 3.2vw, 3.25rem); line-height: 1.1; font-weight: 700; letter-spacing: -.025em; color: #fff; }
        .login-headline .w { display: inline-block; }
        .login-accent { background: linear-gradient(90deg, #a5b4fc, #c084fc 45%, #f0abfc 70%, #a5b4fc); background-size: 200% 100%; -webkit-background-clip: text; background-clip: text; color: transparent;
            animation: login-shine 6s linear infinite; }
        .login-lede { margin-top: 1rem; max-width: 30rem; font-size: 1.0625rem; line-height: 1.6; color: rgb(199 210 254 / .78); }
        .login-features { margin-top: 2rem; display: flex; flex-direction: column; gap: 1.25rem; }
        .login-feature { display: flex; align-items: flex-start; gap: 1rem; }
        .login-feature-icon { position: relative; display: grid; place-items: center; flex-shrink: 0; width: 2.5rem; height: 2.5rem; border-radius: .75rem; color: #c7d2fe;
            background: linear-gradient(180deg, rgb(255 255 255 / .12), rgb(255 255 255 / .04)); border: 1px solid rgb(165 180 252 / .22); }
        .login-feature-icon::after { content: ''; position: absolute; inset: -1px; border-radius: inherit; box-shadow: 0 0 18px 2px rgb(129 140 248 / .55); opacity: 0;
            animation: login-glow 3.6s ease-in-out infinite; animation-delay: var(--g, 0s); }
        .login-feature-title { font-size: .9375rem; font-weight: 600; line-height: 1.4; color: #fff; }
        .login-feature-text { margin-top: .125rem; font-size: .875rem; line-height: 1.5; color: rgb(199 210 254 / .7); }
        .login-foot { position: relative; z-index: 1; font-size: .8125rem; color: rgb(199 210 254 / .6); }

        /* Decorative workflow chips (illustrative only) */
        .login-chips { position: absolute; inset: 0; z-index: 0; pointer-events: none; }
        .login-chip-pos { position: absolute; }
        .login-chip { display: flex; align-items: center; gap: .625rem; padding: .625rem .875rem; border-radius: .875rem; font-size: .8125rem; font-weight: 500; color: #e0e7ff; white-space: nowrap;
            background: rgb(255 255 255 / .07); border: 1px solid rgb(255 255 255 / .14); box-shadow: 0 12px 30px -12px rgb(2 6 23 / .7); backdrop-filter: blur(10px);
            animation: login-bob 6s ease-in-out infinite; animation-delay: var(--b, 0s); }
        .login-chip-dot { display: grid; place-items: center; width: 1.75rem; height: 1.75rem; border-radius: .5rem; flex-shrink: 0; }
        .login-chip small { display: block; font-size: .6875rem; font-weight: 400; color: rgb(199 210 254 / .65); }
        .login-chip-progress { flex-direction: column; align-items: stretch; gap: .5rem; width: 12.5rem; }
        .login-chip-bar { height: .375rem; border-radius: 999px; background: rgb(255 255 255 / .12); overflow: hidden; }
        .login-chip-bar span { display: block; height: 100%; width: 100%; border-radius: inherit; background: linear-gradient(90deg, #818cf8, #c084fc); transform-origin: left;
            animation: login-fill 5s ease-in-out infinite; }
        @keyframes login-bob { 0%, 100% { transform: translate3d(0, 0, 0) rotate(-.6deg); } 50% { transform: translate3d(0, -12px, 0) rotate(.6deg); } }
        @keyframes login-fill { 0% { transform: scaleX(.2); } 60%, 100% { transform: scaleX(.78); } }
        @keyframes login-ping { 0% { box-shadow: 0 0 0 0 rgb(52 211 153 / .55); } 80%, 100% { box-shadow: 0 0 0 8px rgb(52 211 153 / 0); } }
        @keyframes login-glow { 0%, 100% { opacity: 0; } 50% { opacity: .9; } }
        @keyframes login-shine { from { background-position: 0% 0; } to { background-position: 200% 0; } }

        /* Staggered entrance */
        .login-rise { animation: login-rise .8s cubic-bezier(.2, .8, .2, 1) both; animation-delay: var(--d, 0s); }
        .login-headline .w { animation: login-word .7s cubic-bezier(.2, .8, .2, 1) both; animation-delay: calc(.32s + var(--i) * .06s); }
        .login-chip-pos { animation: login-pop .9s cubic-bezier(.2, .8, .2, 1) both; animation-delay: var(--d, 1s); }
        @keyframes login-rise { from { opacity: 0; transform: translate3d(0, 18px, 0); } to { opacity: 1; transform: none; } }
        @keyframes login-word { from { opacity: 0; transform: translate3d(0, .5em, 0); } to { opacity: 1; transform: none; } }
        @keyframes login-pop { from { opacity: 0; transform: translate3d(0, 16px, 0) scale(.94); } to { opacity: 1; transform: none; } }

        /* ---------- Sign-in side ---------- */
        .login-side { display: flex; align-items: center; justify-content: center; padding: 1.5rem; }
        @media (min-width: 1024px) and (max-height: 760px) { .login-side { padding-block: .75rem; } }
        .login-column { width: 100%; max-width: 25rem; display: flex; flex-direction: column; align-items: stretch; }
        .login-mobile-logo { margin: 0 auto 1.5rem; }
        .login-copyright { margin-top: 1.25rem; text-align: center; font-size: .75rem; color: rgb(199 210 254 / .7); }
        @media (min-width: 1024px) { .login-mobile-logo, .login-copyright { display: none !important; } }

        .login-card-wrap { position: relative; isolation: isolate; }
        .login-halo { position: absolute; inset: -10%; z-index: -1; border-radius: 50%; filter: blur(52px); opacity: .5; will-change: transform, opacity;
            background: conic-gradient(from 0deg, #6366f1, #a855f7, #ec4899, #38bdf8, #6366f1); animation: login-spin 16s linear infinite, login-breathe 6s ease-in-out infinite; }
        /* Glass card: translucent surface + blur so the moving scene shows through. */
        .login-ring { position: relative; border-radius: 1.375rem; box-shadow: 0 30px 70px -24px rgb(2 6 23 / .85), 0 0 0 1px rgb(2 6 23 / .25); }
        /* Rotating gradient glow, masked to a thin border so it doesn't tint the glass. */
        .login-ring-glow { position: absolute; inset: 0; z-index: 2; padding: 1.5px; border-radius: inherit; overflow: hidden; pointer-events: none;
            -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0); -webkit-mask-composite: xor;
                    mask: linear-gradient(#000 0 0) content-box exclude, linear-gradient(#000 0 0); }
        .login-ring-glow::before { content: ''; position: absolute; left: 50%; top: 50%; width: 160%; aspect-ratio: 1; will-change: transform;
            background: conic-gradient(from 0deg, transparent 0 50%, #818cf8 64%, #c084fc 74%, #f0abfc 80%, transparent 92%);
            animation: login-ring 5s linear infinite; }
        @keyframes login-spin { to { transform: rotate(360deg); } }
        @keyframes login-ring { from { transform: translate(-50%, -50%) rotate(0deg); } to { transform: translate(-50%, -50%) rotate(360deg); } }
        @keyframes login-breathe { 0%, 100% { opacity: .35; } 50% { opacity: .6; } }

        .login-card { position: relative; z-index: 1; max-width: none; padding: 0; overflow: hidden; border-radius: 1.375rem; color-scheme: dark;
            border: 1px solid rgb(255 255 255 / .15);
            background: linear-gradient(180deg, rgb(255 255 255 / .11) 0%, rgb(255 255 255 / .05) 100%), rgb(12 14 36 / .56);
            -webkit-backdrop-filter: blur(22px) saturate(140%); backdrop-filter: blur(22px) saturate(140%);
            box-shadow: inset 0 1px 0 rgb(255 255 255 / .22), inset 0 0 0 1px rgb(255 255 255 / .03); }
        /* Inner top highlight */
        .login-card::before { content: ''; position: absolute; inset: 0 0 auto; height: 7rem; pointer-events: none; border-radius: inherit;
            background: radial-gradient(120% 100% at 50% 0%, rgb(255 255 255 / .10), transparent 70%); }
        @supports not ((backdrop-filter: blur(1px)) or (-webkit-backdrop-filter: blur(1px))) {
            .login-card { background: linear-gradient(180deg, #1c1d45 0%, #14152f 100%); }
        }
        .login-card-body { position: relative; padding: 2.5rem 2rem 2.125rem; }
        .login-head { display: flex; flex-direction: column; align-items: center; text-align: center; }
        .login-mark svg { filter: drop-shadow(0 8px 18px rgb(129 140 248 / .55)); }
        @media (max-width: 1023.98px) { .login-mark { display: none !important; } }
        .login-title { margin-top: 1rem; font-size: 1.6875rem; line-height: 1.2; font-weight: 700; letter-spacing: -.02em; color: #fff; }
        @media (max-width: 1023.98px) { .login-title { margin-top: 0; } }
        .login-subtitle { margin-top: .5rem; font-size: .9375rem; line-height: 1.5; color: #cbd5e1; }

        .login-alert { display: flex; gap: .625rem; align-items: flex-start; margin-top: 1.25rem; padding: .75rem .875rem; border-radius: .75rem; font-size: .8125rem; line-height: 1.45;
            color: #fecdd3; background: rgb(244 63 94 / .16); border: 1px solid rgb(251 113 133 / .38); animation: login-rise .4s ease-out both; }
        .login-alert-title { font-weight: 600; color: #ffe4e6; }
        .login-alert-icon { margin-top: .05rem; flex-shrink: 0; color: #fda4af; }
        .login-alert-success { color: #a7f3d0; background: rgb(16 185 129 / .16); border-color: rgb(52 211 153 / .38); }
        .login-alert-success .login-alert-icon { color: #6ee7b7; }

        .login-form { margin-top: 1.625rem; display: flex; flex-direction: column; gap: 1.125rem; }
        .login-label { display: block; margin-bottom: .4375rem; font-size: .84375rem; font-weight: 500; color: #cbd5e1; }
        .login-control { position: relative; }
        .login-lead-icon { position: absolute; left: .9375rem; top: 50%; transform: translateY(-50%); color: #94a3b8; pointer-events: none; transition: color .15s; }
        .login-input { display: block; width: 100%; height: 2.875rem; padding: 0 .9375rem 0 2.75rem; font-size: .9375rem; color: #fff; caret-color: #c7d2fe;
            background: rgb(255 255 255 / .06); border: 1px solid rgb(255 255 255 / .15); border-radius: .75rem; transition: border-color .15s, box-shadow .15s, background-color .15s; }
        .login-input::placeholder { color: #94a3b8; opacity: 1; }
        .login-input:hover { border-color: rgb(255 255 255 / .28); background: rgb(255 255 255 / .08); }
        .login-input:focus { outline: none; border-color: #818cf8; background: rgb(255 255 255 / .08); box-shadow: 0 0 0 4px rgb(99 102 241 / .35); }
        .login-control:focus-within .login-lead-icon { color: #a5b4fc; }
        .login-input-trailing { padding-right: 3rem; }
        .login-input[aria-invalid='true'] { border-color: #fb7185; }
        .login-input[aria-invalid='true']:focus { box-shadow: 0 0 0 4px rgb(244 63 94 / .3); }
        .login-control:has(.login-input[aria-invalid='true']) .login-lead-icon { color: #fb7185; }
        /* Browser autofill: dark glass-like fill instead of the default white/yellow. */
        .login-input:-webkit-autofill, .login-input:-webkit-autofill:hover { -webkit-text-fill-color: #fff; caret-color: #c7d2fe;
            -webkit-box-shadow: 0 0 0 1000px #23244a inset; box-shadow: 0 0 0 1000px #23244a inset; transition: background-color 9999s ease-out 0s; }
        .login-input:-webkit-autofill:focus { -webkit-text-fill-color: #fff;
            -webkit-box-shadow: 0 0 0 1000px #23244a inset, 0 0 0 4px rgb(99 102 241 / .35);
                    box-shadow: 0 0 0 1000px #23244a inset, 0 0 0 4px rgb(99 102 241 / .35); }
        .login-input:autofill { background: #23244a; }
        .login-toggle { position: absolute; right: .375rem; top: 50%; transform: translateY(-50%); display: grid; place-items: center; width: 2.125rem; height: 2.125rem;
            border-radius: .5rem; color: #cbd5e1; background: transparent; border: 0; cursor: pointer; transition: background-color .15s, color .15s; }
        .login-toggle:hover { color: #fff; background: rgb(255 255 255 / .1); }
        .login-toggle:focus-visible { outline: 2px solid #818cf8; outline-offset: 1px; }
        .login-caps { display: flex; align-items: center; gap: .375rem; margin-top: .375rem; font-size: .75rem; color: #fcd34d; }
        .login-caps[hidden] { display: none; }

        .login-row { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .375rem .5rem; }
        .login-check { display: inline-flex; align-items: center; gap: .5rem; font-size: .8125rem; color: #e2e8f0; cursor: pointer; }
        .login-check input { width: 1rem; height: 1rem; border-radius: .25rem; accent-color: #6366f1; cursor: pointer; }
        .login-help { font-size: .75rem; color: #cbd5e1; }

        .login-submit { position: relative; overflow: hidden; display: inline-flex; align-items: center; justify-content: center; gap: .5rem; width: 100%; height: 2.875rem; margin-top: .5rem;
            border: 0; border-radius: .75rem; font-size: .9375rem; font-weight: 600; color: #fff; cursor: pointer;
            background: linear-gradient(135deg, #4f46e5 0%, #6d28d9 60%, #9333ea 100%);
            box-shadow: 0 1px 0 rgb(255 255 255 / .22) inset, 0 10px 24px -10px rgb(99 102 241 / .9);
            transition: transform .15s ease, box-shadow .15s ease, filter .15s ease; }
        .login-submit::after { content: ''; position: absolute; top: 0; bottom: 0; left: 0; width: 45%; pointer-events: none;
            background: linear-gradient(105deg, transparent, rgb(255 255 255 / .35), transparent); transform: translateX(-130%) skewX(-18deg); }
        .login-submit:hover::after { transform: translateX(260%) skewX(-18deg); transition: transform .8s ease; }
        .login-submit:hover { filter: brightness(1.08); box-shadow: 0 1px 0 rgb(255 255 255 / .22) inset, 0 14px 30px -10px rgb(129 140 248 / .95); transform: translateY(-1px); }
        .login-submit:active { transform: translateY(0); }
        .login-submit:focus-visible { outline: none; box-shadow: 0 0 0 4px rgb(129 140 248 / .45), 0 10px 22px -10px rgb(99 102 241 / .8); }
        .login-submit:disabled { cursor: progress; filter: saturate(.85); opacity: .85; transform: none; }
        .login-submit > * { position: relative; z-index: 1; }
        .login-arrow { transition: transform .2s ease; }
        .login-submit:hover .login-arrow { transform: translateX(3px); }
        .login-submit[aria-busy='true'] .login-arrow { display: none; }

        .login-secure { position: relative; display: block; padding: .875rem 1.5rem; font-size: .75rem; line-height: 1.45; text-align: center; text-wrap: balance;
            color: #cbd5e1; border-top: 1px solid rgb(255 255 255 / .1); background: rgb(255 255 255 / .04); }
        .login-secure svg { display: inline-block; vertical-align: -3px; margin-right: .375rem; color: #a5b4fc; }

        @media (max-width: 480px) {
            .login-side { padding: 1.5rem .75rem; }
            .login-card-body { padding: 2rem 1.25rem 1.75rem; }
            .login-secure { padding: .875rem 1rem; }
            .login-halo { inset: -6%; }
        }
        @media (prefers-reduced-motion: reduce) {
            .login-page *, .login-page *::before, .login-page *::after { animation: none !important; transition: none !important; }
            .login-parallax { transform: none !important; }
        }
    </style>
</head>
<body class="font-sans antialiased login-page">
    {{-- Decorative animated background --}}
    <div class="login-scene" aria-hidden="true">
        <div class="login-grid"></div>
        <div class="login-parallax" data-parallax>
            <div class="login-orb login-orb-1"></div>
            <div class="login-orb login-orb-2"></div>
            <div class="login-orb login-orb-3"></div>
            <div class="login-orb login-orb-4"></div>
        </div>
        <div class="login-vignette"></div>
    </div>

<div class="login-wrap">
    {{-- Brand side (large screens) --}}
    <aside class="login-brand" aria-hidden="true">
        {{-- Illustrative workflow chips: decoration only, not live data --}}
        <div class="login-chips" data-parallax-chips>
            <div class="login-chip-pos" style="top: 12.5%; right: -6%; --d: 1.2s">
                <div class="login-chip" style="--b: 0s"><span class="login-chip-dot" style="background: rgb(52 211 153 / .18); color: #6ee7b7"><x-icon name="check-square" :size="15" /></span><span>Task moved to Review<small>Homepage redesign</small></span></div>
            </div>
            <div class="login-chip-pos" style="bottom: 3%; right: 5%; --d: 1.6s">
                <div class="login-chip" style="--b: -4s"><span class="login-chip-dot" style="background: rgb(129 140 248 / .2); color: #c7d2fe"><x-icon name="badge-check" :size="15" /></span><span>Client approved scope</span></div>
            </div>
            <div class="login-chip-pos" style="top: 3%; left: 50%; --d: 1.8s">
                <div class="login-chip login-chip-progress" style="--b: -3s"><span class="flex items-center justify-between"><span>Sprint progress</span><x-icon name="trending-up" :size="14" /></span><span class="login-chip-bar"><span></span></span></div>
            </div>
        </div>

        <x-logo :size="40" class="login-rise login-brand-logo" style="--d: 0s" />

        <div class="login-brand-main">
            <span class="login-eyebrow login-rise" style="--d: .15s"><span class="login-eyebrow-dot"></span>Development Operations Center</span>
            <h2 class="login-headline">
                <span class="w" style="--i: 0">Every</span> <span class="w" style="--i: 1">project,</span> <span class="w" style="--i: 2">task</span> <span class="w" style="--i: 3">and</span>
                <span class="w" style="--i: 4">client</span> <span class="w" style="--i: 5">promise</span> <span class="w" style="--i: 6">—</span> <span class="w" style="--i: 7">in</span> <span class="w" style="--i: 8">one</span>
                <span class="w login-accent" style="--i: 9">calm</span> <span class="w login-accent" style="--i: 10">workflow.</span>
            </h2>
            <p class="login-lede login-rise" style="--d: 1s">Plan, deliver and follow up on client work from one shared place built for your development team.</p>
            <ul class="login-features">
                <li class="login-feature login-rise" style="--d: 1.15s"><span class="login-feature-icon" style="--g: 0s"><x-icon name="rocket" :size="18" /></span>
                    <span><span class="login-feature-title block">Lead to launch</span><span class="login-feature-text block">Track delivery with status rules the whole team can trust.</span></span></li>
                <li class="login-feature login-rise" style="--d: 1.3s"><span class="login-feature-icon" style="--g: 1.2s"><x-icon name="hourglass" :size="18" /></span>
                    <span><span class="login-feature-title block">Nothing slips</span><span class="login-feature-text block">See what is waiting on clients, what is blocked and what is due today.</span></span></li>
                <li class="login-feature login-rise" style="--d: 1.45s"><span class="login-feature-icon" style="--g: 2.4s"><x-icon name="shield-check" :size="18" /></span>
                    <span><span class="login-feature-title block">Secure by design</span><span class="login-feature-text block">Role-based access — accounts are created by your administrator.</span></span></li>
            </ul>
        </div>

        <p class="login-foot login-rise" style="--d: 1.6s">&copy; {{ now()->year }} {{ config('app.name') }} · Development Operations Center</p>
    </aside>

    {{-- Sign-in card --}}
    <main class="login-side">
        <div class="login-column login-rise" style="--d: .55s">
            <x-logo :size="40" class="login-mobile-logo" />

            <div class="login-card-wrap">
                <div class="login-halo" aria-hidden="true"></div>
                <div class="login-ring">
                <span class="login-ring-glow" aria-hidden="true"></span>
            <div class="login-card auth-card">
                <div class="login-card-body">
                    <div class="login-head">
                        <x-logo :size="40" :wordmark="false" class="login-mark" />
                        <h1 class="login-title">Welcome back</h1>
                        <p class="login-subtitle">Sign in to continue to your workspace.</p>
                    </div>

                    @if ($errors->any())
                        <div id="login-error" class="login-alert" role="alert">
                            <x-icon name="alert-triangle" :size="18" class="login-alert-icon" />
                            <div>
                                <p class="login-alert-title">We couldn't sign you in</p>
                                <p>{{ $errors->first('email') ?: $errors->first('password') ?: $errors->first() }}</p>
                            </div>
                        </div>
                    @endif
                    @if (session('status'))
                        <div class="login-alert login-alert-success" role="status">
                            <x-icon name="check-circle" :size="18" class="login-alert-icon" />
                            <p>{{ session('status') }}</p>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login.store') }}" class="login-form" data-login-form novalidate>
                        @csrf
                        <div class="login-field">
                            <label for="login-email" class="login-label">Email address</label>
                            <div class="login-control">
                                <x-icon name="mail" :size="18" class="login-lead-icon" />
                                <input id="login-email" type="email" name="email" value="{{ old('email') }}" class="login-input" required autofocus
                                       autocomplete="username" inputmode="email" placeholder="you@company.com" maxlength="255"
                                       aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" @if ($errors->has('email')) aria-describedby="login-error" @endif>
                            </div>
                        </div>

                        <div class="login-field">
                            <label for="login-password" class="login-label">Password</label>
                            <div class="login-control">
                                <x-icon name="lock" :size="18" class="login-lead-icon" />
                                <input id="login-password" type="password" name="password" class="login-input login-input-trailing" required autocomplete="current-password"
                                       placeholder="Enter your password" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                                       aria-describedby="login-caps{{ $errors->has('password') ? ' login-error' : '' }}">
                                <button type="button" class="login-toggle" data-password-toggle="login-password" aria-controls="login-password" aria-pressed="false" aria-label="Show password" title="Show password">
                                    <x-icon name="eye" :size="18" data-show-icon />
                                    <x-icon name="eye-off" :size="18" data-hide-icon hidden />
                                </button>
                            </div>
                            <p id="login-caps" class="login-caps" data-caps-warning hidden><x-icon name="info" :size="14" /> Caps Lock is on.</p>
                        </div>

                        <div class="login-row">
                            <label class="login-check">
                                <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                                <span>Keep me signed in</span>
                            </label>
                            <span class="login-help">Forgot password? Ask your admin.</span>
                        </div>

                        <button type="submit" class="login-submit" data-login-submit>
                            <x-icon name="loader" :size="18" class="spinner" data-busy-icon hidden />
                            <span data-label>Sign in</span>
                            <svg class="login-arrow" data-arrow-icon width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </button>
                    </form>
                </div>

                <p class="login-secure">
                    <x-icon name="shield-check" :size="15" />
                    <span>Accounts are created by your administrator — no self-registration.</span>
                </p>
            </div>
                </div>
            </div>

            <p class="login-copyright">&copy; {{ now()->year }} {{ config('app.name') }} · Development Operations Center</p>
        </div>
    </main>
</div>
<script>
    // Show/hide password and a loading state on submit. Works without the Vite bundle.
    document.querySelectorAll('[data-password-toggle]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const input = document.getElementById(btn.dataset.passwordToggle);
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.setAttribute('aria-pressed', String(show));
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            btn.title = show ? 'Hide password' : 'Show password';
            btn.querySelector('[data-show-icon]').toggleAttribute('hidden', show);
            btn.querySelector('[data-hide-icon]').toggleAttribute('hidden', !show);
            input.focus();
        });
    });
    document.querySelector('[data-login-form]')?.addEventListener('submit', (e) => {
        const form = e.currentTarget;
        if (!form.checkValidity()) {
            e.preventDefault();
            form.reportValidity();
            return;
        }
        const btn = form.querySelector('[data-login-submit]');
        btn.disabled = true;
        btn.setAttribute('aria-busy', 'true');
        btn.querySelector('[data-busy-icon]').removeAttribute('hidden'); // SVG has no .hidden property
        btn.querySelector('[data-label]').textContent = 'Signing in…';
    });
    // Caps Lock hint on the password field (real keyboard state, nothing stored).
    const capsWarning = document.querySelector('[data-caps-warning]');
    ['keydown', 'keyup'].forEach((type) => document.getElementById('login-password')?.addEventListener(type, (e) => {
        if (capsWarning && typeof e.getModifierState === 'function') {
            capsWarning.hidden = !e.getModifierState('CapsLock');
        }
    }));
    document.getElementById('login-password')?.addEventListener('blur', () => { if (capsWarning) capsWarning.hidden = true; });
    window.addEventListener('pageshow', () => {
        const btn = document.querySelector('[data-login-submit]');
        if (!btn) return;
        btn.disabled = false;
        btn.removeAttribute('aria-busy');
        btn.querySelector('[data-busy-icon]').setAttribute('hidden', '');
        btn.querySelector('[data-label]').textContent = 'Sign in';
    });
    // Subtle mouse parallax on the background orbs and chips (fine pointers only, off for reduced motion).
    (() => {
        const orbs = document.querySelector('[data-parallax]');
        const chips = document.querySelector('[data-parallax-chips]');
        if (!orbs || !matchMedia('(pointer: fine)').matches || matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        let tx = 0, ty = 0, x = 0, y = 0, raf = 0;
        const tick = () => {
            x += (tx - x) * 0.06; y += (ty - y) * 0.06;
            orbs.style.transform = `translate3d(${x * 40}px, ${y * 28}px, 0)`;
            if (chips) chips.style.transform = `translate3d(${x * -14}px, ${y * -10}px, 0)`;
            raf = Math.abs(tx - x) + Math.abs(ty - y) > 0.001 ? requestAnimationFrame(tick) : 0;
        };
        window.addEventListener('pointermove', (e) => {
            tx = e.clientX / innerWidth - 0.5; ty = e.clientY / innerHeight - 0.5;
            if (!raf) raf = requestAnimationFrame(tick);
        }, { passive: true });
    })();
</script>
</body>
</html>
