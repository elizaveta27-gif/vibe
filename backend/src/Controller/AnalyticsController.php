<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

class AnalyticsController extends AbstractController
{
    private const ALLOWED_EVENTS = [
        'page_view',
        'template_select',
        'support_open',
        'order_click',
    ];

    public function __construct(
        private readonly Connection         $db,
        private readonly RateLimiterFactory $analyticsEventLimiter,
    ) {}

    #[Route('/api/public/analytics/event', name: 'analytics_event', methods: ['POST'])]
    public function event(Request $request): JsonResponse
    {
        $limiter = $this->analyticsEventLimiter->create($request->getClientIp());
        if (!$limiter->consume(1)->isAccepted()) {
            return $this->json(['error' => 'Слишком много запросов. Попробуйте позже.'], 429);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Невалидный JSON'], Response::HTTP_BAD_REQUEST);
        }

        $event = trim(is_string($data['event'] ?? null) ? $data['event'] : '');
        if (!in_array($event, self::ALLOWED_EVENTS, true)) {
            return $this->json(['error' => 'Unknown event'], Response::HTTP_BAD_REQUEST);
        }

        $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];

        $this->db->executeStatement(
            'INSERT INTO analytics_events (event, meta) VALUES (:event, :meta)',
            ['event' => $event, 'meta' => json_encode($meta)],
        );

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
