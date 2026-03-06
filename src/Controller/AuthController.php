<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api')]
final class AuthController extends AbstractController
{
    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        // Cette méthode n'est jamais exécutée directement.
        // Symfony intercepte la requête via le firewall json_login
        // défini dans security.yaml avant d'arriver ici.
        // Elle existe uniquement pour définir la route.
        throw new \LogicException('This should never be reached.');
    }

    #[Route('/me', name: 'api_me', methods: ['GET'])]
    public function me(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user instanceof User) {
            return $this->json(['message' => 'Not authenticated'], 401);
        }

        return $this->json([
            'status' => 'success',
            'data' => [
                'email' => $user->getUserIdentifier(),
                'roles' => $user->getRoles(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
            ],
        ]);
    }
}
