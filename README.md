# Aimsinfosoft CI Workflows

Shared CI/CD workflows for Magento 2 extensions.

## What this repository centralizes

- Composer validation
- Magento Coding Standard checks
- PHPCS on changed PHP files only
- PHPStan analysis with a shared configuration
- AI review context output for pull requests
- Magento compatibility hooks for later expansion

## Reusable workflow

The reusable workflow is exposed through [.github/workflows/quality-gate.yml](.github/workflows/quality-gate.yml).

Use it from a Magento 2 extension repository with a small caller workflow such as:

```yaml
name: Quality

on:
  pull_request:
  push:
    branches: [main]

jobs:
  quality:
    permissions:
      contents: read
    uses: aimsinfosoft/ci-workflows/.github/workflows/quality-gate.yml@v1.0.0
    with:
      php-version: "8.3"
      enable-phpstan: true
      enable-ai-review: false
      enable-magento-compile: false
```

## Why this structure is useful

Keeping the shared logic in a single repository avoids repeating workflow YAML in every extension project. The shared rulesets, PHPStan configuration, and QA tool versions stay consistent while each extension repository only needs a lightweight entrypoint.
