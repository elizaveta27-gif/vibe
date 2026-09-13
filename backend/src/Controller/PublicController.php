<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\ValidationException;
use App\Exception\MailDeliveryException;
use App\Service\EmailService;
use App\Service\PublicInvitationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

class PublicController extends AbstractController
{
    public function __construct(
        private readonly PublicInvitationService $publicService,
        private readonly EmailService            $emailService,
        private readonly RateLimiterFactory      $waitlistLimiter,
        private readonly RateLimiterFactory      $rsvpAnswerLimiter,
        #[Autowire(env: 'bool:INVITATIONS_ENABLED')] private readonly bool $invitationsEnabled = true,
    ) {}

    #[Route('/api/public/invitations/{shortCode}', name: 'public_invitation_api', methods: ['GET'])]
    public function api(string $shortCode): JsonResponse
    {
        //todo закрыть доступ по api
        $invitation = $this->publicService->findByShortCode($shortCode);

        if (!$invitation) {
            return $this->json(['error' => 'Приглашение не найдено'], 404);
        }

        $this->publicService->incrementViewCount($invitation);

        return $this->json([
            'template_id' => $invitation->getTemplateId(),
            'blocks'      => $invitation->getBlocks(),
        ]);
    }

    #[Route('/api/public/rsvp/{shortCode}', name: 'public_rsvp', methods: ['POST'])]
    public function rsvp(string $shortCode, Request $request): JsonResponse
    {
        $limiter = $this->rsvpAnswerLimiter->create($request->getClientIp());
        if (!$limiter->consume(1)->isAccepted()) {
            return $this->json(['error' => 'Слишком много запросов. Попробуйте позже.'], 429);
        }

        $invitation = $this->publicService->findByShortCode($shortCode);

        if (!$invitation) {
            return $this->json(['error' => 'Приглашение не найдено'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Невалидный JSON'], 400);
        }

        $honeypot = trim((string) ($data['website'] ?? ''));
        if ($honeypot !== '') {
            return $this->json(['error' => 'Заявка отклонена.'], 422);
        }

        $startedAt = (int) ($data['started_at'] ?? 0);
        $elapsedSeconds = time() - $startedAt;
        if ($startedAt <= 0 || $elapsedSeconds < 3 || $elapsedSeconds > 86400) {
            return $this->json(['error' => 'Обновите страницу и отправьте ответ ещё раз.'], 422);
        }

        $name = $data['name'] ?? '';
        $answer = $data['answer'] ?? '';
        $responses = $data['responses'] ?? [];
        if (!is_string($name) || !is_string($answer) || !is_array($responses)) {
            return $this->json(['errors' => ['request' => 'Поля ответа имеют неверный формат']], 422);
        }

        try {
            $this->publicService->saveRsvp(
                $invitation,
                trim($name),
                $answer,
                $responses,
            );
        } catch (ValidationException $e) {
            return $this->json(['errors' => $e->getErrors()], 422);
        }

        return $this->json(['ok' => true], 201);
    }

    #[Route('/api/public/config', name: 'public_config', methods: ['GET'])]
    public function config(): JsonResponse
    {
        return $this->json(['invitations_enabled' => $this->invitationsEnabled]);
    }

    #[Route('/api/public/waitlist', name: 'public_waitlist', methods: ['POST'])]
    public function waitlist(Request $request): JsonResponse
    {
        $limiter = $this->waitlistLimiter->create($request->getClientIp());
        if (!$limiter->consume(1)->isAccepted()) {
            return $this->json(['error' => 'Слишком много запросов. Попробуйте позже.'], 429);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Невалидный JSON'], 400);
        }

        $name = trim(is_string($data['name'] ?? null) ? $data['name'] : '');
        $email = trim(is_string($data['email'] ?? null) ? $data['email'] : '');
        $templateId = trim(is_string($data['template_id'] ?? null) ? $data['template_id'] : '');

        $errors = [];
        if (mb_strlen($name) < 2) {
            $errors['name'] = 'Введите ваше имя';
        }
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Введите корректный email';
        }
        if ($errors) {
            return $this->json(['errors' => $errors], 422);
        }

        try {
            $this->emailService->sendWaitlistNotification($name, $email, $templateId);
        } catch (MailDeliveryException) {
            return $this->json([
                'error' => 'Заявку не удалось отправить. Попробуйте позже или напишите в поддержку.',
            ], 502);
        }

        return $this->json(null, 204);
    }

    #[Route('/{shortCode}', name: 'public_invitation_redirect', requirements: ['shortCode' => '[a-zA-Z0-9]{8}'], methods: ['GET'])]
    public function shortLink(string $shortCode): RedirectResponse
    {
        return $this->redirect('/invite?code=' . $shortCode);
    }
}
