<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

class DefaultController extends AbstractController
{
    /**
     * Root route
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        return $this->json(['message' => 'Welcome to the forum!']);
    }
}
