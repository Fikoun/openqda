<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\Project;
use App\Models\User;

class CategoryPolicy extends BasePolicy
{
    /**
     * Determine whether the user can view categories for the project.
     */
    public function viewAny(User $user, Project $project): bool
    {
        return $this->isUserInProjectOrTeam($user, null, $project) || in_array($user->email, $this->allowedEmails);
    }

    /**
     * Determine whether the user can create categories.
     */
    public function create(User $user, Project $project): bool
    {
        return $this->isUserInProjectOrTeam($user, null, $project) || in_array($user->email, $this->allowedEmails);
    }

    /**
     * Determine whether the user can update the category.
     */
    public function update(User $user, Category $category): bool
    {
        return $this->isUserInProjectOrTeam($user, null, $category->project) || in_array($user->email, $this->allowedEmails);
    }

    /**
     * Determine whether the user can delete the category.
     */
    public function delete(User $user, Category $category): bool
    {
        return $this->isUserInProjectOrTeam($user, null, $category->project) || in_array($user->email, $this->allowedEmails);
    }
}
