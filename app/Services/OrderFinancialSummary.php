<?php

namespace App\Services;

use App\Models\Order;
use App\Support\Money;

final class OrderFinancialSummary
{
    /** @return array{recorded:int, confirmed:int, refunded:int, net_paid:int, due:int, paid:bool} */
    public function for(Order $order): array
    {
        $payments = $order->payments()->get();
        $recorded = $payments->sum(fn ($payment): int => Money::toMinorUnits((string) $payment->amount));
        $confirmed = $payments->filter(fn ($payment): bool => $payment->method === 'cash' || $payment->verification_status === 'verified')
            ->sum(fn ($payment): int => Money::toMinorUnits((string) $payment->amount));
        $refunded = $order->refunds()->whereNotNull('refunded_at')
            ->get()->sum(fn ($refund): int => Money::toMinorUnits((string) $refund->refunded_amount));
        $hasPendingTransfer = $payments->contains(fn ($payment): bool => $payment->method === 'bank_transfer' && $payment->verification_status === 'pending');
        $net = max(0, $confirmed - $refunded);
        $cancellationDiscount = Money::toMinorUnits((string) ($order->cancellations()->latest('id')->value('cancellation_discount') ?? '0.00'));
        $effectiveTotal = max(0, Money::toMinorUnits((string) $order->approved_total) - $cancellationDiscount);
        $due = $order->operational_status === 'cancelled' ? 0 : max(0, $effectiveTotal - $net);

        $status = match (true) {
            $refunded > 0 && $net === 0 => 'refunded_full',
            $refunded > 0 => 'refunded_partial',
            $due === 0 && $order->operational_status !== 'cancelled' => 'paid',
            $net > 0 => 'partially_paid',
            $hasPendingTransfer => 'transfer_pending',
            default => 'unpaid',
        };

        return ['recorded' => $recorded, 'confirmed' => $confirmed, 'refunded' => $refunded, 'net_paid' => $net, 'due' => $due, 'paid' => $due === 0 && $order->operational_status !== 'cancelled', 'has_pending_transfer' => $hasPendingTransfer, 'status' => $status];
    }
}
