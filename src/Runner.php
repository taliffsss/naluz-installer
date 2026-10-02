<?php

declare(strict_types=1);

namespace Naluz\Installer;

/** Runs an external program. The command is an argv list, so nothing is ever interpreted by a shell. */
interface Runner
{
    /** @param list<string> $command @return int the exit code */
    public function run(array $command, ?string $cwd = null): int;
}
