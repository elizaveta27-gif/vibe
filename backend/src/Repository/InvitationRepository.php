<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Invitation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class InvitationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invitation::class);
    }

    public function findByUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['createdAt' => 'DESC']);
    }

    public function findByShortCode(string $shortCode): ?Invitation
    {
        return $this->findOneBy(['shortCode' => $shortCode]);
    }

    public function incrementViewCount(int $id): void
    {
        $this->createQueryBuilder('i')
            ->update()
            ->set('i.viewCount', 'i.viewCount + 1')
            ->where('i.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->execute();
    }

    public function generateUniqueShortCode(int $maxAttempts = 10): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        for ($i = 0; $i < $maxAttempts; $i++) {
            $code = '';
            for ($j = 0; $j < 8; $j++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
            if ($this->findByShortCode($code) === null) {
                return $code;
            }
        }
        throw new \RuntimeException('Не удалось сгенерировать уникальный short code');
    }
}
