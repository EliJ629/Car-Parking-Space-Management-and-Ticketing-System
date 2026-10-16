<?php

namespace App\Console\Commands;

use App\Models\Incident;
use App\Models\IncidentNote;
use App\Models\IncidentPhoto;
use App\Models\Notice;
use App\Models\ParkingPayment;
use App\Models\ParkingReservation;
use App\Models\ParkingSession;
use App\Models\ParkingSlot;
use App\Models\User;
use App\Services\FeeCalculator;
use App\Services\LongParkingService;
use App\Services\NoticeService;
use App\Services\ReservationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Demo data for the driver app and the guard console while the booth workspace
 * is not built yet. Every row it makes is marked (ticket PK-9xxx, receipt RC-9xxx,
 * reservation RSV-DEMO01) so `--clear` removes exactly those rows and nothing else.
 */
class BaylineDemo extends Command
{
    protected $signature = 'bayline:demo
        {--driver=session : What the Driver Test account sees: session, pending, reservation or none}
        {--clear : Only remove the demo rows}';

    protected $description = 'Create (or remove) demo parking sessions, payments and a reservation';

    public function handle(FeeCalculator $fees, NoticeService $notices, ReservationService $reservations, LongParkingService $longParking): int
    {
        $this->clear();

        if ($this->option('clear')) {
            $this->info('Demo rows removed.');

            return self::SUCCESS;
        }

        $scenario = (string) $this->option('driver');

        if (! in_array($scenario, ['session', 'pending', 'reservation', 'none'], true)) {
            $this->error('--driver must be session, pending, reservation or none.');

            return self::FAILURE;
        }

        $attendant = User::query()->where('role', User::ROLE_ATTENDANT)->orderBy('id')->first();
        $driver = User::query()->where('email', 'driver@test.com')->first();

        if (! $attendant || ! ParkingSlot::query()->exists()) {
            $this->error('Run "php artisan db:seed" first, so the accounts and slots exist.');

            return self::FAILURE;
        }

        // Walk-ins, for the guard console.
        $this->session($fees, $attendant, null, 'PK-9018', 'BNM6628', 'White', 'Van', 'A-07', 40, 'paid', 3);
        $this->session($fees, $attendant, null, 'PK-9016', 'HJK4471', 'Silver', 'Car', 'A-02', 125, 'active');
        $this->session($fees, $attendant, null, 'PK-9012', 'QRS3317', 'Black', 'SUV', 'B-01', 24 * 60 + 30, 'active');

        // The Driver Test account.
        if ($driver) {
            $plate = $driver->vehicles()->value('plate_number') ?? 'DEF2208';

            if ($scenario === 'session') {
                $s = $this->session($fees, $attendant, $driver, 'PK-9021', $plate, 'White', 'Car', 'A-04', 75, 'active');
                $notices->parkingStarted($s);
            } elseif ($scenario === 'pending') {
                $s = $this->session($fees, $attendant, $driver, 'PK-9021', $plate, 'White', 'Car', 'A-04', 75, 'paid', 2);
                $notices->parkingStarted($s);
                $notices->paymentReceived($s, $s->payment);
            } elseif ($scenario === 'reservation') {
                $vehicle = $driver->vehicles()->first();
                $slot = ParkingSlot::query()->where('code', 'A-05')->first();

                if ($vehicle && $slot) {
                    $r = ParkingReservation::create([
                        'code' => 'RSV-DEMO01', 'driver_id' => $driver->id, 'vehicle_id' => $vehicle->id, 'slot_id' => $slot->id,
                        'slot_code' => $slot->code, 'plate_number' => $vehicle->plate_number,
                        'scheduled_at' => now()->addHours(2)->startOfMinute(), 'status' => ParkingReservation::STATUS_BOOKED,
                    ]);
                    $notices->reservationConfirmed($r->load('driver', 'slot'));
                    $reservations->holdTodaysSlots();
                }
            }
        }

        $created = $longParking->detect();
        $this->info('Demo rows created. '.($created ? "{$created} long-parking alert(s) were raised." : ''));
        $this->line('Guard console: BNM 6628 is paid and waiting, HJK 4471 is unpaid, QRS 3317 is long-parked.');
        $this->line('Driver Test (driver@test.com): scenario "'.$scenario.'".');
        $this->warn('The demo payment shows in the manager sales totals until you run: php artisan bayline:demo --clear');

        return self::SUCCESS;
    }

