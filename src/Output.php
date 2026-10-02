<?php

declare(strict_types=1);

namespace Naluz\Installer;

/** Writes to a stream; adds colour only for terminals (and never when NO_COLOR is set). */
final class Output
{
    /** @var resource */
    private $out;
    /** @var resource */
    private $err;
    private bool $color;

    /**
     * @param resource|null $out
     * @param resource|null $err
     */
    public function __construct($out = null, $err = null, ?bool $color = null)
    {
        $this->out = $out ?? STDOUT;
        $this->err = $err ?? STDERR;
        $this->color = $color ?? (getenv('NO_COLOR') === false && function_exists('stream_isatty') && @stream_isatty($this->out));
    }

    public function line(string $text = ''): void
    {
        fwrite($this->out, $text . "\n");
    }

    public function info(string $text): void
    {
        $this->line($this->paint('32', $text));
    }

    public function warn(string $text): void
    {
        $this->line($this->paint('33', $text));
    }

    public function error(string $text): void
    {
        fwrite($this->err, $this->paint('31', $text) . "\n");
    }

    public function bold(string $text): string
    {
        return $this->paint('1', $text);
    }

    private function paint(string $code, string $text): string
    {
        return $this->color ? "\033[{$code}m{$text}\033[0m" : $text;
    }
}
