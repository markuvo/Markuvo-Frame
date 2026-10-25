<?php

namespace Core\System;

use Throwable;

/**
 * Dosya tabanlı seviyeli log (storage/logs/<kanal>.log).
 *
 *   logger()->error('Ödeme alınamadı', ['siparis' => 12]);
 *   logger('db_errors')->error($sql);        // storage/logs/db_errors.log
 *
 * Güvenlik: bağlam (context) içindeki şifre/token/anahtar benzeri alanlar maskelenir,
 * mesajdaki satır sonları temizlenir (log sahteciliğini engeller).
 */
class Logger
{
    public const LEVELS = ['debug' => 100, 'info' => 200, 'notice' => 250, 'warning' => 300, 'error' => 400, 'critical' => 500, 'alert' => 550, 'emergency' => 600];

    public function __construct(
        protected string $directory,
        protected string $channel = 'app',
        protected bool $daily = false,
        protected string $minLevel = 'debug'
    ) {
    }

    public function channel(string $name): static
    {
        return new static($this->directory, preg_replace('/[^A-Za-z0-9_\-]/', '', $name) ?: 'app', $this->daily, $this->minLevel);
    }

    public function path(): string
    {
        $name = $this->channel . ($this->daily ? '-' . date('Y-m-d') : '');

        return rtrim($this->directory, '/\\') . DIRECTORY_SEPARATOR . $name . '.log';
    }

    public function log(string $level, string|\Stringable $message, array $context = []): void
    {
        $level = strtolower($level);

        if ((self::LEVELS[$level] ?? 0) < (self::LEVELS[$this->minLevel] ?? 0)) {
            return;
        }

        $line = sprintf(
            '[%s] %s.%s: %s%s',
            date('Y-m-d H:i:s'),
            $this->channel,
            strtoupper($level),
            $this->oneLine((string) $message),
            $context === [] ? '' : ' ' . json_encode($this->sanitize($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR)
        ) . PHP_EOL;

        $this->write($line);
    }

    public function debug(string|\Stringable $m, array $c = []): void { $this->log('debug', $m, $c); }
    public function info(string|\Stringable $m, array $c = []): void { $this->log('info', $m, $c); }
    public function notice(string|\Stringable $m, array $c = []): void { $this->log('notice', $m, $c); }
    public function warning(string|\Stringable $m, array $c = []): void { $this->log('warning', $m, $c); }
    public function error(string|\Stringable $m, array $c = []): void { $this->log('error', $m, $c); }
    public function critical(string|\Stringable $m, array $c = []): void { $this->log('critical', $m, $c); }

    /** Bir istisnayı (sınıf, mesaj, dosya:satır) tek satır olarak error seviyesinde yazar. */
    public function exception(Throwable $e, array $context = []): void
    {
        $this->error(sprintf('%s: %s in %s:%d', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()), $context);
    }

    protected function write(string $line): void
    {
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0755, true) && !is_dir($this->directory)) {
            error_log(rtrim($line));
            return;
        }

        if (@file_put_contents($this->path(), $line, FILE_APPEND | LOCK_EX) === false) {
            error_log(rtrim($line));
        }
    }

    protected function oneLine(string $text): string
    {
        return str_replace(["\r\n", "\r", "\n"], ' ', $text);
    }

    protected function sanitize(array $context): array
    {
        $clean = [];

        foreach ($context as $key => $value) {
            if (is_string($key) && preg_match('/pass|token|secret|authorization|cookie|api[_-]?key|credit/i', $key)) {
                $clean[$key] = '***';
            } elseif (is_array($value)) {
                $clean[$key] = $this->sanitize($value);
            } elseif ($value instanceof Throwable) {
                $clean[$key] = get_class($value) . ': ' . $value->getMessage();
            } elseif (is_scalar($value) || $value === null) {
                $clean[$key] = is_string($value) ? $this->oneLine($value) : $value;
            } else {
                $clean[$key] = '[' . get_debug_type($value) . ']';
            }
        }

        return $clean;
    }
}
