<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Invitation;
use App\Entity\User;
use App\Exception\ValidationException;
use App\Repository\InvitationRepository;
use App\Repository\RsvpResponseRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

class InvitationService
{
    //todo хранить в БД
    private const VALID_TEMPLATES = ['template_1', 'template_2', 'template_5', 'template_6', 'template_7', 'template_8'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly InvitationRepository   $invitationRepository,
        private readonly RsvpResponseRepository $rsvpRepository,
    ) {}

    /**
     * @throws ValidationException
     * @throws \RuntimeException
     */
    public function create(User $user, string $templateId, array $blocks): Invitation
    {
        if (!in_array($templateId, self::VALID_TEMPLATES, true)) {
            throw new ValidationException(['template_id' => 'Неверный template_id']);
        }

        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                $shortCode  = $this->invitationRepository->generateUniqueShortCode();
                $invitation = new Invitation($shortCode, $user);
                $invitation->setTemplateId($templateId);
                $invitation->setBlocks($blocks);

                $this->em->persist($invitation);
                $this->em->flush();

                return $invitation;
            } catch (UniqueConstraintViolationException) {
                $this->em->clear();
            }
        }

        throw new \RuntimeException('Не удалось создать приглашение, попробуйте ещё раз');
    }

    public function findAllByUser(User $user): array
    {
        return $this->invitationRepository->findByUser($user);
    }

    public function findById(int $id): ?Invitation
    {
        return $this->invitationRepository->find($id);
    }

    public function isOwner(Invitation $invitation, User $user): bool
    {
        $invitationUserId = $invitation->getUser()->getId();
        return $invitationUserId !== null && $invitationUserId === $user->getId();
    }

    public function update(Invitation $invitation, array $blocks): Invitation
    {
        $invitation->setBlocks(array_merge($invitation->getBlocks(), $blocks));
        $this->em->flush();

        return $invitation;
    }

    public function delete(Invitation $invitation): void
    {
        $this->em->remove($invitation);
        $this->em->flush();
    }

    public function getRsvpResponses(Invitation $invitation): array
    {
        return $this->rsvpRepository->findByInvitation($invitation);
    }
}
