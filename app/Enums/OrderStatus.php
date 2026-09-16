<?php

namespace App\Enums;

/**
 * The order lifecycle, kept to the states this business actually works in.
 *
 * Online orders are paid up front by bank/e-wallet transfer with a screenshot
 * attached, so they arrive Pending and a staff member checks the proof:
 *
 *     pending ──approve──> approved ──fulfil──> completed
 *        └──reject──> rejected
 *
 * Stock leaves the shelf when an online order is approved (matching the legacy
 * rule) and is put back if that order is later rejected or cancelled.
 *
 * Register sales are tendered and handed over on the spot, so they are written
 * straight to `completed` with stock deducted in the same transaction. An
 * admin can still cancel one, which returns the stock.
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Processing = 'processing';
    case Dispatched = 'dispatched';
    case CancellationRequested = 'cancellation_requested';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Processing => 'Processing',
            self::Dispatched => 'Dispatched',
            self::CancellationRequested => 'Cancellation Requested',
            self::Completed => 'Completed',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
        };
    }

    /** Whether stock is considered committed to the customer in this state. */
    public function holdsStock(): bool
    {
        return in_array($this, [self::Approved, self::Processing, self::Dispatched, self::Completed], true);
    }

    /** Terminal states can no longer be moved along the workflow. */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Rejected, self::Cancelled], true);
    }

    /**
     * States this one may legally move to.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Approved, self::Processing, self::Rejected, self::CancellationRequested, self::Cancelled],
            self::Approved => [self::Processing, self::Completed, self::Pending, self::Rejected, self::CancellationRequested, self::Cancelled],
            self::Processing => [self::Dispatched, self::Completed, self::CancellationRequested, self::Cancelled],
            self::Dispatched => [self::Completed, self::Cancelled],
            self::CancellationRequested => [],
            self::Completed => [self::Cancelled],
            self::Rejected, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** CSS modifier used by the status badge component. */
    public function badgeClass(): string
    {
        return 'status-'.$this->value;
    }

    /** @return array<int, self> */
    public static function openStates(): array
    {
        return [self::Pending, self::Approved, self::Processing, self::Dispatched];
    }
}
