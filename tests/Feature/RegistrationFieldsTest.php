<?php

namespace Tests\Feature;

use App\Mail\ConfirmRegistrationMail;
use App\Models\PendingRegistration;
use App\Support\Rules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistrationFieldsTest extends TestCase
{
    use RefreshDatabase;

    private function form(array $over = []): array
    {
        return $over + [
            'first_name' => 'Maria', 'middle_name' => '', 'last_name' => 'Santos', 'email' => 'maria.santos@gmail.com', 'phone' => '09171234567',
            'plate_number' => '4455 KL', 'vehicle_type' => 'Tricycle', 'make_model' => 'TVS King', 'color' => 'Black',
            'password' => 'Driver123x!', 'password_confirmation' => 'Driver123x!', 'details_confirmed' => '1',
        ];
    }

    public function test_the_form_has_three_name_boxes_with_the_middle_one_optional(): void
    {
        $html = $this->get(route('driver.register'))->assertOk()->assertSee('First name')->assertSee('Middle name')->assertSee('(optional)')->assertSee('Last name')->getContent();

        foreach (['first_name', 'middle_name', 'last_name'] as $field) {
            $this->assertStringContainsString('name="'.$field.'"', $html);
        }
        $this->assertStringNotContainsString('name="name"', $html);
        $this->assertStringContainsString('data-mobile', $html);
        $this->assertStringContainsString('js/inputs.js', $html);
    }

    public function test_the_three_parts_are_joined_into_one_name_and_the_middle_name_can_be_left_out(): void
    {
        Mail::fake();
        $this->withoutMiddleware(ThrottleRequests::class);

        $this->post(route('driver.register'), $this->form(['middle_name' => ' Dela   Cruz ']))->assertSessionHasNoErrors();
        $this->assertSame('Maria Dela Cruz Santos', PendingRegistration::firstOrFail()->name);
        PendingRegistration::query()->delete();

        $this->post(route('driver.register'), $this->form())->assertSessionHasNoErrors();
        $this->assertSame('Maria Santos', PendingRegistration::firstOrFail()->name);
        Mail::assertSent(ConfirmRegistrationMail::class, 2);
    }

    public function test_every_name_box_takes_letters_only(): void
    {
        Mail::fake();
        $this->withoutMiddleware(ThrottleRequests::class);

        foreach ([
            ['first_name', 'Maria1'], ['first_name', 'M@ria'], ['first_name', "Ma'ria"], ['first_name', 'Ma-ria'], ['first_name', 'Maria.'], ['first_name', ''],
            ['middle_name', 'Cruz2'], ['middle_name', 'Cruz_'], ['last_name', 'Santos 3'], ['last_name', 'S'], ['last_name', ''], ['last_name', '<b>x</b>'],
        ] as [$field, $value]) {
            $this->post(route('driver.register'), $this->form([$field => $value]))->assertSessionHasErrors($field);
        }
        $this->assertSame(0, PendingRegistration::count());

        // Accents and a space inside a name are letters.
        $this->post(route('driver.register'), $this->form(['first_name' => 'María José', 'last_name' => 'Peña']))->assertSessionHasNoErrors();
    }

    public function test_a_phone_number_with_63_in_front_is_the_same_number_as_09(): void
    {
        Mail::fake();
        $this->withoutMiddleware(ThrottleRequests::class);

        $this->post(route('driver.register'), $this->form(['phone' => '639171234567']))->assertSessionHasNoErrors();
        $this->assertSame('09171234567', PendingRegistration::firstOrFail()->phone);
        $this->assertNotNull(Rules::mobileProblem('63917123'));
    }

    public function test_a_saved_name_opens_again_in_three_boxes(): void
    {
        $this->assertSame(['first_name' => 'Maria', 'middle_name' => 'Dela', 'last_name' => 'Santos'], Rules::splitName('Maria Dela Santos'));
        $this->assertSame(['first_name' => 'Maria', 'middle_name' => '', 'last_name' => 'Santos'], Rules::splitName(' Maria  Santos '));
        $this->assertSame(['first_name' => 'Maria', 'middle_name' => '', 'last_name' => ''], Rules::splitName('Maria'));
    }
}
