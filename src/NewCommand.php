<?php

declare(strict_types=1);

namespace Naluz\Installer;

/** `naluz new <name>`: creates a project from the NaluzPHP application skeleton with Composer. */
final class NewCommand
{
    public const PACKAGE = 'naluz/naluzphp';
    public const DEV_VERSION = 'dev-master';

    /** @var list<string>|null */
    private ?array $composer;
    /** @var \Closure(string):bool */
    private \Closure $confirm;

    /**
     * @param list<string>|null $composer argv prefix that runs Composer (see ComposerLocator), or null if not installed
     * @param (\Closure(string):bool)|null $confirm asks a yes/no question; null = interactive terminal prompt
     */
    public function __construct(
        private readonly Runner $runner,
        private readonly Output $output,
        ?array $composer,
        private readonly string $cwd,
        private readonly bool $interactive = false,
        private readonly string $php = PHP_BINARY,
        ?\Closure $confirm = null,
    ) {
        $this->composer = $composer;
        $this->confirm = $confirm ?? static function (string $question): bool {
            echo $question . ' [Y/n] ';
            $answer = strtolower(trim((string) fgets(STDIN)));
            return $answer === '' || $answer === 'y' || $answer === 'yes';
        };
    }

    public function handle(Input $input): int
    {
        $name = $input->argument(0);
        if ($name === null || $name === '') {
            $this->output->error('Usage: naluz new <name> [--dir=PATH] [--dev] [--release=1.2.0] [--name=vendor/package] [--git] [--migrate] [--no-install]');
            return 1;
        }
        if (!self::validName($name)) {
            $this->output->error("Invalid project name [{$name}]. Use letters, digits, '.', '_' and '-' (or '.' for the current directory).");
            return 1;
        }

        $parent = $input->option('dir') ?? $this->cwd;
        if (!is_dir($parent)) {
            $this->output->error("The directory [{$parent}] does not exist.");
            return 1;
        }
        $target = $name === '.' ? rtrim($parent, '/\\') : rtrim($parent, '/\\') . DIRECTORY_SEPARATOR . $name;
        if (is_dir($target) && (glob($target . '/*') !== [] || glob($target . '/.[!.]*') !== [])) {
            $this->output->error("The directory [{$target}] is not empty. Choose another name, or empty it first.");
            return 1;
        }
        if (is_file($target)) {
            $this->output->error("[{$target}] is a file.");
            return 1;
        }

        $release = $input->has('dev') ? self::DEV_VERSION : $input->option('release');
        if ($release !== null && !self::validRelease($release)) {
            $this->output->error("Invalid --release value [{$release}]. Examples: 1.2.0, ^1.2, dev-master.");
            return 1;
        }
        $package = $input->option('name');
        if ($package !== null && !self::validPackageName($package)) {
            $this->output->error("Invalid --name [{$package}]. Use the form vendor/package in lower case.");
            return 1;
        }
        if ($this->composer === null) {
            $this->output->error('Composer was not found. Install it from https://getcomposer.org, or set COMPOSER_BINARY to its path.');
            return 1;
        }

        $this->output->line($this->output->bold('Creating a NaluzPHP project') . " in {$target} ...");
        $command = [
            ...$this->composer, 'create-project', self::PACKAGE, $target,
            ...($release !== null ? [$release] : []),
            '--prefer-dist',
            ...($this->interactive ? [] : ['--no-interaction']),
            // the skeleton's post-create script needs vendor/, so it is skipped when nothing is installed
            ...($input->has('no-install') ? ['--no-install', '--no-scripts'] : []),
        ];
        if (($code = $this->runner->run($command, $this->cwd)) !== 0) {
            $this->output->error("Composer failed (exit code {$code}); the project was not created.");
            return $code;
        }

        if ($package !== null) {
            $this->renamePackage($target, $package);
        }
        $migrated = false;
        if (!$input->has('no-install')) {
            $migrated = $this->prepare($target, $input);
        } elseif (is_file($target . '/.env.example') && !is_file($target . '/.env')) {
            copy($target . '/.env.example', $target . '/.env');
        }
        if ($input->has('git')) {
            $this->initGit($target);
        }

        $this->output->line();
        $this->output->info("Your NaluzPHP project is ready: {$target}");
        $this->output->line();
        $next = [];
        if ($name !== '.') {
            $next[] = 'cd ' . $name;
        }
        if ($input->has('no-install')) {
            $next[] = 'composer install';
            $next[] = 'php naluz key:generate --jwt';
        }
        if (!$migrated) {
            $next[] = 'php naluz migrate';
        }
        $next[] = 'php naluz run:server     # http://127.0.0.1:8000';
        $this->output->line('Next steps:');
        foreach ($next as $step) {
            $this->output->line('  ' . $step);
        }
        $this->output->line();
        $this->output->line('Documentation: https://taliffsss.github.io/naluz-framework-docs/');
        return 0;
    }

    public static function validName(string $name): bool
    {
        return $name === '.' || ($name !== '..' && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,99}$/', $name) === 1);
    }

    public static function validRelease(string $release): bool
    {
        // never starts with "-", so it can't be read as a Composer option
        return preg_match('/^[A-Za-z0-9^~<>=][A-Za-z0-9._\/*^~|<>=, -]{0,60}$/', $release) === 1;
    }

    public static function validPackageName(string $name): bool
    {
        return preg_match('/^[a-z0-9]([_.-]?[a-z0-9]+)*\/[a-z0-9](([_.]|-{1,2})?[a-z0-9]+)*$/', $name) === 1;
    }

    private function renamePackage(string $target, string $package): void
    {
        $file = $target . '/composer.json';
        $json = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (!is_array($json)) {
            $this->output->warn('Could not set the package name: composer.json was not found or is not valid.');
            return;
        }
        $json['name'] = $package;
        file_put_contents($file, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
    }

    /** @return bool whether the migrations ran successfully */
    private function prepare(string $target, Input $input): bool
    {
        if (!is_file($target . '/naluz')) {
            $this->output->warn('The project has no naluz script; skipping key generation.');
            return false;
        }
        if ($this->runner->run([$this->php, 'naluz', 'key:generate', '--jwt'], $target) !== 0) {
            $this->output->warn('Could not generate keys. Run `php naluz key:generate --jwt` inside the project.');
        }
        if (is_dir($target . '/storage') && !is_file($target . '/storage/database.sqlite')) {
            @touch($target . '/storage/database.sqlite'); // SQLite is the default database
        }
        $migrate = $input->has('migrate') || ($this->interactive && ($this->confirm)('Run the database migrations now?'));
        if (!$migrate) {
            return false;
        }
        if ($this->runner->run([$this->php, 'naluz', 'migrate'], $target) !== 0) {
            $this->output->warn('Migrations failed. Check your database settings in .env, then run `php naluz migrate`.');
            return false;
        }
        return true;
    }

    private function initGit(string $target): void
    {
        if ($this->runner->run(['git', 'init', '--quiet'], $target) !== 0) {
            $this->output->warn('git is not available; skipped `git init`.');
            return;
        }
        $this->runner->run(['git', 'add', '-A'], $target);
        if ($this->runner->run(['git', 'commit', '--quiet', '-m', 'Initial commit'], $target) !== 0) {
            $this->output->warn('Created the repository but could not commit (set git user.name and user.email, then commit).');
        }
    }
}
