<?php

namespace App\Policies;

use App\Models\Mutation;
use App\Models\User;

class MutationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('movements.view');
    }

    public function view(User $user, Mutation $mutation): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('movements.create');
    }

    public function update(User $user, Mutation $mutation): bool
    {
        return false;
    }

    public function delete(User $user, Mutation $mutation): bool
    {
        return false;
    }

    public function process(User $user, Mutation $mutation): bool
    {
        return $this->create($user) && in_array($mutation->status, ['pending', 'approved']);
    }

    public function cancel(User $user, Mutation $mutation): bool
    {
        return $this->process($user, $mutation);
    }
}
