<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\ValidationException;

class SupportService
{
    public function __construct(
        private readonly EmailService $emailService,
    ) {}

    /**
     * @throws ValidationException
     */
    public function submit(string $email, string $message, array $contactMethods, string $contactHandle = ''): void
    {
        $errors = [];

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Введите корректный email';
        }

        if (!$message || mb_strlen($message) < 10) {
            $errors['message'] = 'Опишите проблему подробнее (минимум 10 символов)';
        }

        $needsHandle = in_array('Telegram', $contactMethods, true) || in_array('Телефон', $contactMethods, true);
        if ($needsHandle && !$contactHandle) {
            $errors['contact_handle'] = 'Укажите ник в Telegram или номер телефона';
        }

        if ($errors) {
            throw new ValidationException($errors);
        }

        $contactStr = $contactMethods ? implode(', ', array_filter($contactMethods)) : 'не указан';
        if ($contactHandle) {
            $contactStr .= ' (' . $contactHandle . ')';
        }

        $this->emailService->sendSupportTicket($email, $message, $contactStr);
    }
}
