# Development

## Requirements

- PHP 8.2+
- Statamic 6
- Laravel 12

## Setup

```bash
composer install
```

## Testing

```bash
vendor/bin/phpunit
```

## Contributing

1. Fork the repository.
2. Create a feature branch.
3. Write tests for your changes.
4. Run `vendor/bin/phpunit` and ensure all tests pass.
5. Submit a pull request.

## Documentation

Architecture, data flow, and implementation details are in `docs/`:

- [docs/OVERVIEW.md](docs/OVERVIEW.md) — architecture, file tree, data flow, settings, permissions
- [docs/PLAN.md](docs/PLAN.md) — implementation plan and known limitations
- [docs/CHANGELOG.md](docs/CHANGELOG.md) — instructions for maintaining root `CHANGELOG.md`
- [docs/statamic-building-addon.md](docs/statamic-building-addon.md) — Statamic addon reference
- [docs/statamic-testing.md](docs/statamic-testing.md) — Statamic testing reference
- [CHANGELOG.md](CHANGELOG.md) — release changelog entries used for GitHub Releases

## Releases and Changelog

- Changelog source: GitHub Releases for this repository (`git tag` + release notes)
- Release notes custom tags:
  - `[new]` for new functionality badge
  - `[fix]` for fixed bug badge
- Versioning: Semantic Versioning (`MAJOR.MINOR.PATCH`)
- Tag format: no `v` prefix (`1.0.0`, not `v1.0.0`)
