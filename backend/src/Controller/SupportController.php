<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\MailDeliveryException;
use App\Service\SupportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

class SupportController extends AbstractController
{
    public function __construct(private readonly SupportService $supportService) {}

    #[Route('/api/support', name: 'support_submit', methods: ['POST'])]
    public function submit(Request $request, RateLimiterFactory $supportRequestLimiter): JsonResponse
    {
        $limiter = $supportRequestLimiter->create($request->getClientIp());
        if (!$limiter->consume(1)->isAccepted()) {
            return $this->json(['error' => 'Слишком много запросов. Попробуйте позже.'], 429);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Невалидный JSON'], 400);
        }

        try {
            $this->supportService->submit(
                trim((string) ($data['email'] ?? '')),
                trim((string) ($data['message'] ?? '')),
                (array) ($data['contact_methods'] ?? []),
                trim((string) ($data['contact_handle'] ?? '')),
            );
        } catch (MailDeliveryException) {
            return $this->json([
                'error' => 'Сообщение не удалось отправить. Попробуйте позже или напишите нам напрямую.',
            ], 502);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
