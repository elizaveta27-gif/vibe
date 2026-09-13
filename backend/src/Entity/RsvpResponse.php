<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RsvpResponseRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RsvpResponseRepository::class)]
class RsvpResponse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Invitation $invitation;

    #[ORM\Column(length: 100)]
    private string $name;

    /** 'yes' | 'no' */
    #[ORM\Column(length: 3)]
    private string $answer;

    #[ORM\Column(type: 'json')]
    private array $responses = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Invitation $invitation, string $name, string $answer, array $responses = [])
    {
        $this->invitation = $invitation;
        $this->name       = $name;
        $this->answer     = $answer;
        $this->responses  = $responses;
        $this->createdAt  = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInvitation(): Invitation
    {
        return $this->invitation;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAnswer(): string
    {
        return $this->answer;
    }

    public function getResponses(): array
    {
        return $this->responses;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
