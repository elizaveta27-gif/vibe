<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PasswordResetToken;
use App\Exception\ValidationException;
use App\Repository\PasswordResetTokenRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class PasswordResetService
{
    private const TTL_MINUTES = 60;

    public function __construct(
        private readonly PasswordResetTokenRepository $tokenRepository,
        private readonly UserRepository               $userRepository,
        private readonly EntityManagerInterface       $em,
        private readonly UserPasswordHasherInterface  $hasher,
        private readonly EmailService                 $emailService,
    ) {}

    /**
     * Создаёт токен и отправляет письмо. Если email не найден — молчим (не раскрываем существование).
     */
    public function requestReset(string $email): void
    {
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException(['email' => 'Введите корректный email']);
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);
        if (!$user) {
            return;
        }

        $this->tokenRepository->deleteByUser($user);

        $rawToken  = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = new \DateTimeImmutable('+' . self::TTL_MINUTES . ' minutes');

        $resetToken = new PasswordResetToken($user, $tokenHash, $expiresAt);
        $this->em->persist($resetToken);
        $this->em->flush();

        $this->emailService->sendPasswordReset($user, $rawToken, self::TTL_MINUTES);
    }

    /**
     * Устанавливает новый пароль по сырому токену из ссылки.
     *
     * @throws ValidationException
     */
    public function reset(string $rawToken, string $newPassword): void
    {
        $errors = [];

        if (!$rawToken || !preg_match('/^[0-9a-f]{64}$/', $rawToken)) {
            throw new ValidationException(['token' => 'Токен недействителен']);
        }

        if (mb_strlen($newPassword) < 8) {
            $errors['password'] = 'Минимум 8 символов';
        }

        if ($errors) {
            throw new ValidationException($errors);
        }

        $tokenHash   = hash('sha256', $rawToken);
        $tokenEntity = $this->tokenRepository->findValidByHash($tokenHash);

        if (!$tokenEntity) {
            throw new ValidationException(['token' => 'Ссылка недействительна или устарела']);
        }

        $user = $tokenEntity->getUser();
        $user->setPassword($this->hasher->hashPassword($user, $newPassword));
        $user->incrementTokenVersion();

        $tokenEntity->markUsed();

        $this->em->flush();
    }
}
