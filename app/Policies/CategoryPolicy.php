<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('categories.view');
    }

    public function view(User $user, Category $category): bool
    {
        return $user->hasPermissionTo('categories.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('categories.manage');
    }

    public function update(User $user, Category $category): bool
    {
        return $user->hasPermissionTo('categories.manage');
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->hasPermissionTo('categories.manage');
    }
}
