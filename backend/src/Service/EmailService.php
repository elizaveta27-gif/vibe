<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Invitation;
use App\Entity\User;
use App\Exception\MailDeliveryException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class EmailService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly string          $adminEmail,
        private readonly string          $appUrl,
    ) {}

    public function sendPasswordReset(User $user, string $rawToken, int $ttlMinutes): void
    {
        $link = rtrim($this->appUrl, '/') . '/reset-password?token=' . $rawToken;

        $this->mailer->send(
            (new Email())
                ->from($this->adminEmail)
                ->to($user->getEmail())
                ->replyTo($this->adminEmail)
                ->subject('[Vibe] Сброс пароля')
                ->text(sprintf(
                    "Добрый день, %s!\n\n" .
                    "Мы получили запрос на сброс пароля для вашего аккаунта Vibe.\n\n" .
                    "Перейдите по ссылке, чтобы установить новый пароль:\n%s\n\n" .
                    "Ссылка действует %d минут. Если вы не запрашивали сброс — просто проигнорируйте это письмо.\n\n" .
                    "С уважением,\nКоманда Vibe",
                    $user->getName(),
                    $link,
                    $ttlMinutes,
                ))
        );
    }

    public function sendRsvpNotification(
        Invitation $invitation,
        string     $name,
        string     $answer,
        array      $responses,
    ): void {
        $owner      = $invitation->getUser();
        $answerText = $answer === 'yes' ? 'подтвердил(а) участие ✓' : 'не сможет прийти ✗';

        $invitationNames = $invitation->getBlocks()['hero-names'] ?? null;
        $responsesUrl    = rtrim($this->appUrl, '/') . '/responses?id=' . $invitation->getId();

        $extraLines = '';
        foreach ($responses as $key => $value) {
            if ($value !== '' && $value !== null) {
                $extraLines .= sprintf(
                    "  %s: %s\n",
                    substr((string) $key, 0, 100),
                    substr((string) $value, 0, 500),
                );
            }
        }

        $body = sprintf(
            "Здравствуйте, %s!\n\n" .
            "Гость %s %s.\n" .
            "%s%s" .
            "Посмотреть все ответы: %s\n\n" .
            "— Vibe",
            $owner->getName(),
            $name,
            $answerText,
            $invitationNames ? sprintf("Приглашение: %s\n", $invitationNames) : '',
            $extraLines ? "\nДополнительно:\n" . $extraLines . "\n" : '',
            $responsesUrl,
        );

        $this->sendSilently(
            (new Email())
                ->from($this->adminEmail)
                ->to($owner->getEmail())
                ->replyTo($this->adminEmail)
                ->subject(sprintf('[Vibe] %s %s', $name, $answerText))
                ->text($body),
            'RSVP owner notification failed',
        );
    }

    public function sendWaitlistNotification(string $name, string $email, string $templateId): void
    {
        $date = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s') . ' UTC';

        $this->sendOrFail(
            (new Email())
                ->from($this->adminEmail)
                ->to($this->adminEmail)
                ->replyTo($email)
                ->subject('[Vibe] Новая заявка на приглашение')
                ->text(sprintf(
                    "Имя: %s\nEmail: %s\nШаблон: %s\nДата: %s",
                    $name, $email, $templateId ?: 'не указан', $date,
                )),
            'Waitlist email send failed',
        );
    }

    public function sendSupportTicket(string $fromEmail, string $message, string $contactStr): void
    {
        $date = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s') . ' UTC';

        $this->sendOrFail(
            (new Email())
                ->from($this->adminEmail)
                ->to($this->adminEmail)
                ->replyTo($fromEmail)
                ->subject('[Vibe] Обращение в поддержку')
                ->text(sprintf(
                    "От: %s\n\nСообщение:\n%s\n\nУдобный способ связи: %s\n\nДата: %s",
                    $fromEmail, $message, $contactStr, $date,
                )),
            'Support ticket email send failed',
        );
    }

    public function sendErrorAlert(\Throwable $exception, string $url): void
    {
        $this->sendSilently(
            (new Email())
                ->from($this->adminEmail)
                ->to($this->adminEmail)
                ->subject(sprintf('[Vibe] Ошибка: %s', $exception->getMessage()))
                ->text(sprintf(
                    "URL: %s\n\n%s: %s\n\nStack trace:\n%s",
                    $url,
                    $exception::class,
                    $exception->getMessage(),
                    $exception->getTraceAsString(),
                )),
            'Failed to send error alert email',
        );
    }

    private function sendSilently(Email $email, string $context): void
    {
        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->warning($context . ': ' . $e->getMessage());
        }
    }

    private function sendOrFail(Email $email, string $context): void
    {
        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->error($context . ': ' . $e->getMessage());
            throw new MailDeliveryException('Email delivery failed', 0, $e);
        }
    }
}
