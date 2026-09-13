<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Exception\ValidationException;
use App\Service\EmailService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::EXCEPTION)]
class ExceptionListener
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly EmailService    $emailService,
    ) {}

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if ($exception instanceof ValidationException) {
            $event->setResponse(new JsonResponse(
                ['errors' => $exception->getErrors()],
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
            ));
            return;
        }

        if ($exception instanceof HttpExceptionInterface) {
            $event->setResponse(new JsonResponse(
                ['error' => $exception->getMessage() ?: 'Ошибка запроса'],
                $exception->getStatusCode(),
            ));
            return;
        }

        $url = (string) $event->getRequest()->getUri();

        $this->logger->error($exception->getMessage(), [
            'exception' => $exception,
            'url'       => $url,
        ]);

        $this->emailService->sendErrorAlert($exception, $url);

        $event->setResponse(new JsonResponse(
            ['error' => 'На сайте произошла ошибка. Попробуйте позже'],
            JsonResponse::HTTP_INTERNAL_SERVER_ERROR,
        ));
    }
}
