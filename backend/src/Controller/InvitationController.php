<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Invitation;
use App\Entity\RsvpResponse;
use App\Service\InvitationService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/invitations')]
class InvitationController extends AppController
{
    public function __construct(
        private readonly InvitationService  $invitationService,
        private readonly RateLimiterFactory $createInvitationLimiter,
        private readonly RateLimiterFactory $uploadImageLimiter,
        #[Autowire(env: 'bool:INVITATIONS_ENABLED')] private readonly bool $invitationsEnabled = true,
    ) {}

    #[Route('', name: 'invitation_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        if (!$this->invitationsEnabled) {
            return $this->json(['error' => 'Создание приглашений временно недоступно.'], 503);
        }

        $limiter = $this->createInvitationLimiter->create($request->getClientIp());
        if (!$limiter->consume(1)->isAccepted()) {
            return $this->json(['error' => 'Слишком много запросов. Попробуйте позже.'], 429);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Невалидный JSON'], 400);
        }

        $blocks = $data['blocks'] ?? [];
        if (!is_array($blocks)) {
            return $this->json(['error' => 'blocks должен быть объектом'], 422);
        }

        $invitation = $this->invitationService->create(
            $this->getCurrentUser(),
            $data['template_id'] ?? '',
            $blocks,
        );

        return $this->json([
            'id'          => $invitation->getId(),
            'short_code'  => $invitation->getShortCode(),
            'template_id' => $invitation->getTemplateId(),
            'created_at'  => $invitation->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], 201);
    }

    #[Route('', name: 'invitation_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $invitations = $this->invitationService->findAllByUser($this->getCurrentUser());

        return $this->json(array_map(fn(Invitation $inv) => [
            'id'          => $inv->getId(),
            'short_code'  => $inv->getShortCode(),
            'template_id' => $inv->getTemplateId(),
            'hero_names'  => $inv->getBlocks()['hero-names'] ?? null,
            'view_count'  => $inv->getViewCount(),
            'created_at'  => $inv->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at'  => $inv->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ], $invitations));
    }

    #[Route('/upload-image', name: 'invitation_upload_image', methods: ['POST'])]
    public function uploadImage(Request $request, #[Autowire('%kernel.project_dir%')] string $projectDir): JsonResponse
    {
        $limiter = $this->uploadImageLimiter->create($request->getClientIp());
        if (!$limiter->consume(1)->isAccepted()) {
            return $this->json(['error' => 'Слишком много загрузок. Попробуйте позже.'], 429);
        }

        $file = $request->files->get('image');
        if (!$file instanceof UploadedFile) {
            return $this->json(['errors' => ['image' => 'Выберите изображение']], 422);
        }

        if (!$file->isValid()) {
            return $this->json(['errors' => ['image' => $this->getUploadErrorMessage($file)]], 422);
        }

        if ($file->getSize() > 5 * 1024 * 1024) {
            return $this->json(['errors' => ['image' => 'Максимальный размер изображения - 5 МБ']], 422);
        }

        $extensions = [
            'image/jpeg' => 'jpg',
            'image/pjpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/avif' => 'avif',
        ];

        $mime = (string) $file->getMimeType();

        if (!isset($extensions[$mime])) {
            return $this->json(['errors' => ['image' => 'Поддерживаются только JPG, PNG, WebP, GIF и AVIF']], 422);
        }

        $userId = $this->getCurrentUser()->getId();
        if ($userId === null) {
            return $this->json(['error' => 'Пользователь не найден'], 401);
        }

        $uploadDir = $projectDir . '/public/uploads/invitations/' . $userId;
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            return $this->json(['error' => 'Не удалось подготовить папку загрузки'], 500);
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        $file->move($uploadDir, $filename);

        return $this->json([
            'url' => '/uploads/invitations/' . $userId . '/' . $filename,
        ], 201);
    }

    private function getUploadErrorMessage(UploadedFile $file): string
    {
        return match ($file->getError()) {
            \UPLOAD_ERR_INI_SIZE,
            \UPLOAD_ERR_FORM_SIZE => 'Максимальный размер изображения - 5 МБ',
            \UPLOAD_ERR_PARTIAL => 'Файл загрузился не полностью. Попробуйте еще раз',
            \UPLOAD_ERR_NO_FILE => 'Выберите изображение',
            default => 'Файл не удалось загрузить',
        };
    }

    #[Route('/{id}', name: 'invitation_get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $invitation = $this->resolveOwnedInvitation($id);
        if ($invitation instanceof JsonResponse) {
            return $invitation;
        }

        return $this->json([
            'id'          => $invitation->getId(),
            'short_code'  => $invitation->getShortCode(),
            'template_id' => $invitation->getTemplateId(),
            'blocks'      => $invitation->getBlocks(),
            'created_at'  => $invitation->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at'  => $invitation->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }

    #[Route('/{id}', name: 'invitation_update', methods: ['PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $invitation = $this->resolveOwnedInvitation($id);
        if ($invitation instanceof JsonResponse) {
            return $invitation;
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Невалидный JSON'], 400);
        }

        if (!isset($data['blocks'])) {
            return $this->json(['error' => 'Поле blocks обязательно'], 400);
        }

        if (!is_array($data['blocks'])) {
            return $this->json(['error' => 'blocks должен быть объектом'], 422);
        }

        $invitation = $this->invitationService->update($invitation, $data['blocks']);

        return $this->json([
            'id'         => $invitation->getId(),
            'short_code' => $invitation->getShortCode(),
            'blocks'     => $invitation->getBlocks(),
            'updated_at' => $invitation->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }

    #[Route('/{id}', name: 'invitation_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $invitation = $this->resolveOwnedInvitation($id);
        if ($invitation instanceof JsonResponse) {
            return $invitation;
        }

        $this->invitationService->delete($invitation);

        return $this->json(null, 204);
    }

    #[Route('/{id}/rsvp', name: 'invitation_rsvp_list', methods: ['GET'])]
    public function rsvpList(int $id): JsonResponse
    {
        $invitation = $this->resolveOwnedInvitation($id);
        if ($invitation instanceof JsonResponse) {
            return $invitation;
        }

        return $this->json(array_map(fn(RsvpResponse $r) => [
            'name'       => $r->getName(),
            'answer'     => $r->getAnswer(),
            'responses'  => $r->getResponses(),
            'created_at' => $r->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], $this->invitationService->getRsvpResponses($invitation)));
    }

    private function resolveOwnedInvitation(int $id): Invitation|JsonResponse
    {
        $invitation = $this->invitationService->findById($id);

        if (!$invitation) {
            return $this->json(['error' => 'Не найдено'], 404);
        }

        if (!$this->invitationService->isOwner($invitation, $this->getCurrentUser())) {
            return $this->json(['error' => 'Доступ запрещён'], 403);
        }

        return $invitation;
    }
}
