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

if (PHP_SAPI === 'cli') {
    $prompt = file_get_contents(__DIR__ . '/prompts/magento-review.md');
    $prTitle = getenv('PR_TITLE') ?: 'No PR title provided';
    $prDescription = getenv('PR_DESCRIPTION') ?: 'No PR description provided';
    $changedFiles = getenv('CHANGED_FILES') ?: 'No changed files captured';
    $gitDiff = getenv('GIT_DIFF') ?: 'No git diff captured';

    echo buildReviewMarkdown($prTitle, $prDescription, $changedFiles, $gitDiff, $prompt);
}
