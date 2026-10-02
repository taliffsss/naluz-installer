<?php

declare(strict_types=1);

namespace Naluz\Installer;

/** Parsed command line: `command arg1 arg2 --flag --key=value`. */
final class Input
{
    /** @var list<string> */
    public readonly array $arguments;
    /** @var array<string,string|true> */
    public readonly array $options;

    /** @param list<string> $argv arguments without the script name */
    public function __construct(array $argv)
    {
        $arguments = [];
        $options = [];
        $onlyArguments = false;
        foreach ($argv as $token) {
            if (!$onlyArguments && $token === '--') {
                $onlyArguments = true;
            } elseif (!$onlyArguments && str_starts_with($token, '--') && strlen($token) > 2) {
                $pair = explode('=', substr($token, 2), 2);
                $options[$pair[0]] = $pair[1] ?? true;
            } elseif (!$onlyArguments && preg_match('/^-[A-Za-z]$/', $token) === 1) {
                $options[$token[1]] = true;
            } else {
                $arguments[] = $token;
            }
        }
        $this->arguments = $arguments;
        $this->options = $options;
    }

    public function command(): ?string
    {
        return $this->arguments[0] ?? null;
    }

    public function argument(int $position): ?string
    {
        return $this->arguments[$position + 1] ?? null; // position 0 is the first argument after the command
    }

    public function has(string $name): bool
    {
        return isset($this->options[$name]);
    }

    public function option(string $name): ?string
    {
        $value = $this->options[$name] ?? null;
        return is_string($value) ? $value : null;
    }
}
