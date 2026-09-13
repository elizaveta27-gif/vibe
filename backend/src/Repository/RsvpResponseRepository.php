<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Invitation;
use App\Entity\RsvpResponse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RsvpResponseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RsvpResponse::class);
    }

    public function findByInvitation(Invitation $invitation): array
    {
        return $this->findBy(['invitation' => $invitation], ['createdAt' => 'DESC']);
    }
}
