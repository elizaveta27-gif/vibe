<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\InvitationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InvitationRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Invitation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 8, unique: true)]
    private string $shortCode;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    //todo фиксировать неверно, наверно лучше регистраиця в бд
    #[Assert\Choice(choices: ['template_1', 'template_2', 'template_5', 'template_6', 'template_7', 'template_8'])]
    private string $templateId;

    /**
     * Хранит все изменённые блоки шаблона.
     *
     * Формат:
     * {
     *   "hero-names": "Александр & Виктория",
     *   "hero-date": "15 июня 2025",
     *   "hero-image": "/uploads/uuid/cover.jpg",
     *   "timeline-0-time": "15:00",
     *   "timeline-0-title": "Сбор гостей",
     *   "timeline-0-desc": "Встречаемся в ресторане",
     *   "footer-title": "С любовью, Саша и Вика"
     * }
     */
    #[ORM\Column(type: 'json')]
    private array $blocks = [];

    #[ORM\Column(options: ['default' => 0])]
    private int $viewCount = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $shortCode, User $user)
    {
        $this->shortCode = $shortCode;
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->user      = $user;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getShortCode(): string
    {
        return $this->shortCode;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getTemplateId(): string
    {
        return $this->templateId;
    }

    public function setTemplateId(string $templateId): static
    {
        $this->templateId = $templateId;
        return $this;
    }

    public function getBlocks(): array
    {
        return $this->blocks;
    }

    public function setBlocks(array $blocks): static
    {
        $this->blocks = $blocks;
        return $this;
    }

    public function getViewCount(): int
    {
        return $this->viewCount;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
