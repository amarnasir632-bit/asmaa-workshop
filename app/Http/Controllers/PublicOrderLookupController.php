<?php

namespace App\Http\Controllers;

use App\Models\Child;
use App\Models\Guardian;
use App\Models\Order;
use App\Services\OrderFinancialSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class PublicOrderLookupController extends Controller
{
    public function index(): View
    {
        return view('public.lookup');
    }

    public function search(Request $request, OrderFinancialSummary $summary): View
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);
        $key = 'public-order-lookup:'.hash('sha256', (string) $request->ip());
        if (RateLimiter::tooManyAttempts($key, 30)) {
            return view('public.lookup', ['message' => 'تعذر إتمام الاستعلام الآن. حاول لاحقاً.']);
        }
        RateLimiter::hit($key, 86400);

        $name = trim($data['name']);
        $phone = isset($data['phone']) ? trim($data['phone']) : null;
        $guardians = Guardian::query()->where('name', $name)->get(['id', 'phone']);
        $children = Child::query()->with('guardian:id,phone')->where('name', $name)->get(['id', 'guardian_id']);
        $matches = $guardians->count() + $children->count();

        if (! $phone && $matches > 1) {
            return view('public.lookup', ['needsPhone' => true]);
        }

        $guardianIds = collect();
        $childIds = collect();
        if ($phone) {
            $guardianIds = $guardians->where('phone', $phone)->pluck('id');
            $matchedChildren = $children->filter(fn (Child $child): bool => $child->guardian?->phone === $phone);
            $childIds = $matchedChildren->pluck('id');
            $guardianIds = $guardianIds->merge($matchedChildren->pluck('guardian_id'))->unique()->values();
        } elseif ($guardians->count() === 1 && $children->isEmpty()) {
            $guardianIds = $guardians->pluck('id');
        } elseif ($children->count() === 1 && $guardians->isEmpty()) {
            $childIds = $children->pluck('id');
        }

        if ($guardianIds->isEmpty() && $childIds->isEmpty()) {
            return view('public.lookup', ['message' => 'تعذر تحديد طلب بهذه البيانات. تواصل مع المشغل.']);
        }

        $orders = Order::query()->where(function ($query) use ($guardianIds, $childIds): void {
            if ($guardianIds->isNotEmpty()) {
                $query->whereIn('guardian_id', $guardianIds);
            }
            if ($childIds->isNotEmpty()) {
                $method = $guardianIds->isNotEmpty() ? 'orWhereHas' : 'whereHas';
                $query->{$method}('items', fn ($items) => $items->whereIn('child_id', $childIds));
            }
        })->get();

        if ($orders->isEmpty()) {
            return view('public.lookup', ['message' => 'تعذر تحديد طلب بهذه البيانات. تواصل مع المشغل.']);
        }

        $ready = $orders->every(fn (Order $order): bool => in_array($order->operational_status, ['ready', 'delivered'], true));
        $paid = $orders->every(fn (Order $order): bool => $summary->for($order)['paid']);

        return view('public.lookup', ['result' => ['ready' => $ready, 'paid' => $paid]]);
    }
}
