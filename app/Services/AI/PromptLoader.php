<?php

namespace App\Services\AI;

use RuntimeException;

class PromptLoader
{
    public function load(string $name): string
    {
        $path = resource_path("prompts/{$name}.md");

        if (! is_file($path)) {
            throw new RuntimeException("Prompt template not found: {$name}");
        }

        return trim((string) file_get_contents($path));
    }
}
