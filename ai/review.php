<?php

declare(strict_types=1);

function buildReviewMarkdown(string $prTitle, string $prDescription, string $changedFiles, string $gitDiff, string $prompt): string
{
    $contextText = strtolower($prTitle . "\n" . $prDescription . "\n" . $changedFiles . "\n" . $gitDiff);

    $summaryItems = [];
    if (str_contains($contextText, 'di.xml') || str_contains($contextText, 'etc/')) {
        $summaryItems[] = '- XML configuration changes are present, so the review should confirm the placement is correct and compatible with Magento merge behavior.';
    }
    if (str_contains($contextText, 'plugin') || str_contains($contextText, 'preference')) {
        $summaryItems[] = '- The change touches extension points that should be reviewed for override depth and maintainability.';
    }
    if (str_contains($contextText, 'graphql') || str_contains($contextText, 'schema')) {
        $summaryItems[] = '- API or schema changes are present, so compatibility and backward-compatibility concerns should be checked.';
    }
    if (str_contains($contextText, 'db_schema') || str_contains($contextText, 'installschema') || str_contains($contextText, 'upgradeschema')) {
        $summaryItems[] = '- Database-related changes are present, which can affect upgrade safety and data migration paths.';
    }
    if ($summaryItems === []) {
        $summaryItems[] = '- The change appears to be a focused Magento extension update and should be reviewed for backward compatibility and extension safety.';
    }

    $criticalIssues = [];
    if (str_contains($contextText, 'preference')) {
        $criticalIssues[] = '- Prefer a plugin or service preference review before relying on a class rewrite; a preference can create upgrade and maintenance risk.';
    }
    if (str_contains($contextText, 'db_schema') || str_contains($contextText, 'installschema') || str_contains($contextText, 'upgradeschema')) {
        $criticalIssues[] = '- Verify that schema and data-install scripts are safe for existing stores and that there is a clear upgrade path.';
    }
    if ($criticalIssues === []) {
        $criticalIssues[] = '- No obvious blocking issue surfaced from the diff, but the implementation should still be checked for backward compatibility and production safety.';
    }

    $highPrioritySuggestions = [];
    if (str_contains($contextText, 'di.xml')) {
        $highPrioritySuggestions[] = '- Confirm that XML configuration is placed in the correct module scope and that it does not unintentionally affect unrelated areas.';
    }
    if (str_contains($contextText, 'plugin') || str_contains($contextText, 'observer')) {
        $highPrioritySuggestions[] = '- Review plugin and observer execution order to avoid side effects, recursion, or performance regressions.';
    }
    if ($highPrioritySuggestions === []) {
        $highPrioritySuggestions[] = '- Add or review regression coverage around the behavior being changed so future updates remain safe.';
    }

    $improvements = [];
    $improvements[] = '- Document the intended extension point and describe why the chosen approach is preferable to alternatives.';
    if (str_contains($contextText, 'graphql') || str_contains($contextText, 'api')) {
        $improvements[] = '- Keep public API and schema changes explicit, and avoid introducing breaking changes unless they are intentionally versioned.';
    }

    $positiveFeedback = [];
    $positiveFeedback[] = '- The change appears scoped and focused on a specific Magento concern, which is a good sign for maintainability.';

    $review = <<<EOT
$prompt

## PR Context

- **Title:** $prTitle
- **Description:** $prDescription

### Changed Files
$changedFiles

### Git Diff
```diff
$gitDiff
```

## Summary
- The PR appears to target a Magento extension change that should be reviewed for maintainability and compatibility.
EOT;

    $review .= "\n\n## Summary Details\n" . implode("\n", $summaryItems);
    $review .= "\n\n## Critical Issues\n" . implode("\n", $criticalIssues);
    $review .= "\n\n## High Priority Suggestions\n" . implode("\n", $highPrioritySuggestions);
    $review .= "\n\n## Improvements\n" . implode("\n", $improvements);
    $review .= "\n\n## Positive Feedback\n" . implode("\n", $positiveFeedback);

    return $review;
}

function generateReviewText(string $prTitle, string $prDescription, string $changedFiles, string $gitDiff, string $prompt): string
{
    $apiKey = getenv('OPENAI_API_KEY') ?: getenv('AZURE_OPENAI_API_KEY') ?: '';
    $apiBase = getenv('OPENAI_API_BASE') ?: getenv('AZURE_OPENAI_API_BASE') ?: 'https://api.openai.com/v1';
    $model = getenv('OPENAI_MODEL') ?: getenv('AZURE_OPENAI_MODEL') ?: 'gpt-4o-mini';
    $provider = getenv('AI_PROVIDER') ?: (str_contains($apiBase, 'openai.azure.com') ? 'azure' : 'openai');

    if ($apiKey === '') {
        fwrite(STDERR, "AI review: no API key found, using local fallback review.\n");
        return buildReviewMarkdown($prTitle, $prDescription, $changedFiles, $gitDiff, $prompt);
    }

    fwrite(STDERR, "AI review: calling provider '{$provider}' using model '{$model}' at '{$endpoint}'.\n");

    $requestBody = [
        'model' => $model,
        'messages' => [
            [
                'role' => 'system',
                'content' => $prompt,
            ],
            [
                'role' => 'user',
                'content' => sprintf(
                    "PR Title: %s\n\nPR Description: %s\n\nChanged Files:\n%s\n\nDiff:\n%s",
                    $prTitle,
                    $prDescription,
                    $changedFiles,
                    $gitDiff
                ),
            ],
        ],
        'temperature' => 0.2,
    ];

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ];

    $endpoint = $provider === 'azure'
        ? rtrim($apiBase, '/') . '/openai/deployments/' . $model . '/chat/completions?api-version=2024-02-01'
        : rtrim($apiBase, '/') . '/chat/completions';

    $payload = json_encode($requestBody, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => $headers,
            'content' => $payload,
            'timeout' => 30,
        ],
    ]);

    $response = @file_get_contents($endpoint, false, $context);
    if ($response === false) {
        fwrite(STDERR, "AI review: provider call failed, using local fallback review.\n");
        return buildReviewMarkdown($prTitle, $prDescription, $changedFiles, $gitDiff, $prompt);
    }

    fwrite(STDERR, "AI review: provider returned a response.\n");

    $data = json_decode($response, true);
    if (!is_array($data) || !isset($data['choices'][0]['message']['content'])) {
        fwrite(STDERR, "AI review: provider response did not contain review content, using local fallback review.\n");
        return buildReviewMarkdown($prTitle, $prDescription, $changedFiles, $gitDiff, $prompt);
    }

    fwrite(STDERR, "AI review: using provider-generated review content.\n");
    return trim((string) $data['choices'][0]['message']['content']);
}

if (PHP_SAPI === 'cli') {
    $prompt = file_get_contents(__DIR__ . '/prompts/magento-review.md');
    $prTitle = getenv('PR_TITLE') ?: 'No PR title provided';
    $prDescription = getenv('PR_DESCRIPTION') ?: 'No PR description provided';
    $changedFiles = getenv('CHANGED_FILES') ?: 'No changed files captured';
    $gitDiff = getenv('GIT_DIFF') ?: 'No git diff captured';

    echo generateReviewText($prTitle, $prDescription, $changedFiles, $gitDiff, $prompt);
}
