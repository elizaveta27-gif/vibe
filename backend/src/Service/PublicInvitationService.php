<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Invitation;
use App\Entity\RsvpResponse;
use App\Exception\ValidationException;
use App\Repository\InvitationRepository;
use Doctrine\ORM\EntityManagerInterface;

class PublicInvitationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly InvitationRepository   $invitationRepository,
        private readonly EmailService           $emailService,
    ) {}

    public function findByShortCode(string $shortCode): ?Invitation
    {
        return $this->invitationRepository->findByShortCode($shortCode);
    }

    public function incrementViewCount(Invitation $invitation): void
    {
        $this->invitationRepository->incrementViewCount($invitation->getId());
    }

    /**
     * @throws ValidationException
     */
    public function saveRsvp(Invitation $invitation, string $name, string $answer, array $responses = []): void
    {
        if ($name === '') {
            throw new ValidationException(['name' => 'Укажите имя']);
        }

        if (!in_array($answer, ['yes', 'no'], true)) {
            throw new ValidationException(['answer' => 'Неверный ответ']);
        }

        if (mb_strlen($name) > 100) {
            throw new ValidationException(['name' => 'Имя не должно превышать 100 символов']);
        }

        if (count($responses) > 20) {
            throw new ValidationException(['responses' => 'Слишком много полей']);
        }

        $sanitizedResponses = $this->sanitizeResponses($responses);
        $rsvp = new RsvpResponse($invitation, $name, $answer, $sanitizedResponses);
        $this->em->persist($rsvp);
        $this->em->flush();

        $this->emailService->sendRsvpNotification($invitation, $name, $answer, $sanitizedResponses);
    }

    private function sanitizeResponses(array $responses): array
    {
        $result = [];
        foreach ($responses as $key => $value) {
            if (!is_scalar($value) && $value !== null) {
                continue;
            }
            $key          = substr(str_replace("\0", '', (string) $key), 0, 100);
            $value        = substr(str_replace("\0", '', (string) $value), 0, 500);
            $result[$key] = $value;
        }
        return $result;
    }
}
