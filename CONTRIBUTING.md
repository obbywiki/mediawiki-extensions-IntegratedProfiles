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

As for everything else, see: <https://github.com/obbywiki/standards>

# Tests

`pnpm test` runs the Vitest suite in `tests/vitest`. `composer test` is PHP lint (syntax + PHPCS).

PHPUnit lives under `tests/phpunit/Unit` and `tests/phpunit/Integration`, using MediaWiki's `MediaWikiUnitTestCase` / `MediaWikiIntegrationTestCase`. It runs in CI automatically on push and pull requests. Locally, you can use mwenv (see below) or any MediaWiki checkout with IntegratedProfiles at `extensions/IntegratedProfiles`, before running `composer phpunit`.

## PHP tests with mwenv

[mwenv](https://github.com/ProfessionalWiki/mwenv) gives each git worktree its own MediaWiki, database, and port, so several branches can run their suites at once.
It runs on Linux, macOS, and WSL (having your local repository under WSL is heavily recommended), and needs git plus Docker with Compose. In WSL, create worktrees with WSL git, as a worktree made by Windows git records a `C:/` path that mwenv cannot follow (although some modern Git features can work-around this, that hasn't been tested).

From the worktree, set up a test wiki, then run the suite:

```sh
scripts/mwenv-setup
mwenv run -- composer phpunit
mwenv run -- composer phpunit -- --filter test_some_name
```

`scripts/mwenv-setup` defaults to MediaWiki 1.46, PHP 8.3, and MariaDB, and also installs FloatingUI so the profile card and chip tooltips can be tried in the browser (the wiki URL and admin login are printed on start).
CI uses SQLite and tests MediaWiki 1.45 with PHP 8.3 and 1.46 with PHP 8.5. To match one, run (e.g.) `MW=1.45 scripts/mwenv-setup --db sqlite` or `PHP=8.5 scripts/mwenv-setup --db sqlite` in a fresh worktree or after `mwenv destroy`.

NewAuth is private, so the verified connections integration is not set up here and needs manual testing on a wiki that has it. Or, if you have a local checkout, you could probably add it manually to the checkout.

The first start builds images, which takes a few minutes. Later worktrees start in under a minute. The setup script is safe to run again, and `mwenv destroy` removes the wiki and its database.

For PHP lint inside the container (`composer test` on the host works too):

```sh
mwenv run -- sh -c 'composer install && composer test'
```
