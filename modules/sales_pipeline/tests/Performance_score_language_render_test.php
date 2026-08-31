<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);

$languageFiles = [
    'vi' => dirname(__DIR__) . '/language/vietnamese/sales_pipeline_lang.php',
    'en' => dirname(__DIR__) . '/language/english/sales_pipeline_lang.php',
];

foreach ($languageFiles as $locale => $languageFile) {
    $lang = [];
    require $languageFile;

    $key = 'sales_pipeline_quote_acceptance_rate_percent';
    if (!isset($lang[$key])) {
        fwrite(STDERR, "FAIL: Missing {$key} for {$locale}\n");
        exit(1);
    }

    try {
        // Mirrors the no-label branch used by Perfex CRM's _l() helper.
        $rendered = sprintf($lang[$key], '');
    } catch (ValueError $error) {
        fwrite(STDERR, "FAIL: {$locale} label breaks _l(): {$error->getMessage()}\n");
        exit(1);
    }

    if (strpos($rendered, '(%)') === false) {
        fwrite(STDERR, "FAIL: {$locale} label must render a literal percent sign\n");
        exit(1);
    }
}

fwrite(STDOUT, "PASS: Performance Score language rendering\n");
