<?php

namespace Core\Database;

use RuntimeException;

/** $fillable dışındaki bir alanın toplu atama (fill/create) ile yazılmaya çalışılması. */
class MassAssignmentException extends RuntimeException
{
    public function __construct(string $model, string $attribute)
    {
        parent::__construct(
            "{$model}: '{$attribute}' alanı toplu atamaya kapalı. Modelde \$fillable dizisine ekleyin."
        );
    }
}
