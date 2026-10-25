<?php

namespace Core\Database;

use RuntimeException;

/** findOrFail() kaydı bulamadığında fırlatılır; HTTP isteğinde otomatik 404 olur. */
class ModelNotFoundException extends RuntimeException
{
    public function __construct(string $model, $id = null)
    {
        parent::__construct($id === null
            ? "{$model} kaydı bulunamadı."
            : "{$model} kaydı bulunamadı (id: {$id}).");
    }
}
