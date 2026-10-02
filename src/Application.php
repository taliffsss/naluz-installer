<?php

declare(strict_types=1);

namespace Naluz\Installer;

/**
 * The global `naluz` command.
 *
 *   naluz new my-app     create a project
 *   naluz <anything>     inside a NaluzPHP project, runs the project's own `php naluz <anything>`
 */
final class Application
{
    public const VERSION = '1.0.0';

    public function __construct(
        private readonly Output $output = new Output(),
        private readonly ?Runner $runner = null,
        private readonly ?string $cwd = null,
        private readonly ?bool $interactive = null,
    ) {
    }

    /** @param list<string> $argv full argv including the script name */
    public function run(array $argv): int
    {
        $tokens = array_slice($argv, 1);
        $input = new Input($tokens);
        $cwd = $this->cwd ?? (string) getcwd();
        $runner = $this->runner ?? new ProcessRunner();

        if ($input->command() === null && ($input->has('version') || $input->has('V'))) {
            $this->output->line('NaluzPHP Installer ' . self::VERSION);
            return 0;
        }

        if ($input->command() !== 'new' && self::isProject($cwd)) {
            // inside a project: `naluz migrate` is the same as `php naluz migrate`, and a bare `naluz` lists its commands
            return $runner->run([PHP_BINARY, 'naluz', ...($tokens === [] ? ['list'] : $tokens)], $cwd);
        }

        return match ($input->command()) {
            'new' => $this->newProject($input, $runner, $cwd),
            null, 'list', 'help' => $this->list(),
            default => $this->unknown($input->command()),
        };
    }

    public static function isProject(string $dir): bool
    {
        return is_file($dir . '/naluz') && is_file($dir . '/bootstrap/app.php');
    }

    private function newProject(Input $input, Runner $runner, string $cwd): int
    {
        $interactive = $this->interactive ?? (!$input->has('no-interaction') && function_exists('stream_isatty') && @stream_isatty(STDIN));
        $composer = ComposerLocator::find(getenv('COMPOSER_BINARY') ?: null, ComposerLocator::pathDirs(), $cwd);

        return (new NewCommand($runner, $this->output, $composer, $cwd, $interactive))->handle($input);
    }

    private function list(): int
    {
        $o = $this->output;
        $o->line('NaluzPHP Installer ' . self::VERSION);
        $o->line();
        $o->warn('Usage: naluz <command> [arguments] [--options]');
        $o->line();
        $o->line('  new <name>        Create a new NaluzPHP project');
        $o->line('  list              Show this list');
        $o->line();
        $o->line('Options for `new`:');
        $o->line('  --dir=PATH           create the project inside PATH (default: the current directory)');
        $o->line('  --release=VERSION    install a specific release, e.g. 1.2.0 or ^1.2 (default: the latest)');
        $o->line('  --dev                install the development version (dev-master)');
        $o->line('  --name=vendor/pkg    set the Composer package name of the new project');
        $o->line('  --git                run git init and make the first commit');
        $o->line('  --migrate            run the database migrations after installing');
        $o->line('  --no-install         do not run composer install');
        $o->line('  --no-interaction     never ask questions');
        $o->line();
        $o->line('Inside a NaluzPHP project, any other command is passed to the project: `naluz migrate` = `php naluz migrate`.');
        return 0;
    }

    private function unknown(?string $command): int
    {
        $this->output->error("Command [{$command}] is not defined. Run `naluz list`. (Project commands such as `migrate` work inside a NaluzPHP project.)");
        return 1;
    }
}
