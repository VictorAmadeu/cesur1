<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\ErrorHandler\Exception\FlattenException;

class ErrorController extends AbstractController
{
    public function show(Request $request, FlattenException $exception): Response
    {
        $statusCode = $exception->getStatusCode();
        $errorMessage = $exception->getMessage();

        return $this->render('admin/error.html.twig', [
            'status_code' => $statusCode,
            'message' => $errorMessage ?: 'Ha ocurrido un error inesperado.',
        ]);
    }
}