<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\ValidationException;
use App\Service\UserService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/user')]
class UserController extends AppController
{
    public function __construct(private readonly UserService $userService) {}

    #[Route('/me', name: 'user_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getCurrentUser();

        return $this->json([
            'id'    => $user->getId(),
            'name'  => $user->getName(),
            'email' => $user->getEmail(),
        ]);
    }

    #[Route('/profile', name: 'user_update_profile', methods: ['PATCH'])]
    public function updateProfile(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Невалидный JSON'], 400);
        }

        try {
            $this->userService->updateName($this->getCurrentUser(), $data['name'] ?? '');
        } catch (ValidationException $e) {
            return $this->json(['errors' => $e->getErrors()], 422);
        }

        return $this->json(['name' => $this->getCurrentUser()->getName()]);
    }

    #[Route('/email', name: 'user_update_email', methods: ['PATCH'])]
    public function updateEmail(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Невалидный JSON'], 400);
        }

        try {
            $this->userService->updateEmail(
                $this->getCurrentUser(),
                $data['email']            ?? '',
                $data['current_password'] ?? '',
            );
        } catch (ValidationException $e) {
            return $this->json(['errors' => $e->getErrors()], 422);
        }

        return $this->json(['email' => $this->getCurrentUser()->getEmail()]);
    }

    #[Route('/password', name: 'user_update_password', methods: ['PATCH'])]
    public function updatePassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Невалидный JSON'], 400);
        }

        try {
            $this->userService->updatePassword(
                $this->getCurrentUser(),
                $data['current_password'] ?? '',
                $data['new_password']     ?? '',
            );
        } catch (ValidationException $e) {
            return $this->json(['errors' => $e->getErrors()], 422);
        }

        return $this->json(null, 204);
    }
}
