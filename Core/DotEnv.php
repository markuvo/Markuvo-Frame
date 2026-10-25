<?php

namespace Core;

/**
 * .env okuyucu.
 *
 *   ANAHTAR=deger
 *   ANAHTAR="boşluklu değer # bu yorum sayılmaz"
 *   ANAHTAR=deger # bu satır sonu yorumudur
 *   export ANAHTAR=deger
 *
 * Sistemde zaten tanımlı olan bir ortam değişkeni .env tarafından ezilmez.
 */
class DotEnv
{
    protected string $path;

    public function __construct(string $path)
    {
        if (!file_exists($path)) {
            throw new \InvalidArgumentException(".env dosyası bulunamadı: {$path}");
        }

        $this->path = $path;
    }

    public function load(): void
    {
        $lines = file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            // UTF-8 BOM (Windows/Atom bazen ekler) ilk anahtarı bozmasın
            $line = ltrim($line, "\xEF\xBB\xBF");

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = ltrim(substr($line, 7));
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);

            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $name)) {
                continue;
            }

            $value = $this->parseValue(ltrim($value));

            if (getenv($name) === false && !array_key_exists($name, $_ENV)) {
                putenv("{$name}={$value}");
                $_ENV[$name] = $value;
            }
        }
    }

    protected function parseValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $quote = $value[0];

        if ($quote === '"' || $quote === "'") {
            $end = $this->closingQuote($value, $quote);

            if ($end !== null) {
                $inner = substr($value, 1, $end - 1);

                return $quote === '"'
                    ? strtr($inner, ['\\"' => '"', '\\\\' => '\\', '\\n' => "\n"])
                    : $inner;
            }
        }

        // Tırnaksız: " #" ile başlayan satır sonu yorumunu at
        $value = preg_replace('/\s+#.*$/', '', $value);

        return trim($value);
    }

    protected function closingQuote(string $value, string $quote): ?int
    {
        $length = strlen($value);

        for ($i = 1; $i < $length; $i++) {
            if ($value[$i] === '\\' && $quote === '"') {
                $i++;
                continue;
            }

            if ($value[$i] === $quote) {
                return $i;
            }
        }

        return null;
    }
}
