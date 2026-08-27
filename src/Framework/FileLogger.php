<?php

declare(strict_types=1);

namespace App\Framework;

use Psr\Log\AbstractLogger;
use Stringable;

/**
 * PSR-3 logger that appends every entry to a project-local log file.
 *
 * Used for the two IPMA channels: cache write failures (permission denied,
 * disk full, …) in `var/log/ipma-cache.log`, and the upstream requests
 * recorded by {@see LoggingCache} in `var/log/ipma-requests.log`. FPM's
 * `catch_workers_output` is off in this image, so writing through
 * `error_log()` would be dropped.
 */
final class FileLogger extends AbstractLogger
{
    public function __construct(
        private readonly string $logFile,
        private readonly string $channel = 'ipma-cache',
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $rendered = $this->interpolate((string) $message, $context);

        if (isset($context['exception']) && $context['exception'] instanceof \Throwable) {
            $rendered .= ' [' . $context['exception']::class . ': ' . $context['exception']->getMessage() . ']';
        }

        $line = sprintf(
            "[%s] [%s] %s: %s\n",
            date('c'),
            $this->channel,
            strtoupper((string) $level),
            $rendered,
        );

        $dir = dirname($this->logFile);
        if (!is_dir($dir) && @!mkdir($dir, 0o775, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('Cannot create log directory "%s".', $dir));
        }

        if (file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX) === false) {
            throw new \RuntimeException(sprintf('Cannot write to log file "%s".', $this->logFile));
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function interpolate(string $message, array $context): string
    {
        if ($context === [] || !str_contains($message, '{')) {
            return $message;
        }

        $replacements = [];
        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value instanceof Stringable || $value === null) {
                $replacements['{' . $key . '}'] = (string) $value;
            }
        }

        return strtr($message, $replacements);
    }
}
