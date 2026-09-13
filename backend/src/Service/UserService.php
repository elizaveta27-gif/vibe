<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Exception\ValidationException;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserService
{
    public function __construct(
        private readonly EntityManagerInterface      $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly UserRepository              $userRepository,
    ) {}

    public function updateName(User $user, string $name): void
    {
        $name   = trim($name);
        $errors = [];
        if (mb_strlen($name) < 2) {
            $errors['name'] = 'Минимум 2 символа';
        } elseif (mb_strlen($name) > 100) {
            $errors['name'] = 'Максимум 100 символов';
        }
        if ($errors) {
            throw new ValidationException($errors);
        }

        $user->setName($name);
        $this->em->flush();
    }

    public function updateEmail(User $user, string $newEmail, string $currentPassword): void
    {
        $errors = [];

        if (!$this->hasher->isPasswordValid($user, $currentPassword)) {
            $errors['current_password'] = 'Неверный текущий пароль';
        }

        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Некорректный email';
        } elseif ($newEmail !== $user->getEmail() && $this->userRepository->findOneBy(['email' => $newEmail])) {
            $errors['email'] = 'Этот email уже используется';
        }

        if ($errors) {
            throw new ValidationException($errors);
        }

        $user->setEmail($newEmail);
        $this->em->flush();
    }

    public function updatePassword(User $user, string $currentPassword, string $newPassword): void
    {
        $errors = [];

        if (!$this->hasher->isPasswordValid($user, $currentPassword)) {
            $errors['current_password'] = 'Неверный текущий пароль';
        }

        if (mb_strlen($newPassword) < 8) {
            $errors['new_password'] = 'Минимум 8 символов';
        }

        if ($errors) {
            throw new ValidationException($errors);
        }

        $user->setPassword($this->hasher->hashPassword($user, $newPassword));
        $user->incrementTokenVersion();
        $this->em->flush();
    }
}
