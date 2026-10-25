<?php

namespace Core\View;

/** Bir render işlemi boyunca bölümleri (section/push) ve miras zincirini tutar. */
class Environment
{
    protected array $sections = [];
    protected array $sectionStack = [];
    protected array $pushes = [];
    protected array $pushStack = [];
    protected ?string $extends = null;

    public function __construct(protected \Closure $renderer)
    {
    }

    /** Alt şablonun içinde çağrılır; render sonrasında layout çalıştırılır. */
    public function extend(string $layout): void
    {
        $this->extends = $layout;
    }

    public function pullExtends(): ?string
    {
        $layout = $this->extends;
        $this->extends = null;

        return $layout;
    }

    public function make(string $view, array $data = []): string
    {
        unset($data['compiledFile'], $data['__data'], $data['__env']);

        return ($this->renderer)($view, $data, $this);
    }

    public function startSection(string $name): void
    {
        $this->sectionStack[] = $name;
        ob_start();
    }

    public function inlineSection(string $name, string $content): void
    {
        $this->sections[$name] = $content;
    }

    /** @return string|null $show true ise bölümü hemen yazdırmak için içeriği döner */
    public function stopSection(bool $show = false): ?string
    {
        if ($this->sectionStack === []) {
            throw new \LogicException('@endsection için açık bir @section yok.');
        }

        $name = array_pop($this->sectionStack);
        $content = (string) ob_get_clean();

        // Alt şablon önce tanımlar; layout içindeki varsayılan bölüm onu ezmez
        $this->sections[$name] ??= $content;

        return $show ? $this->sections[$name] : null;
    }

    public function yieldContent(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    public function startPush(string $name): void
    {
        $this->pushStack[] = $name;
        ob_start();
    }

    public function stopPush(): void
    {
        $name = array_pop($this->pushStack);
        $this->pushes[$name][] = (string) ob_get_clean();
    }

    public function yieldPush(string $name): string
    {
        return implode('', $this->pushes[$name] ?? []);
    }

    /** Hata durumunda açık kalan tamponları kapatır. */
    public function openBuffers(): int
    {
        return count($this->sectionStack) + count($this->pushStack);
    }
}
