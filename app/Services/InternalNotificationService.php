<?php

namespace App\Services;

use App\Models\InternalNotification;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Collection;

final class InternalNotificationService
{
    public function refreshFor(User $user, OrderFinancialSummary $summary): Collection
    {
        $targets = collect();
        $today = now(config('app.display_timezone', 'Africa/Khartoum'))->toDateString();
        $orders = Order::query()->whereNull('deleted_at')->whereNotIn('operational_status', ['delivered', 'cancelled'])->get();

        foreach ($orders as $order) {
            if ($order->due_on && $order->due_on->lt($today)) {
                $targets->push($this->target($order, 'order_overdue', 'طلب متأخر', 'تجاوز الطلب '.$order->number.' موعد الإنجاز المتوقع.'));
            }
            if ($order->operational_status === 'ready') {
                $targets->push($this->target($order, 'order_ready', 'طلب جاهز', 'الطلب '.$order->number.' جاهز للتسليم.'));
            }
            if ($user->can('payments.record') && $summary->for($order)['due'] > 0) {
                $targets->push($this->target($order, 'order_balance_due', 'رصيد مستحق', 'يوجد رصيد مستحق على الطلب '.$order->number.'.'));
            }
        }

        $activeKeys = [];
        foreach ($targets as $target) {
            $activeKeys[] = $target['type'].'|'.Order::class.'|'.$target['order']->id;
            $notification = InternalNotification::query()->firstOrNew([
                'user_id' => $user->id, 'type' => $target['type'], 'subject_type' => Order::class, 'subject_id' => $target['order']->id,
            ]);
            if (! $notification->exists || $notification->resolved_at) {
                $notification->read_at = null;
            }
            $notification->title = $target['title'];
            $notification->body = $target['body'];
            $notification->resolved_at = null;
            $notification->save();
        }

        foreach (InternalNotification::query()->where('user_id', $user->id)->whereIn('type', ['order_overdue', 'order_ready', 'order_balance_due'])->whereNull('resolved_at')->get() as $notification) {
            $key = $notification->type.'|'.$notification->subject_type.'|'.$notification->subject_id;
            if (! in_array($key, $activeKeys, true)) {
                $notification->update(['resolved_at' => now()]);
            }
        }

        return InternalNotification::query()->where('user_id', $user->id)->whereNull('resolved_at')
            ->orderByRaw('read_at IS NOT NULL')->latest('updated_at')->limit(30)->get();
    }

    private function target(Order $order, string $type, string $title, string $body): array
    {
        return compact('order', 'type', 'title', 'body');
    }
}
