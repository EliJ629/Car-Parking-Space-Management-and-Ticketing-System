<?php

namespace Tests\Unit;

use App\Support\PlateNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** The one plate service: every supported format, normalisation, display, masking. */
class PlateNumberTest extends TestCase
{
    /** @return array<string, array{string, string, string}> input, stored key, shown */
    public static function validPlates(): array
    {
        return [
            // Standard four-wheel: LLL DDDD
            'standard four-wheel with a space' => ['ASD 1234', 'ASD1234', 'ASD 1234'],
            'standard four-wheel together' => ['ASD1234', 'ASD1234', 'ASD 1234'],
            'lower case' => ['asd1234', 'ASD1234', 'ASD 1234'],
            'dash' => ['asd-1234', 'ASD1234', 'ASD 1234'],
            'dot and spaces' => ['  asd . 1234 ', 'ASD1234', 'ASD 1234'],
            // Standard motorcycle: L DDD LL and DDD LLL
            'standard motorcycle L DDD LL' => ['a 123 bc', 'A123BC', 'A 123 BC'],
            'standard motorcycle L DDD LL together' => ['A123BC', 'A123BC', 'A 123 BC'],
            'standard motorcycle DDD LLL' => ['123 abc', '123ABC', '123 ABC'],
            // Legacy four-wheel: LLL DDD
            'legacy four-wheel' => ['ABC 123', 'ABC123', 'ABC 123'],
            'vintage TX series' => ['TXA 123', 'TXA123', 'TXA 123'],
            // Legacy motorcycle or tricycle: LL DDDD and DDDD LL
            'legacy motorcycle LL DDDD' => ['ab1234', 'AB1234', 'AB 1234'],
            'legacy motorcycle DDDD LL' => ['1234 AB', '1234AB', '1234 AB'],
            'legacy motorcycle DDDD-LL' => ['1234-ab', '1234AB', '1234 AB'],
            // Pre-1981: LL DDD, L DDDDD, DD-DDDD
            'pre-1981 LL DDD' => ['ab 123', 'AB123', 'AB 123'],
            'pre-1981 L DDDDD' => ['a 12345', 'A12345', 'A 12345'],
            'pre-1981 DD-DDDD' => ['12-3456', '12-3456', '12-3456'],
            // Vintage: L DDDD LL
            'vintage L DDDD LL' => ['a 1234 bc', 'A1234BC', 'A 1234 BC'],
            // Commercial trailer: LL DDDD, second letter U
            'trailer' => ['PU 1234', 'PU1234', 'PU 1234'],
            // Electric and hybrid
            'electric four-wheel' => ['ABV 1234', 'ABV1234', 'ABV 1234'],
            'electric motorcycle' => ['A 123 VB', 'A123VB', 'A 123 VB'],
            // Vanity: LLL DD, LLL DDD, LLL DDDD
            'vanity LLL DD' => ['ZEN 88', 'ZEN88', 'ZEN 88'],
            'vanity LLL DDD' => ['zen 888', 'ZEN888', 'ZEN 888'],
            'vanity LLL DDDD' => ['zen 8888', 'ZEN8888', 'ZEN 8888'],
            // Government protocol: 1 to 17
            'protocol 1' => ['1', '1', '1'],
            'protocol 9' => ['9', '9', '9'],
            'protocol 10' => ['10', '10', '10'],
            'protocol 17' => ['17', '17', '17'],
            // Foreign and diplomatic: DDD DDDD, DDD DDD
            'diplomatic DDD DDDD' => ['123 4567', '1234567', '123 4567'],
            'diplomatic DDD DDDD together' => ['1234567', '1234567', '123 4567'],
            'diplomatic DDD DDD' => ['123 456', '123456', '123 456'],
            // Temporary transit: cars L DDDD and LL DDDD, motorcycles DDDD-DDDDDDDDDDD
            'temporary car L DDDD' => ['t 1234', 'T1234', 'T 1234'],
            'temporary car LL DDDD' => ['tp 1234', 'TP1234', 'TP 1234'],
            'temporary motorcycle' => ['1234-12345678901', '1234-12345678901', '1234-12345678901'],
            'temporary motorcycle with spaces around the dash' => ['1234 - 12345678901', '1234-12345678901', '1234-12345678901'],
        ];
    }

    #[DataProvider('validPlates')]
    public function test_supported_plates_are_valid_stored_compact_and_shown_spaced(string $input, string $key, string $shown): void
    {
        $this->assertTrue(PlateNumber::isValid($input), $input);
        $this->assertSame($key, PlateNumber::normalize($input));
        $this->assertSame($shown, PlateNumber::format($input));
        $this->assertNull(PlateNumber::problem($input));
        // A stored key is a fixed point: normalising it again changes nothing.
        $this->assertSame($key, PlateNumber::normalize($key));
        $this->assertTrue(PlateNumber::isValid($key));
    }

