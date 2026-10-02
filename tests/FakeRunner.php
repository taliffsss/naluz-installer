<?php

declare(strict_types=1);

namespace Naluz\Installer\Tests;

use Naluz\Installer\Runner;

/** Records commands instead of running them; `onRun` can simulate what a command would have done. */
final class FakeRunner implements Runner
{
    /** @var list<array{0:list<string>,1:?string}> */
    public array $calls = [];
    /** @var array<string,int> exit codes keyed by the command's first word after the binary, e.g. 'create-project' */
    public array $exitCodes = [];
    /** @var (\Closure(list<string>, ?string):void)|null */
    public ?\Closure $onRun = null;

    public function run(array $command, ?string $cwd = null): int
    {
        $this->calls[] = [$command, $cwd];
        if ($this->onRun !== null) {
            ($this->onRun)($command, $cwd);
        }
        foreach ($this->exitCodes as $needle => $code) {
            if (in_array($needle, $command, true)) {
                return $code;
            }
        }
        return 0;
    }

    /** @return list<string> every call as one string */
    public function lines(): array
    {
        return array_map(static fn (array $c) => implode(' ', $c[0]), $this->calls);
    }
}
