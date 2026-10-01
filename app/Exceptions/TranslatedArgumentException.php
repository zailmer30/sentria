<?php

namespace App\Exceptions;

use InvalidArgumentException;

class TranslatedArgumentException extends InvalidArgumentException
{
    /**
     * @param  array<string, string|int>  $replacements
     */
    public function __construct(
        string $key,
        public readonly array $replacements = [],
    ) {
        parent::__construct($key);
    }
}
