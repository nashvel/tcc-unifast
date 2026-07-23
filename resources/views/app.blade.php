@php
    $locale = $locale ?? app()->getLocale();
    $availableLocales = $availableLocales ?? ['en', 'tl', 'ceb'];
    $fallbackLocale = $fallbackLocale ?? 'en';
    $seo = match ($locale) {
        'tl' => [
            'title' => 'UniFAST TES · Tagoloan Community College',
            'description' => 'Pamahalaan ang TES grantees, dokumento, batch, ulat, at eligibility.',
        ],
        'ceb' => [
            'title' => 'UniFAST TES · Tagoloan Community College',
            'description' => 'Dumala ang TES grantees, dokumento, batch, report, ug eligibility.',
        ],
        default => [
            'title' => 'UniFAST TES · Tagoloan Community College',
            'description' => 'Manage TES grantees, documents, batches, reports, and eligibility.',
        ],
    };
@endphp
<!doctype html>
<html lang="{{ $locale }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#4a141d">
    <meta name="description" content="{{ $seo['description'] }}">
    <meta property="og:title" content="{{ $seo['title'] }}">
    <meta property="og:description" content="{{ $seo['description'] }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $seo['title'] }}</title>
    <link rel="icon" href="/favicon.ico">
    <script>
        window.__APP_LOCALE__ = {{ Illuminate\Support\Js::from([
            'currentLanguage' => $locale,
            'availableLanguages' => $availableLocales,
            'fallbackLanguage' => $fallbackLocale,
        ]) }};
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.ts'])
</head>
<body><div id="app"></div></body>
</html>
