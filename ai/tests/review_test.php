<?php

declare(strict_types=1);

require dirname(__DIR__) . '/review.php';

if (!function_exists('buildReviewMarkdown')) {
    throw new RuntimeException('buildReviewMarkdown() is not available yet.');
}

$review = buildReviewMarkdown(
    'Add a new Magento module feature',
    'This change adds a new module and updates DI configuration.',
    "app/code/Module/etc/di.xml\napp/code/Module/Model/Example.php",
    "diff --git a/app/code/Module/etc/di.xml b/app/code/Module/etc/di.xml\n+<type name=\"Foo\">\n+  <plugin name=\"bar\" />\n</type>\n",
    'Review prompt'
);

if (!str_contains($review, '## Summary')) {
    throw new RuntimeException('The review output is missing the Summary section.');
}

if (!str_contains($review, '## Summary Details')) {
    throw new RuntimeException('The review output is missing the summary details section.');
}

if (!str_contains($review, 'XML configuration changes are present')) {
    throw new RuntimeException('The review output should include XML-related guidance.');
}

if (!str_contains($review, 'di.xml')) {
    throw new RuntimeException('The review output should mention XML configuration changes.');
}

echo "Review test passed\n";
