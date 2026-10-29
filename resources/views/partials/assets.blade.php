<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="alternate icon" href="{{ asset('favicon.ico') }}" sizes="any">
<meta name="theme-color" content="#4f46e5">
{{-- Built assets. Run `npm run build` (or `npm run dev`) so these exist. --}}
@if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
@endif
