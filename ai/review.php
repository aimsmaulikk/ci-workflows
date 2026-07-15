<?php

declare(strict_types=1);

$prompt = file_get_contents(__DIR__ . '/prompts/magento-review.md');
$prTitle = getenv('PR_TITLE') ?: 'No PR title provided';
$prDescription = getenv('PR_DESCRIPTION') ?: 'No PR description provided';
$changedFiles = getenv('CHANGED_FILES') ?: 'No changed files captured';
$gitDiff = getenv('GIT_DIFF') ?: 'No git diff captured';

echo <<<EOT
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
EOT;