    /** @return array<string, array{string}> */
    public static function invalidPlates(): array
    {
        return [
            'empty' => [''], 'spaces only' => ['   '], 'one letter one digit' => ['A1'], 'one letter two digits' => ['A12'],
            'too many letters' => ['ABCD 1234'], 'LLL with five digits' => ['ABC 12345'], 'LL with five digits is no longer a format' => ['AB 12345'],
            'LLL with one digit' => ['ABC 1'], 'letters only' => ['ABCDEF'], 'four digits only' => ['1234'], 'five digits only' => ['12345'],
            'eight digits only' => ['12345678'], 'ten digits only' => ['1234567890'],
            'protocol 0' => ['0'], 'protocol 00' => ['00'], 'protocol 18' => ['18'], 'protocol 99' => ['99'], 'protocol 01' => ['01'],
            'PANGULO is not on the list' => ['PANGULO'],
            'separator inside letters' => ['AB C 1234'], 'separator inside digits' => ['ABC 12 34'], 'digits split three ways' => ['12 34 56'],
            'diplomatic with a dash' => ['123-4567'], 'pre-1981 with the wrong split' => ['123-456'], 'pre-1981 digits short' => ['12-345'],
            'temporary motorcycle too short' => ['1234-1234567890'], 'temporary motorcycle too long' => ['1234-123456789012'],
            'temporary motorcycle without the dash' => ['1234 12345678901'], 'temporary motorcycle wrong first block' => ['123-12345678901'],
            'symbols' => ['ABC#1234'], 'sql' => ["ABC1234'; DROP TABLE users;--"], 'script' => ['<script>1</script>'],
            'too long' => ['ABC 1234 ABC 1234 ABC'], 'unicode letters' => ['ÄBC 1234'],
        ];
    }

    #[DataProvider('invalidPlates')]
    public function test_unsupported_plates_are_rejected_with_a_message(string $input): void
    {
        $this->assertFalse(PlateNumber::isValid($input), $input);
        $this->assertNotNull(PlateNumber::problem($input));
    }

    public function test_the_same_vehicle_has_one_key_however_it_is_typed(): void
    {
        foreach (['ASD1234', 'asd 1234', 'ASD-1234', ' asd.1234 ', "ASD\t1234"] as $typed) {
            $this->assertSame('ASD1234', PlateNumber::normalize($typed), $typed);
        }

        foreach (['12-3456', '12 - 3456', ' 12-3456 '] as $typed) {
            $this->assertSame('12-3456', PlateNumber::normalize($typed), $typed);
        }

        foreach (['123 4567', '1234567', '123  4567'] as $typed) {
            $this->assertSame('1234567', PlateNumber::normalize($typed), $typed);
        }
    }

    public function test_the_longest_plate_fits_the_database_column_and_the_input(): void
    {
        $this->assertSame(16, strlen(PlateNumber::normalize('1234-12345678901')));
        $this->assertGreaterThanOrEqual(strlen('1234 - 12345678901'), PlateNumber::MAX_INPUT_LENGTH);
        $this->assertLessThanOrEqual(20, strlen(PlateNumber::normalize('1234-12345678901')));
    }

    public function test_the_laravel_rule_reports_the_specific_problem(): void
    {
        $fails = function (string $value): ?string {
            $message = null;
            foreach (PlateNumber::rules() as $rule) {
                if ($rule instanceof \Closure) {
                    $rule('plate', $value, function (string $m) use (&$message) { $message = $m; });
                }
            }

            return $message;
        };

        $this->assertNull($fails('ABC 1234'));
        $this->assertNull($fails('1234-12345678901'));
        $this->assertStringContainsString('letters and numbers only', strtolower((string) $fails('ABC#1234')));
        $this->assertStringContainsString('at most 20', (string) $fails(str_repeat('A', 21)));
        $this->assertSame(PlateNumber::MESSAGE, $fails('AB 12345'));
    }

    public function test_the_plate_type_is_named(): void
    {
        $this->assertSame('Four-wheel (standard, electric or hybrid)', PlateNumber::type('ABC1234'));
        $this->assertSame('Four-wheel (legacy or vintage)', PlateNumber::type('ABC123'));
        $this->assertSame('Vanity plate', PlateNumber::type('ZEN88'));
        $this->assertSame('Motorcycle (standard, electric or hybrid)', PlateNumber::type('A123BC'));
        $this->assertSame('Trailer, motorcycle or tricycle', PlateNumber::type('PU1234'));
        $this->assertSame('Pre-1981 plate', PlateNumber::type('12-3456'));
        $this->assertSame('Vintage', PlateNumber::type('A1234BC'));
        $this->assertSame('Foreign or diplomatic', PlateNumber::type('1234567'));
        $this->assertSame('Temporary transit (car)', PlateNumber::type('T1234'));
        $this->assertSame('Temporary transit (motorcycle)', PlateNumber::type('1234-12345678901'));
        $this->assertSame('Government protocol', PlateNumber::type('17'));
        $this->assertNull(PlateNumber::type('nope'));
    }

    public function test_masking_hides_the_middle_and_never_returns_the_full_plate(): void
    {
        $this->assertSame('LMN ••34', PlateNumber::mask('LMN 1234'));
        $this->assertStringNotContainsString('1234', PlateNumber::mask('LMN 1234'));
        $this->assertSame('', PlateNumber::mask(''));
        $this->assertStringNotContainsString('12345678', PlateNumber::mask('1234-12345678901'));
    }
}
