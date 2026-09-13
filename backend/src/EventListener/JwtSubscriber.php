<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class JwtSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly UserRepository $userRepository) {}

    public static function getSubscribedEvents(): array
    {
        return [
            Events::JWT_CREATED => 'onJwtCreated',
            Events::JWT_DECODED => 'onJwtDecoded',
        ];
    }

    public function onJwtCreated(JWTCreatedEvent $event): void
    {
        /** @var \App\Entity\User $user */
        $user    = $event->getUser();
        $payload = $event->getData();
        $payload['tv'] = $user->getTokenVersion();
        $event->setData($payload);
    }

    public function onJwtDecoded(JWTDecodedEvent $event): void
    {
        $payload = $event->getPayload();

        if (!isset($payload['tv'])) {
            $event->markAsInvalid();
            return;
        }

        $identifier = $payload['username'] ?? $payload['sub'] ?? null;
        if (!$identifier) {
            $event->markAsInvalid();
            return;
        }

        $user = $this->userRepository->findOneBy(['email' => $identifier]);
        if (!$user || $payload['tv'] !== $user->getTokenVersion()) {
            $event->markAsInvalid();
        }
    }
}
