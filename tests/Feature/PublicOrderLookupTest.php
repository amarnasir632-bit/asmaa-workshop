<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PublicOrderLookupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutVite();
    }

    public function test_duplicate_name_requests_phone_then_only_shows_two_permitted_statuses(): void
    {
        $creator = $this->employee();
        $first = Guardian::query()->create(['name' => 'اسم متكرر', 'phone' => '000000011']);
        $second = Guardian::query()->create(['name' => 'اسم متكرر', 'phone' => '000000012']);
        $child = $first->children()->create(['name' => 'طفل سري للاختبار']);
        $order = Order::query()->create(['number' => 'PUBLIC-TEST', 'type' => 'custom', 'guardian_id' => $first->id, 'customer_name_snapshot' => $first->name, 'created_by' => $creator->id, 'created_on' => now()->toDateString(), 'operational_status' => 'ready', 'items_total' => '10.00', 'discount_total' => '0.00', 'approved_total' => '10.00']);
        $order->items()->create(['child_id' => $child->id, 'description_snapshot' => 'بند خاص', 'quantity' => 1, 'unit_price' => '10.00', 'discount_amount' => '0.00', 'line_total' => '10.00']);
        $order->payments()->create(['amount' => '10.00', 'paid_at' => now(), 'method' => 'cash', 'verification_status' => 'not_required', 'recorded_by' => $creator->id]);

        $this->post(route('public.lookup.search'), ['name' => 'اسم متكرر'])->assertOk()->assertSee('رقم التواصل المسجل')->assertDontSee('طفل سري للاختبار');
        $response = $this->post(route('public.lookup.search'), ['name' => 'اسم متكرر', 'phone' => '000000011'])->assertOk()->assertSee('جاهز')->assertSee('مسدد');
        $response->assertDontSee('طفل سري للاختبار')->assertDontSee('000000011')->assertDontSee('10.00')->assertDontSee('PUBLIC-TEST')->assertDontSee('بند خاص');
    }

    public function test_rate_limit_and_unmatched_results_do_not_disclose_customer_existence(): void
    {
        $this->post(route('public.lookup.search'), ['name' => 'اسم لا يوجد'])->assertOk()->assertSee('تعذر تحديد طلب');
        $key = 'public-order-lookup:'.hash('sha256', '127.0.0.1');
        for ($attempt = 0; $attempt < 30; $attempt++) {
            RateLimiter::hit($key, 86400);
        }
        $this->post(route('public.lookup.search'), ['name' => 'اسم لا يوجد'])->assertOk()->assertSee('تعذر إتمام الاستعلام');
    }

    private function employee(): User
    {
        $user = User::factory()->create(['username' => 'public.lookup.creator']);
        $user->roles()->attach(Role::query()->where('name', 'employee')->value('id'));

        return $user;
    }
}
