<?php

namespace App\Services;

use App\Enums\OrderChannel;
use App\Models\HeldSale;
use App\Models\Order;
use Illuminate\Support\Carbon;

/**
 * Document numbering.
 *
 * Every reference a customer or a cashier is ever asked to read out loud is
 * allocated here, in one format:
 *
 *     VD-POS-260824-0007      a counter sale
 *     VD-WEB-260824-0003      an online order
 *     HOLD-260824-0002        a parked basket
 *
 * The parts are, in order: the store's mark, the channel the sale came
 * through, the trading date, and a counter that restarts each day. That makes
 * a reference short enough to say over a phone, sortable, obviously ours, and
 * enough on its own to find the day's paperwork — which a random string such
 * as the old `ORDa1b2c3d4e5f6g` never was.
 *
 * Allocation runs inside the caller's transaction. The last reference for the
 * day is read with the row locked, so two registers cannot read the same
 * number, and the counter is then walked forward past anything that already
 * exists before it is handed back.
 */
class ReferenceService
{
    /** How many times to walk the counter forward before giving up. */
    private const MAX_PROBES = 1000;

    /** The reference for a new order on the given channel. */
    public function orderReference(OrderChannel $channel, ?Carbon $on = null): string
    {
        $stem = sprintf(
            '%s-%s-%s-',
            config('void.reference.prefix', 'VD'),
            $this->channelCode($channel),
            $this->datePart($on),
        );

        return $this->allocate(
            $stem,
            fn (string $stem) => Order::query()
                ->where('order_ref', 'like', $stem.'%')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->value('order_ref'),
            fn (string $candidate) => Order::where('order_ref', $candidate)->exists(),
        );
    }

    /** The reference for a basket parked at the register. */
    public function heldSaleReference(?Carbon $on = null): string
    {
        $stem = sprintf(
            '%s-%s-',
            config('void.reference.held_prefix', 'HOLD'),
            $this->datePart($on),
        );

        return $this->allocate(
            $stem,
            fn (string $stem) => HeldSale::query()
                ->where('reference', 'like', $stem.'%')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->value('reference'),
            fn (string $candidate) => HeldSale::where('reference', $candidate)->exists(),
        );
    }

    /**
     * Walk the day's counter forward from whatever was last issued.
     *
     * @param  \Closure(string): ?string  $last     the last reference on this stem
     * @param  \Closure(string): bool     $taken    whether a candidate is already in use
     */
    private function allocate(string $stem, \Closure $last, \Closure $taken): string
    {
        $padding = (int) config('void.reference.padding', 4);
        $sequence = $this->sequenceAfter($last($stem)) ;

        for ($probe = 0; $probe < self::MAX_PROBES; $probe++) {
            $candidate = $stem.str_pad((string) $sequence, $padding, '0', STR_PAD_LEFT);

            if (! $taken($candidate)) {
                return $candidate;
            }

            $sequence++;
        }

        // A day that somehow exhausted a thousand probes still has to trade.
        // Fall back to a reference that cannot collide rather than refusing
        // the sale, keeping the same readable shape.
        return $stem.strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    }

    /** The number after the trailing counter of a reference, or 1. */
    private function sequenceAfter(?string $reference): int
    {
        if ($reference !== null && preg_match('/(\d+)$/', $reference, $matches) === 1) {
            return ((int) $matches[1]) + 1;
        }

        return 1;
    }

    private function datePart(?Carbon $on): string
    {
        return ($on ?? Carbon::now())->format('ymd');
    }

    private function channelCode(OrderChannel $channel): string
    {
        return (string) config(
            'void.reference.channels.'.$channel->value,
            strtoupper(substr($channel->value, 0, 3))
        );
    }
}
