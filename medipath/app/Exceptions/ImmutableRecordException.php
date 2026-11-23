<?php

namespace App\Exceptions;

use RuntimeException;

class ImmutableRecordException extends RuntimeException
{
    public static function forModel(string $model, string $field): self
    {
        return new self(sprintf('%s.%s is immutable and cannot be changed.', class_basename($model), $field));
    }
}
