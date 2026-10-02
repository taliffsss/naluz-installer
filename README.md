# NaluzPHP Installer

The global command-line installer for [NaluzPHP](https://github.com/taliffsss/naluzphp-framework) projects.

```bash
composer global require naluz/installer
naluz new my-app
```

Requires PHP 8.2+ and [Composer](https://getcomposer.org/). The installer itself has no dependencies.

## Install

```bash
composer global require naluz/installer
```

Make sure Composer's global `bin` directory is on your `PATH`. Find it with `composer global config bin-dir --absolute`
(commonly `~/.composer/vendor/bin` or `~/.config/composer/vendor/bin`; on Windows `%APPDATA%\Composer\vendor\bin`).

Check it works:

```bash
naluz --version
```

> **Note**
Until the package is registered on [Packagist](https://packagist.org/), install it from GitHub:

```bash
composer global config repositories.naluz-installer vcs https://github.com/taliffsss/naluz-installer
composer global require naluz/installer:dev-main
```

Update it with `composer global update naluz/installer`, and remove it with `composer global remove naluz/installer`.

## Create a project

```bash
naluz new my-app
cd my-app
php naluz run:server
```

`naluz new` runs `composer create-project` for the NaluzPHP application skeleton, then:

1. generates `APP_KEY` and `JWT_SECRET` in `.env`,
2. creates the default SQLite database file (`storage/database.sqlite`),
3. optionally runs the database migrations (it asks, or use `--migrate`),
4. optionally initializes a Git repository (`--git`).

| Option | Meaning |
|---|---|
| `--dir=PATH` | create the project inside `PATH` (default: the current directory) |
| `--release=VERSION` | install a specific release, e.g. `1.2.0` or `^1.2` (default: the latest stable release) |
| `--dev` | install the development version (`dev-master`) |
| `--name=vendor/package` | set the Composer package name of the new project |
| `--git` | run `git init` and make the first commit |
| `--migrate` | run the migrations after installing, without asking |
| `--no-install` | create the files only; run `composer install` yourself afterwards |
| `--no-interaction` | never ask questions (also the default when there is no terminal) |

```bash
naluz new shop --name=acme/shop --migrate --git
naluz new . --dev                     # into the current (empty) directory
naluz new api --dir=~/Sites --release=^1.2
```

The target directory must not exist or must be empty. The installer never deletes anything.

## Commands inside a project

Inside a NaluzPHP project (a folder with `naluz` and `bootstrap/app.php`), any other command is passed to the project's own
`naluz` script, so these are equivalent:

```bash
naluz migrate         php naluz migrate
naluz make:model Post php naluz make:model Post
naluz                 php naluz list
```

`naluz new` and `naluz --version` always run the installer.

## Security

- The project name is validated (`[A-Za-z0-9._-]`, no path separators, no `..`), and so are `--release` and `--name`, so values
  cannot be turned into Composer options or paths.
- Programs are started with an argument list, never through a shell, so nothing in a name is interpreted by `sh`.
- The installer only forwards commands when the current directory really looks like a NaluzPHP project, and never executes
  unrelated files.
- The skeleton is fetched by Composer from `https://github.com/taliffsss/naluzphp-framework`, with TLS verification and
  Composer's usual integrity checks.

## Troubleshooting

| Message | Fix |
|---|---|
| `Composer was not found` | install Composer, or set `COMPOSER_BINARY` to its path |
| `The directory [...] is not empty` | choose another name, or empty the directory |
| `Composer failed (exit code N)` | read Composer's output above it (network, PHP version or extension problems are the usual causes) |
| `naluz: command not found` | add Composer's global `bin` directory to your `PATH` |

## Development

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpcs
php bin/naluz list
```

Releases: tag `vX.Y.Z` on `main`. See [CHANGELOG.md](CHANGELOG.md).

## License

MIT © Mark Anthony Naluz
