# CHANGELOG Rules

This document defines how to maintain `CHANGELOG.md` in the project root.

## Source and Purpose

- Changelog file: `CHANGELOG.md` (project root)
- This file is the source for GitHub Release notes
- Releases page: https://github.com/isapp/statamic-sensitive-form-fields/releases

## Entry Format

- Marketplace badge tags:
  - `[new]` for new functionality
  - `[fix]` for bug fixes
- Example entries:
  - `- [new] Added per-field sensitive toggle`
  - `- [fix] Prevented double encryption for already-marked values`

## Version Format

- Use SemVer: `MAJOR.MINOR.PATCH`
- Tag format: no `v` prefix (`1.0.0`, not `v1.0.0`)
- Version heading format in changelog:
  - `## X.Y.Z (YYYY-MM-DD)`
- Keep newest version at the top
- Keep an `## Unreleased` section at the top while developing

## Release Procedure (when user says "release X.Y.Z")

1. Pre-checks:
   - Ensure working tree is clean: `git status`
   - Run tests: `vendor/bin/phpunit`
   - Run style check: `vendor/bin/pint --test`
   - Verify `CHANGELOG.md` has entries under `## Unreleased`
2. Update `CHANGELOG.md`:
   - Rename `## Unreleased` to `## X.Y.Z (YYYY-MM-DD)`
   - Add a new empty `## Unreleased` section at the top
3. Commit release changelog:
   - `git add CHANGELOG.md`
   - `git commit -m "chore: prepare release X.Y.Z"`
4. Create git tag:
   - `git tag X.Y.Z`
5. Push branch and tag:
   - `git push origin <branch>`
   - `git push origin X.Y.Z`
6. Create GitHub Release from that changelog section:
   - `gh release create X.Y.Z --title "X.Y.Z" --notes "<release notes>"`
7. Verify release:
   - `gh release view X.Y.Z`
