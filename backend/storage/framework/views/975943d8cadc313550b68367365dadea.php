<?php
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
?>
<!doctype html>
<html lang="<?php echo e($locale); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#4a141d">
    <meta name="description" content="<?php echo e($seo['description']); ?>">
    <meta property="og:title" content="<?php echo e($seo['title']); ?>">
    <meta property="og:description" content="<?php echo e($seo['description']); ?>">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo e($seo['title']); ?></title>
    <link rel="icon" href="/favicon.ico">
    <script>
        window.__APP_LOCALE__ = <?php echo e(Illuminate\Support\Js::from([
            'currentLanguage' => $locale,
            'availableLanguages' => $availableLocales,
            'fallbackLanguage' => $fallbackLocale,
        ])); ?>;
    </script>
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.ts']); ?>
</head>
<body><div id="app"></div></body>
</html>
<?php /**PATH C:\Users\User001\Downloads\tcc-unifast\resources\views/app.blade.php ENDPATH**/ ?>