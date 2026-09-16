<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    /** Adding a design to the catalog is an admin decision. */
    public function create(User $user): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function update(User $user, Product $product): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    /**
     * Staff run the stock room, so they may correct counts, but only an admin
     * changes what a thing costs. This mirrors the legacy rule, minus its
     * quirk of letting staff only ever count stock down.
     */
    public function adjustStock(User $user): bool
    {
        return $user->is_active;
    }

    public function changePricing(User $user): bool
    {
        return $user->is_active && $user->isAdmin();
    }
}
