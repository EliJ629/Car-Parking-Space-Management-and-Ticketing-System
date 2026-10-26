<?php

namespace Tests\Feature;

use App\Models\ParkingSession;
use App\Support\TicketNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Tickets are PK-DDDD and are kept apart from plates. */
class TicketNumberTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, ?string}> */
    public static function inputs(): array
    {
        return [
            'padded' => ['PK-0003', 'PK-0003'], 'no dash' => ['PK0003', 'PK-0003'], 'short' => ['PK-3', 'PK-0003'], 'lower and short' => ['pk3', 'PK-0003'],
            'space' => ['PK 3', 'PK-0003'], 'two digits' => ['pk-03', 'PK-0003'], 'underscore' => ['pk_0003', 'PK-0003'], 'max' => ['PK-9999', 'PK-9999'],
            'zero' => ['PK-0', null], 'zero padded' => ['PK-0000', null], 'five digits' => ['PK-10000', null], 'letters' => ['PK-ABC', null], 'no number' => ['PK', null],
            'a plate' => ['ABC1234', null], 'bare number is not a ticket here' => ['3', null],
        ];
    }

    #[DataProvider('inputs')]
    public function test_a_typed_ticket_is_completed_to_pk_dddd(string $typed, ?string $expected): void
    {
        $this->assertSame($expected, TicketNumber::normalize($typed));
    }

    public function test_a_bare_number_is_a_ticket_only_where_the_screen_says_so(): void
    {
        $this->assertSame('PK-0017', TicketNumber::normalize('17', true));
        $this->assertNull(TicketNumber::normalize('17'));
        $this->assertFalse(TicketNumber::looksLikeTicket('17'), 'A bare 17 can be a protocol plate, so it is not treated as a ticket.');
        $this->assertTrue(TicketNumber::looksLikeTicket('pk 17'));
    }

    public function test_a_ticket_is_never_confused_with_a_plate(): void
    {
        $this->assertFalse(TicketNumber::isValid('ABC1234'));
        $this->assertNull(TicketNumber::normalize('ABC1234'));
    }

    public function test_a_plate_that_starts_with_pk_is_still_found_when_no_ticket_has_that_number(): void
    {
        // PK 1234 is a real plate shape (LL DDDD) and also looks like ticket PK-1234.
        ParkingSession::create(['ticket_number' => 'PK-0007', 'slot_code' => 'A-01', 'plate_number' => 'PK1234', 'entered_at' => now(), 'status' => 'active']);
        $gate = app(\App\Services\GateService::class);

        $this->assertSame('PK-0007', $gate->search('PK-0007')?->ticket_number, 'The ticket number wins when it exists.');
        $this->assertSame('PK-0007', $gate->search('pk 1234')?->ticket_number, 'No ticket PK-1234, so it is read as the plate.');
        $this->assertNull($gate->search('PK-0099'));
        $this->assertNull($gate->search('PK-0'));
    }

    public function test_the_next_number_continues_from_the_highest_and_old_tickets_still_open(): void
    {
        foreach (['PK-0001', 'PK-0005', 'OLD-77'] as $ticket) {
            ParkingSession::create(['ticket_number' => $ticket, 'slot_code' => 'A-01', 'plate_number' => 'ABC1234', 'entered_at' => now(), 'status' => 'completed', 'exited_at' => now()]);
        }

        $this->assertSame('PK-0006', TicketNumber::next());
        $this->assertContains('OLD-77', TicketNumber::lookupCandidates('old-77'), 'A stored ticket that does not fit the pattern still opens by its exact number.');
    }

    public function test_after_pk_9999_the_lowest_free_number_is_reused(): void
    {
        ParkingSession::create(['ticket_number' => 'PK-9999', 'slot_code' => 'A-01', 'plate_number' => 'ABC1234', 'entered_at' => now(), 'status' => 'completed', 'exited_at' => now()]);

        $this->assertSame('PK-0001', TicketNumber::next());
    }
}
