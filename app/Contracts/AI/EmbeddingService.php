<?php

namespace App\Contracts\AI;

interface EmbeddingService
{
    /**
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embed(array $texts): array;

    public function dimensions(): int;

    public function modelName(): string;
}
