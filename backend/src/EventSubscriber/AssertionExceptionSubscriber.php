<?php

namespace App\EventSubscriber;

use Assert\AssertionFailedException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class AssertionExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'handleException',
        ];
    }

    public function handleException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if ($exception instanceof AssertionFailedException) {
            $response = new JsonResponse(
                ['error' => $exception->getMessage()],
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY
            );
            $event->setResponse($response);
        }
    }
}
