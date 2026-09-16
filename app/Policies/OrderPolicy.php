<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /** Both roles work the order desk. */
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Order $order): bool
    {
        return $user->is_active;
    }

    /** Staff and admins can both approve, reject and fulfil orders. */
    public function review(User $user, Order $order): bool
    {
        return $user->is_active && ! $order->status->isTerminal();
    }

    public function resolveCancellation(User $user, Order $order): bool
    {
        return $user->is_active && $order->status === OrderStatus::CancellationRequested;
    }

    /** Cancelling reverses a settled sale, so it stays with admins. */
    public function cancel(User $user, Order $order): bool
    {
        return $user->is_active
            && $user->isAdmin()
            && $order->status->canTransitionTo(OrderStatus::Cancelled);
    }

    /** Deleting destroys the audit trail — admins only, as before. */
    public function delete(User $user, Order $order): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    /** Anyone who can work the desk can hand over a receipt. */
    public function printReceipt(User $user, Order $order): bool
    {
        return $user->is_active;
    }
}
