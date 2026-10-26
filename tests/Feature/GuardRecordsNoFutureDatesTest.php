<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Lot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The guard's Records filter: "Pick dates" never goes past today. */
class GuardRecordsNoFutureDatesTest extends TestCase
{
    use RefreshDatabase;

    private function guard(): User
    {
        return User::factory()->create(['role' => User::ROLE_GUARD, 'status' => User::STATUS_ACTIVE, 'username' => 'guard'.random_int(100, 999)]);
    }

    public function test_both_date_boxes_stop_at_today(): void
    {
        $today = Lot::now()->format('Y-m-d');
        foreach (['parking', 'incidents'] as $tab) {
            $html = $this->actingAs($this->guard())->get(route('guard.records', ['tab' => $tab, 'range' => 'custom']))->assertOk()->getContent();
            $this->assertStringContainsString('id="from" name="from" type="date" data-range-start max="'.$today.'"', $html);
            $this->assertStringContainsString('id="to" name="to" type="date" data-range-end max="'.$today.'"', $html);
        }
    }

    public function test_dates_in_the_future_are_pulled_back_to_today(): void
    {
        $today = Lot::now()->format('Y-m-d');
        $future = Lot::now()->addDays(20)->format('Y-m-d');

        $html = $this->actingAs($this->guard())->get(route('guard.records', ['range' => 'custom', 'from' => '2026-01-01', 'to' => $future]))->assertOk()->getContent();
        $this->assertStringContainsString('name="to" type="date" data-range-end max="'.$today.'" value="'.$today.'"', $html);

        $html = $this->actingAs($this->guard())->get(route('guard.records', ['range' => 'custom', 'from' => $future, 'to' => $future]))->assertOk()->getContent();
        $this->assertStringContainsString('name="from" type="date" data-range-start max="'.$today.'" value="'.$today.'"', $html);
        $this->assertStringContainsString('name="to" type="date" data-range-end max="'.$today.'" value="'.$today.'"', $html);
    }

    public function test_a_past_range_is_left_alone(): void
    {
        $html = $this->actingAs($this->guard())->get(route('guard.records', ['range' => 'custom', 'from' => '2026-01-01', 'to' => '2026-01-31']))->assertOk()->getContent();
        $this->assertStringContainsString('value="2026-01-01"', $html);
        $this->assertStringContainsString('value="2026-01-31"', $html);
    }
}
