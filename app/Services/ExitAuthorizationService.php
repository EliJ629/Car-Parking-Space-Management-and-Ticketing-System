<?php

namespace App\Services;

use App\Models\ExitAuthorization;
use App\Models\ParkingPayment;
use App\Models\ParkingSession;
use App\Models\User;
use App\Support\Lot;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * The ledger of exit permissions. Every payment issues one authorization with its own code. It ends in exactly one
 * of two ways: the Guard uses it (the exit is confirmed) or its time runs out (expired). Nothing is deleted, so an
 * expired permission and the one that replaces it are both kept.
 *
 * Callers run these methods inside the transaction that already holds a lock on the parking session, which is what
 * makes two requests for the same vehicle (a double click, a retried job) take turns instead of both succeeding.
 */
class ExitAuthorizationService
{
    public function __construct(private NoticeService $notices) {}

    /** The permission that is open for this session right now, if any. */
    public function active(ParkingSession $session): ?ExitAuthorization
    {
        return ExitAuthorization::query()->where('session_id', $session->id)->active()->latest('sequence')->first();
    }

    /**
     * Issue a new authorization for a payment that has just been recorded. An older one that is still open is closed
     * as expired first, so a session never has two open permissions.
     */
    public function issue(ParkingSession $session, ParkingPayment $payment, User $by, float $amount, float $cumulative): ExitAuthorization
    {
        ExitAuthorization::query()->where('session_id', $session->id)->active()->update(['status' => ExitAuthorization::EXPIRED, 'expired_at' => now(), 'updated_at' => now()]);

        $sequence = (int) ExitAuthorization::query()->where('session_id', $session->id)->max('sequence') + 1;
        $now = now();

        // The code comes from the highest code in the table. Two bookings at the same instant can pick the same
        // number; the unique index refuses the second and it simply takes the next number.
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(fn () => ExitAuthorization::create([
                    'code' => ExitAuthorization::nextCode(),
                    'session_id' => $session->id,
                    'payment_id' => $payment->id,
                    'sequence' => $sequence,
                    'status' => ExitAuthorization::ACTIVE,
                    'amount' => round($amount, 2),
                    'cumulative_paid' => round($cumulative, 2),
                    'receipt_number' => $payment->receipt_number,
                    'issued_by' => $by->id,
                    'issued_at' => $now,
                    'expires_at' => $now->copy()->addMinutes(Lot::exitWindowMinutes()),
                ]));
            } catch (QueryException $e) {
                if ($attempt >= 5) {
                    throw $e;
                }
            }
        }
    }

    /** The Guard confirmed the exit. */
    public function markUsed(ParkingSession $session, User $guard): void
    {
        ExitAuthorization::query()->where('session_id', $session->id)->active()
            ->update(['status' => ExitAuthorization::USED, 'used_at' => now(), 'used_by' => $guard->id, 'updated_at' => now()]);
    }

    /** The time ran out. Returns the authorization that was closed, or null when there was nothing open to close. */
    public function expire(ParkingSession $session): ?ExitAuthorization
    {
        $authorization = ExitAuthorization::query()->where('session_id', $session->id)->active()->lockForUpdate()->latest('sequence')->first();

        if (! $authorization) {
            return null;
        }

        $authorization->update(['status' => ExitAuthorization::EXPIRED, 'expired_at' => now()]);

        return $authorization;
    }

    /**
     * Tell the driver the permission ended: an in-app notice for a registered driver, a text message for a walk-in
     * with a mobile number. Sent once per authorization. The row is claimed under a lock before anything is sent,
     * so a retry or a second worker finds it claimed and sends nothing.
     */
    public function notifyExpired(ExitAuthorization $authorization): void
    {
        $claimed = DB::transaction(function () use ($authorization): bool {
            $locked = ExitAuthorization::query()->lockForUpdate()->find($authorization->id);

            if (! $locked || $locked->notify_channel !== null || $locked->notified_at !== null) {
                return false;
            }

            $locked->update(['notify_channel' => 'pending']);

            return true;
        });

        if (! $claimed) {
            return;
        }

        $session = ParkingSession::query()->find($authorization->session_id);
        $delivery = $session
            ? $this->notices->exitWindowEnded($session, $authorization)
            : ['ok' => false, 'channel' => null, 'result' => 'The session no longer exists.', 'at' => null];

        $authorization->update([
            'notified_at' => $delivery['ok'] ? ($delivery['at'] ?? now()) : null,
            'notify_channel' => $delivery['channel'] ?? 'none',
            'notify_result' => mb_substr((string) $delivery['result'], 0, 160),
        ]);
    }

    /**
     * Charges are settled before a new permission is issued: a session whose permission ended is open again and owes
     * the difference between what it has paid in total and what it owes now. Money already paid is never charged twice.
     */
    public function alreadyPaid(ParkingSession $session): float
    {
        return ExitAuthorization::paidFor($session);
    }
}
