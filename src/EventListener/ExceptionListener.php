<?php

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ExceptionListener implements EventSubscriberInterface
{
    public function __construct(private LoggerInterface $logger) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        // LOG THE REAL ERROR
        $this->logger->error('REAL EXCEPTION: ' . $exception->getMessage(), [
            'exception' => $exception,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);

        if (!str_starts_with($request->getPathInfo(), '/api/') || str_starts_with($request->getPathInfo(), '/api/doc')) {
            return;
        }

        $response = $this->createJsonResponse($exception);
        $event->setResponse($response);
    }
    

    private function createJsonResponse(\Throwable $exception): JsonResponse
    {
        $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
        $errorCode = 'INTERNAL_ERROR';
        $message = 'An unexpected error occurred';

        if ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
            $message = $exception->getMessage() ?: 'HTTP Error';
            $errorCode = match($statusCode) {
                400 => 'BAD_REQUEST',
                401 => 'UNAUTHORIZED',
                403 => 'FORBIDDEN',
                404 => 'NOT_FOUND',
                405 => 'METHOD_NOT_ALLOWED',
                default => 'HTTP_ERROR',
            };
        } elseif ($exception instanceof AuthenticationException) {
            $statusCode = Response::HTTP_UNAUTHORIZED;
            $errorCode = 'AUTHENTICATION_FAILED';
            $message = 'Authentication failed: Invalid credentials or token';
        } elseif ($exception instanceof AccessDeniedException) {
            $statusCode = Response::HTTP_FORBIDDEN;
            $errorCode = 'ACCESS_DENIED';
            $message = $exception->getMessage() ?: 'You do not have access to this resource';
        } elseif ($exception instanceof NotFoundHttpException) {
            $statusCode = Response::HTTP_NOT_FOUND;
            $errorCode = 'RESOURCE_NOT_FOUND';
            $message = 'The requested resource was not found';
        }

        // In production, don't expose detailed error messages for security reasons
        $detailedMessage = $exception->getMessage();
        if (!in_array($statusCode, [400, 401, 403, 404])) {
            $detailedMessage = null;
        }

        return new JsonResponse([
            'success' => false,
            'error' => $message,
            'errorCode' => $errorCode,
            'details' => $detailedMessage,
            'timestamp' => date('Y-m-d H:i:s'),
        ], $statusCode);
    }
}
