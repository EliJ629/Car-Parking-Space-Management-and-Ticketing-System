<?php

namespace Tests\Feature;

use App\Mail\VerifyEmailMail;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\EmailVerification;
use App\Support\VehicleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Driver sign up: Gmail only, a code and a link by email before the first sign in, no duplicates, model list by vehicle type. */
class SignUpVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    /** @return array<string, mixed> */
    private function form(array $override = []): array
    {
        return $override + [
            'name' => 'Pedro Cruz', 'email' => 'pedro.cruz@gmail.com', 'phone' => '09171234567', 'plate_number' => 'KLM 4455',
            'vehicle_type' => 'Car', 'make_model' => 'Toyota Vios', 'color' => 'Black',
            'password' => 'Driver123x!', 'password_confirmation' => 'Driver123x!',
        ];
    }

    private function codeSentTo(string $email): VerifyEmailMail
    {
        $found = null;
        Mail::assertSent(VerifyEmailMail::class, function (VerifyEmailMail $mail) use ($email, &$found) {
            if ($mail->hasTo($email)) {
                $found = $mail;

                return true;
            }

            return false;
        });

        return $found;
    }

    public function test_sign_up_emails_a_code_and_a_link_and_the_driver_cannot_sign_in_until_it_is_used(): void
    {
        Mail::fake();

        $this->post(route('driver.register'), $this->form())->assertRedirect(route('driver.verify'));
        $mail = $this->codeSentTo('pedro.cruz@gmail.com');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $mail->code);
        $this->assertStringContainsString('/verify-email/', $mail->link);
        $this->assertStringContainsString('signature=', $mail->link);

        $user = User::where('email', 'pedro.cruz@gmail.com')->firstOrFail();
        $this->assertFalse($user->hasVerifiedEmail());

        // Signing in before the code is used sends the driver back to the code step.
        auth()->logout();
        $this->flushSession();
        $this->post(route('driver.login'), ['identity' => 'pedro.cruz@gmail.com', 'password' => 'Driver123x!'])->assertRedirect(route('driver.verify'));
        $this->assertGuest();

        // A wrong code is counted and refused.
        $wrong = $mail->code === '123456' ? '654321' : '123456';
        $this->post(route('driver.verify.check'), ['code' => $wrong])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->assertSame(1, $user->fresh()->verification_attempts);

        $this->post(route('driver.verify.check'), ['code' => $mail->code])->assertRedirect(route('driver.home'));
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertNull($user->fresh()->verification_code_hash);

        // After that the normal sign in works.
        auth()->logout();
        $this->post(route('driver.login'), ['identity' => 'pedro.cruz@gmail.com', 'password' => 'Driver123x!'])->assertRedirect(route('driver.home'));
    }

    public function test_the_link_in_the_email_confirms_the_address_and_cannot_be_edited(): void
    {
        Mail::fake();
        $this->post(route('driver.register'), $this->form())->assertRedirect(route('driver.verify'));
        $mail = $this->codeSentTo('pedro.cruz@gmail.com');
        $user = User::where('email', 'pedro.cruz@gmail.com')->firstOrFail();

        $this->get($mail->link.'x')->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());

        $this->get($mail->link)->assertRedirect(route('driver.login'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_a_code_expires_and_locks_after_too_many_wrong_tries(): void
    {
        Mail::fake();
        $this->post(route('driver.register'), $this->form())->assertRedirect(route('driver.verify'));
        $mail = $this->codeSentTo('pedro.cruz@gmail.com');
        $wrong = $mail->code === '123456' ? '654321' : '123456';

        foreach (range(1, EmailVerification::MAX_ATTEMPTS) as $i) {
            $this->post(route('driver.verify.check'), ['code' => $wrong])->assertSessionHasErrors('code');
        }

        // Even the right code is refused once locked.
        $this->post(route('driver.verify.check'), ['code' => $mail->code])->assertSessionHasErrors('code');
        $this->assertGuest();

        // Resend is held back for a minute.
        $this->post(route('driver.verify.resend'))->assertSessionHasErrors('code');

        $this->travel(2)->minutes();
        $this->post(route('driver.verify.resend'))->assertSessionHasNoErrors();
        $fresh = $this->codeSentTo('pedro.cruz@gmail.com');
        $this->post(route('driver.verify.check'), ['code' => $fresh->code])->assertRedirect(route('driver.home'));

        $this->travelBack();
    }

    public function test_only_real_looking_gmail_addresses_are_accepted(): void
    {
        Mail::fake();

        foreach (['pedro.cruz@yahoo.com', 'pedro.cruz@example.com', 'pedro.cruz@gmail.co', 'pedro@gmail.com', 'pedro cruz@gmail.com', '.pedro.cruz@gmail.com', 'pedro..cruz@gmail.com'] as $email) {
            $this->post(route('driver.register'), $this->form(['email' => $email]))->assertSessionHasErrors('email');
        }

        $this->assertSame(0, User::where('role', 'driver')->count());
        Mail::assertNothingSent();
    }

    public function test_a_gmail_account_name_or_plate_that_is_already_registered_is_refused(): void
    {
        Mail::fake();
        $this->post(route('driver.register'), $this->form())->assertRedirect(route('driver.verify'));
        $this->flushSession();

        // Gmail ignores dots and capital letters, so these are the same mailbox.
        $this->post(route('driver.register'), $this->form(['email' => 'Pedro.Cruz@gmail.com', 'name' => 'Other One', 'plate_number' => 'ABC 1234']))->assertSessionHasErrors('email');
        $this->post(route('driver.register'), $this->form(['email' => 'pedrocruz@gmail.com', 'name' => 'Other One', 'plate_number' => 'ABC 1234']))->assertSessionHasErrors('email');
        $this->post(route('driver.register'), $this->form(['email' => 'someone.else@gmail.com', 'name' => '  pedro   CRUZ ', 'plate_number' => 'ABC 1234']))->assertSessionHasErrors('name');
        $this->post(route('driver.register'), $this->form(['email' => 'someone.else@gmail.com', 'name' => 'Other One', 'plate_number' => 'klm-4455']))->assertSessionHasErrors('plate_number');

        $this->assertSame(1, User::where('role', 'driver')->count());
        $this->post(route('driver.register'), $this->form(['email' => 'someone.else@gmail.com', 'name' => 'Other One', 'plate_number' => 'ABC 1234']))->assertSessionHasNoErrors();
        $this->assertSame(2, User::where('role', 'driver')->count());
    }

    public function test_an_address_the_mail_service_refuses_leaves_nothing_behind(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('Sender or recipient not allowed'));

        $this->post(route('driver.register'), $this->form())->assertSessionHasErrors('email');

        $this->assertSame(0, User::where('role', 'driver')->count());
        $this->assertSame(0, Vehicle::withTrashed()->count());
    }

    public function test_the_vehicle_model_follows_the_vehicle_type_and_others_opens_a_text_box(): void
    {
        Mail::fake();
        $this->get(route('driver.register'))->assertOk()->assertSee('Vehicle model')->assertDontSee('Truck')->assertDontSee('Car model');

        // A Hilux is a pickup, not a car; Truck is not a type any more.
        $this->post(route('driver.register'), $this->form(['make_model' => 'Toyota Hilux']))->assertSessionHasErrors('make_model');
        $this->post(route('driver.register'), $this->form(['vehicle_type' => 'Truck', 'make_model' => 'Isuzu Giga']))->assertSessionHasErrors('vehicle_type');

        // Others needs the text.
        $this->post(route('driver.register'), $this->form(['make_model' => 'Others']))->assertSessionHasErrors('make_model_other');
        $this->post(route('driver.register'), $this->form(['color' => 'Others']))->assertSessionHasErrors('color_other');
        $this->assertSame(0, User::where('role', 'driver')->count());

        $this->post(route('driver.register'), $this->form(['make_model' => 'Others', 'make_model_other' => 'BYD Dolphin', 'color' => 'Others', 'color_other' => 'maroon']))
            ->assertSessionHasNoErrors();
        $vehicle = Vehicle::firstOrFail();
        $this->assertSame('BYD Dolphin', $vehicle->make_model);
        $this->assertSame('Maroon', $vehicle->color);

        foreach (VehicleCatalog::forForm() as $type => $models) {
            $this->assertNotEmpty($models, "{$type} has models");
            $this->assertSame('Others', end($models));
        }
    }

    public function test_the_password_needs_ten_characters_a_number_a_capital_and_a_special_character(): void
    {
        Mail::fake();

        foreach (['Short1!A', 'alllowercase1!', 'NoNumberHere!', 'NoSpecial1234'] as $weak) {
            $this->post(route('driver.register'), $this->form(['password' => $weak, 'password_confirmation' => $weak]))->assertSessionHasErrors('password');
        }

        $this->post(route('driver.register'), $this->form())->assertSessionHasNoErrors();
    }
}
