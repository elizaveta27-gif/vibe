<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\ValidationException;
use App\Service\AuthService;
use App\Service\PasswordResetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
class AuthController extends AbstractController
{
    public function __construct(
        private readonly AuthService           $authService,
        private readonly PasswordResetService  $passwordResetService,
        private readonly RateLimiterFactory    $forgotPasswordLimiter,
        #[Autowire(env: 'bool:COOKIE_SECURE')] private readonly bool $secureCookies = false,
    ) {}

    // Маршрут нужен, но тело никогда не выполняется — LexikJWT перехватывает раньше
    #[Route('/login', name: 'auth_login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        throw new \LogicException('Handled by LexikJWT firewall.');
    }

    #[Route('/register', name: 'auth_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json(['error' => 'Невалидный JSON'], 400);
        }

        $email = $data['email'] ?? null;
        $name = $data['name'] ?? null;
        $password = $data['password'] ?? null;
        if (!is_string($email) || !is_string($name) || !is_string($password)) {
            return $this->json(['errors' => ['request' => 'Поля email, name и password должны быть строками']], 422);
        }

        try {
            $result = $this->authService->register(
                $email,
                $name,
                $password,
            );
        } catch (ValidationException $e) {
            return $this->json(['errors' => $e->getErrors()], 422);
        }

        $response = $this->json(['user' => $result['user']], 201);
        $response->headers->setCookie($this->createAuthCookie($result['token']));

        return $response;
    }

    #[Route('/logout', name: 'auth_logout', methods: ['POST'])]
    public function logout(): JsonResponse
    {
        $response = $this->json(null, Response::HTTP_NO_CONTENT);
        $response->headers->clearCookie('BEARER', '/', null, $this->secureCookies, true, Cookie::SAMESITE_LAX);

        return $response;
    }

    #[Route('/forgot-password', name: 'auth_forgot_password', methods: ['POST'])]
    public function forgotPassword(Request $request): JsonResponse
    {
        $limiter = $this->forgotPasswordLimiter->create($request->getClientIp());

        if (!$limiter->consume()->isAccepted()) {
            return $this->json(['error' => 'Слишком много запросов. Попробуйте позже.'], 429);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Невалидный JSON'], 400);
        }

        $email = trim(is_string($data['email'] ?? null) ? $data['email'] : '');

        try {
            $this->passwordResetService->requestReset($email);
        } catch (ValidationException $e) {
            return $this->json(['errors' => $e->getErrors()], 422);
        }

        // Всегда 204 — не раскрываем, существует ли email
        return $this->json(null, 204);
    }

    #[Route('/reset-password', name: 'auth_reset_password', methods: ['POST'])]
    public function resetPassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Невалидный JSON'], 400);
        }

        $token = trim(is_string($data['token'] ?? null) ? $data['token'] : '');
        $password = is_string($data['password'] ?? null) ? $data['password'] : '';

        try {
            $this->passwordResetService->reset($token, $password);
        } catch (ValidationException $e) {
            return $this->json(['errors' => $e->getErrors()], 422);
        }

        return $this->json(null, 204);
    }

    private function createAuthCookie(string $token): Cookie
    {
        return Cookie::create('BEARER')
            ->withValue($token)
            ->withExpires(time() + 3600)
            ->withPath('/')
            ->withSecure($this->secureCookies)
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_LAX);
    }
}
