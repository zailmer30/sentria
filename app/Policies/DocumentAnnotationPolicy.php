<?php

namespace App\Policies;

use App\Models\DocumentAnnotation;
use App\Models\User;

class DocumentAnnotationPolicy
{
    public function view(User $user, DocumentAnnotation $annotation): bool
    {
        return $annotation->user_id === $user->getKey();
    }

    public function update(User $user, DocumentAnnotation $annotation): bool
    {
        return $annotation->user_id === $user->getKey();
    }

    public function delete(User $user, DocumentAnnotation $annotation): bool
    {
        return $annotation->user_id === $user->getKey();
    }
}
