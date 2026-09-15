<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\InvalidAttendeeQrTokenException;
use App\Domain\Form\Models\Attendee;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use DomainException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use UnexpectedValueException;

/**
 * Vérifie le QR d'une personne inscrite : signature, expiration, inscription
 * toujours confirmée, puis révocation (qr_jti). Le statut passe avant la
 * révocation : une annulation efface aussi qr_jti, et l'accueil doit lire
 * « annulée » plutôt que « un nouveau QR a été émis ». L'appartenance à
 * l'événement scanné reste vérifiée par l'appelant (GuestExistsForEvent) ;
 * un second scan est accepté ici et signalé en conflit par RecordCheckIn.
 */
final class VerifyAttendeeQrToken
{
    private const ALGORITHM = 'HS256';

    public function handle(string $token): Attendee
    {
        try {
            $payload = JWT::decode($token, new Key((string) config('services.ticket_qr.secret'), self::ALGORITHM));
        } catch (ExpiredException) {
            throw InvalidAttendeeQrTokenException::expired();
        } catch (UnexpectedValueException|DomainException) {
            throw InvalidAttendeeQrTokenException::invalid();
        }

        $attendee = Attendee::query()->withoutGlobalScopes()->find($payload->aid ?? null);

        if ($attendee === null || $attendee->deleted_at !== null) {
            throw InvalidAttendeeQrTokenException::invalid();
        }

        $registration = Registration::query()->withoutGlobalScopes()->find($attendee->registration_id);

        if ($registration === null || $registration->deleted_at !== null || $registration->status !== RegistrationStatus::Confirmed) {
            throw InvalidAttendeeQrTokenException::notConfirmed();
        }

        if ($attendee->qr_jti === null || $attendee->qr_jti !== ($payload->jti ?? null)) {
            throw InvalidAttendeeQrTokenException::revoked();
        }

        return $attendee;
    }
}
