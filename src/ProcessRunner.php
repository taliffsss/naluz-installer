<?php

declare(strict_types=1);

namespace Naluz\Installer;

/** Runs a program with the terminal's own stdin/stdout/stderr, so Composer's progress and prompts work. */
final class ProcessRunner implements Runner
{
    public function run(array $command, ?string $cwd = null): int
    {
        $process = @proc_open($command, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, $cwd);
        if (!is_resource($process)) {
            return 127;
        }
        return proc_close($process);
    }
}
