<?php

declare(strict_types=1);

$templateMap = [
    'template_1' => __DIR__ . '/../public/templates/classic.html',
    'template_2' => __DIR__ . '/../public/templates/dark.html',
    'template_5' => __DIR__ . '/../public/templates/terracotta.html',
    'template_6' => __DIR__ . '/../public/templates/lumiere.html',
    'template_7' => __DIR__ . '/../public/templates/rose.html',
    'template_8' => __DIR__ . '/../public/templates/starry.html',
];

$requiredBlockIds = [
    'hero-names',
    'hero-date',
    'wedding-date',
    'venue-name',
    'venue-address',
    'venue-lat',
    'venue-lng',
];

$hasErrors = false;

foreach ($templateMap as $templateId => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, sprintf("%s: file not found: %s\n", $templateId, $path));
        $hasErrors = true;
        continue;
    }

    $html = (string) file_get_contents($path);
    preg_match_all('/data-block-id="([^"]+)"/', $html, $matches);
    $blockIds = array_unique($matches[1] ?? []);
    $missing = array_values(array_diff($requiredBlockIds, $blockIds));

    if ($missing === []) {
        echo sprintf("%s: OK\n", $templateId);
        continue;
    }

    $hasErrors = true;
    echo sprintf("%s: missing %s\n", $templateId, implode(', ', $missing));
}

exit($hasErrors ? 1 : 0);
