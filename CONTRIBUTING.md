# IntegratedProfiles

MediaWiki 1.46+ extension.

# Coding style

Apply these rules to code you write in this repo. Do not rename third-party or MediaWiki APIs to match them, obviously.

## Naming (`snake_case`)

Use `snake_case` for variables, parameters, and functions you define.

**With these exceptions:**

- PHP namespaces and classes: `MediaWiki\Extension\IntegratedProfiles\...`
- MediaWiki globals and extension surface: `mw`, `mw.config`, `mw.message`, `mw.hook`
- Object literal keys and JSON/config shapes from ResourceLoader / Action API
- Preference keys and config registry names (`ip-about`, `IntegratedProfilesColor`)

As for everything else, see: https://github.com/obbywiki/standards

# Tests

`pnpm test` runs the Vitest suite in `tests/vitest`. `composer test` is PHP lint (syntax + PHPCS). `composer phpunit` runs PHPUnit in `tests/phpunit`. See the Contributing section of `README.md` for what each file actually checks.

PHPUnit in this repository boots without MediaWiki. The masthead renderer tests need MediaWiki's template parser and run in the MediaWiki PHPUnit CI job.