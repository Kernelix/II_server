<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class AdminLogoutSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLogout(LogoutEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();
        if ($request->isXmlHttpRequest() || str_contains($request->headers->get('Accept'), 'application/json')) {
            $response->setContent(json_encode(['status' => 'success']));
            $response->headers->set('Content-Type', 'application/json');
        }
    }
}
