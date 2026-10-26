<?php

namespace Tests\Feature;

use App\Models\PendingRegistration;
use App\Models\User;
use App\Support\Lot;
use App\Support\Rules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Contact numbers: digits and a leading + only. 09 means 11 digits, +63 means 13 characters. */
class PhoneNumberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function form(string $phone): array
    {
        return [
            'name' => 'Pedro Cruz', 'email' => 'pedro.cruz@gmail.com', 'phone' => $phone, 'plate_number' => 'KLM 4455',
            'vehicle_type' => 'Car', 'make_model' => 'Toyota Vios', 'color' => 'Black',
            'password' => 'Driver123x!', 'password_confirmation' => 'Driver123x!',
        ];
    }

    public function test_good_numbers_pass(): void
    {
        foreach (['09171234567', '+639171234567', '09999999999'] as $phone) {
            $this->assertNull(Rules::mobileProblem(Lot::normalizePhone($phone)), $phone);
        }
    }

    public function test_a_plus_63_number_is_saved_in_the_09_form(): void
    {
        $this->assertSame('09171234567', Lot::normalizePhone('+639171234567'));
        $this->assertSame('09171234567', Lot::normalizePhone('  09171234567 '));
    }

    public function test_letters_spaces_dashes_and_other_symbols_are_refused(): void
    {
        foreach (['0917abc4567', '09171234567a', 'abcdefghijk', '0917 123 4567', '0917-123-4567', '(0917)1234567', '+63 917 123 4567', '09171234567.', '09+1712345', '++639171234567', '0917123456+'] as $phone) {
            $this->assertSame(
                'Use digits only, or a + at the start. No letters, spaces or dashes.',
                Rules::mobileProblem(Lot::normalizePhone($phone)),
                $phone,
            );
        }
    }

    public function test_a_number_starting_with_09_must_have_exactly_11_digits(): void
    {
        foreach (['0917123456', '091712345678', '09', '0917'] as $phone) {
            $this->assertStringContainsString('exactly 11 digits', (string) Rules::mobileProblem(Lot::normalizePhone($phone)), $phone);
        }
    }

    public function test_a_number_starting_with_plus_63_must_have_exactly_13_characters(): void
    {
        foreach (['+63917123456', '+6391712345678', '+63', '+638171234567'] as $phone) {
            $this->assertStringContainsString('exactly 13 characters', (string) Rules::mobileProblem(Lot::normalizePhone($phone)), $phone);
        }
    }

    public function test_other_starts_are_refused(): void
    {
        foreach (['9171234567', '639171234567', '+19171234567', '12345', '+0917123456'] as $phone) {
            $this->assertNotNull(Rules::mobileProblem(Lot::normalizePhone($phone)), $phone);
        }
    }

    public function test_the_sign_up_form_applies_the_rule(): void
    {
        Mail::fake();

        foreach (['0917abc4567', '0917 123 4567', '0917123456', '+63917123456', '639171234567'] as $bad) {
            $this->post(route('driver.register'), $this->form($bad))->assertSessionHasErrors('phone');
        }
        $this->assertSame(0, PendingRegistration::count());

        $this->post(route('driver.register'), $this->form('+639171234567'))->assertSessionHasNoErrors();
        $this->assertSame('09171234567', PendingRegistration::firstOrFail()->phone);
    }

    public function test_the_profile_form_applies_the_rule(): void
    {
        $driver = User::create([
            'name' => 'Maria Santos', 'email' => 'maria@example.com', 'phone' => '09175550142',
            'password' => 'Driver123_', 'role' => User::ROLE_DRIVER, 'status' => User::STATUS_ACTIVE,
        ]);

        foreach (['0917 555 0199', 'call-me', '+63917555019'] as $bad) {
            $this->actingAs($driver)->put(route('driver.account.profile.update'), ['name' => 'Maria Santos', 'email' => 'maria@example.com', 'phone' => $bad])
                ->assertSessionHasErrors('phone');
        }
    }

    public function test_the_phone_inputs_limit_the_length_to_13(): void
    {
        $this->get(route('driver.register'))->assertOk()->assertSee('maxlength="13"', false)->assertSee('09171234567', false);
    }
}
