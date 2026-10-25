<?php

namespace Core\Support;

use Countable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * Sayfalanmış sonuç: hem veriyi hem HTML sayfalama bağlantılarını üretir.
 *
 *   $bloglar = BlogModel::where('durum', 'aktif')->paginate(10);
 *   @foreach($bloglar as $blog) ... @endforeach
 *   {!! $bloglar->links() !!}
 */
class Paginator implements Countable, IteratorAggregate, JsonSerializable
{
    protected int $lastPage;
    protected array $options;

    public function __construct(
        protected Collection $items,
        protected int $total,
        protected int $perPage,
        protected int $currentPage,
        array $options = []
    ) {
        $this->options = array_merge(['path' => '/', 'query' => [], 'pageName' => 'page'], $options);
        $this->perPage = max(1, $perPage);
        $this->lastPage = max(1, (int) ceil($total / $this->perPage));
        $this->currentPage = max(1, $currentPage);
    }

    public function items(): Collection
    {
        return $this->items;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function currentPage(): int
    {
        return $this->currentPage;
    }

    public function lastPage(): int
    {
        return $this->lastPage;
    }

    public function hasPages(): bool
    {
        return $this->lastPage > 1;
    }

    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage;
    }

    public function onFirstPage(): bool
    {
        return $this->currentPage <= 1;
    }

    public function url(int $page): string
    {
        $query = array_merge($this->options['query'], [$this->options['pageName'] => max(1, $page)]);

        return $this->options['path'] . '?' . http_build_query($query);
    }

    /** Sayfalama bağlantılarının HTML'i. Tüm değerler kaçışlanır. */
    public function links(): string
    {
        if (!$this->hasPages()) {
            return '';
        }

        $e = fn(string $v) => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<nav class="pagination" role="navigation" aria-label="Sayfalama"><ul>';

        $html .= $this->onFirstPage()
            ? '<li class="prev disabled"><span>&laquo;</span></li>'
            : '<li class="prev"><a href="' . $e($this->url($this->currentPage - 1)) . '" rel="prev">&laquo;</a></li>';

        $previous = 0;
        foreach ($this->window() as $page) {
            if ($page - $previous > 1) {
                $html .= '<li class="ellipsis disabled"><span>&hellip;</span></li>';
            }

            $html .= $page === $this->currentPage
                ? '<li class="active"><span aria-current="page">' . $page . '</span></li>'
                : '<li><a href="' . $e($this->url($page)) . '">' . $page . '</a></li>';

            $previous = $page;
        }

        $html .= $this->hasMorePages()
            ? '<li class="next"><a href="' . $e($this->url($this->currentPage + 1)) . '" rel="next">&raquo;</a></li>'
            : '<li class="next disabled"><span>&raquo;</span></li>';

        return $html . '</ul></nav>';
    }

    /** İlk, son ve geçerli sayfanın ±2 komşusu. */
    protected function window(): array
    {
        $pages = [1, $this->lastPage];

        for ($i = $this->currentPage - 2; $i <= $this->currentPage + 2; $i++) {
            if ($i >= 1 && $i <= $this->lastPage) {
                $pages[] = $i;
            }
        }

        $pages = array_unique($pages);
        sort($pages);

        return $pages;
    }

    public function toArray(): array
    {
        return [
            'data' => $this->items->toArray(),
            'total' => $this->total,
            'per_page' => $this->perPage,
            'current_page' => $this->currentPage,
            'last_page' => $this->lastPage,
        ];
    }

    public function jsonSerialize(): array
    {
        return array_merge($this->toArray(), ['data' => $this->items->jsonSerialize()]);
    }

    public function getIterator(): Traversable
    {
        return $this->items->getIterator();
    }

    public function count(): int
    {
        return $this->items->count();
    }
}
