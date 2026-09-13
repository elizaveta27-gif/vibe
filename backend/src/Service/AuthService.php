<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Exception\ValidationException;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AuthService
{
    public function __construct(
        private readonly EntityManagerInterface      $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ValidatorInterface          $validator,
        private readonly JWTTokenManagerInterface    $jwt,
        private readonly UserRepository              $userRepository,
    ) {}

    /**
     * @throws ValidationException
     */
    public function register(string $email, string $name, string $plainPassword): array
    {
        $user = new User();
        $user->setEmail($email);
        $user->setName($name);

        $violations = $this->validator->validate($user);

        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }
            throw new ValidationException($errors);
        }

        if (mb_strlen($plainPassword) < 8) {
            throw new ValidationException(['password' => 'Минимум 8 символов']);
        }

        if ($this->userRepository->findOneBy(['email' => $email])) {
            throw new ValidationException(['email' => 'Этот email уже используется']);
        }

        try {
            $user->setPassword($this->hasher->hashPassword($user, $plainPassword));
            $this->em->persist($user);
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ValidationException(['email' => 'Этот email уже используется']);
        }

        return [
            'token' => $this->jwt->create($user),
            'user'  => [
                'id'    => $user->getId(),
                'email' => $user->getEmail(),
                'name'  => $user->getName(),
            ],
        ];
    }
}