    private function session(FeeCalculator $fees, User $attendant, ?User $driver, string $ticket, string $plate, string $color, string $type, string $slotCode, int $minutesAgo, string $status, int $paidMinutesAgo = 0): ParkingSession
    {
        $slot = ParkingSlot::query()->where('code', $slotCode)->firstOrFail();
        $slot->update(['status' => $status === 'paid' ? ParkingSlot::STATUS_PENDING_EXIT : ParkingSlot::STATUS_OCCUPIED]);

        $session = ParkingSession::create([
            'ticket_number' => $ticket, 'driver_id' => $driver?->id, 'slot_id' => $slot->id, 'slot_code' => $slotCode,
            'attendant_id' => $attendant->id, 'plate_number' => $plate, 'vehicle_color' => $color, 'vehicle_type' => $type,
            'entered_at' => now()->subMinutes($minutesAgo)->startOfMinute(), 'status' => $status,
        ]);

        if ($status === 'paid') {
            $paidAt = now()->subMinutes($paidMinutesAgo);
            $estimate = $fees->estimate($session, $paidAt);

            ParkingPayment::create([
                'receipt_number' => 'RC-'.substr($ticket, 3), 'session_id' => $session->id, 'received_by' => $attendant->id,
                'base_fee' => $estimate['first_hour'], 'additional_fee' => $estimate['additional'] + $estimate['cap_adjustment'],
                'penalty' => $estimate['penalties'], 'discount' => 0, 'total' => $estimate['total'], 'method' => 'cash',
                'receipt_snapshot' => ['cash_received' => ceil($estimate['total'] / 50) * 50, 'change' => ceil($estimate['total'] / 50) * 50 - $estimate['total'], 'demo' => true],
                'paid_at' => $paidAt,
            ]);
        }

        return $session->fresh(['payment', 'slot']);
    }

    private function clear(): void
    {
        DB::transaction(function (): void {
            $sessions = ParkingSession::query()->where('ticket_number', 'like', 'PK-9%')->get();
            $ids = $sessions->pluck('id');
            $slotIds = $sessions->pluck('slot_id')->filter();

            $incidents = Incident::query()->whereIn('session_id', $ids)->pluck('id');
            IncidentNote::query()->whereIn('incident_id', $incidents)->delete();
            IncidentPhoto::query()->whereIn('incident_id', $incidents)->delete();
            Incident::query()->whereIn('id', $incidents)->delete();

            Notice::query()->where(function ($q) use ($ids) {
                $q->where('subject_type', 'session')->whereIn('subject_id', $ids);
            })->delete();

            $reservations = ParkingReservation::query()->where('code', 'RSV-DEMO01')->get();
            Notice::query()->where('subject_type', 'reservation')->whereIn('subject_id', $reservations->pluck('id'))->delete();
            $slotIds = $slotIds->merge($reservations->pluck('slot_id'))->filter();
            ParkingReservation::query()->whereIn('id', $reservations->pluck('id'))->delete();

            ParkingPayment::query()->whereIn('session_id', $ids)->delete();
            ParkingSession::query()->whereIn('id', $ids)->delete();

            ParkingSlot::query()->whereIn('id', $slotIds)->whereIn('status', [
                ParkingSlot::STATUS_OCCUPIED, ParkingSlot::STATUS_PENDING_EXIT, ParkingSlot::STATUS_RESERVED,
            ])->update(['status' => ParkingSlot::STATUS_AVAILABLE]);
        });
    }
}
