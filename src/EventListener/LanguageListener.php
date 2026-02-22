<?php

namespace App\EventListener;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Language Listener
 * 
 * Sets the application language based on:
 * 1. User's language preference (from database)
 * 2. Accept-Language header
 * 3. Default to English
 */
class LanguageListener implements EventSubscriberInterface
{
    public function __construct(private Security $security) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $language = 'en'; // Default language

        // Priority 1: Check if user is authenticated and has language preference
        $user = $this->security->getUser();
        if ($user instanceof User) {
            $language = $user->getLanguage();
        } else {
            // Priority 2: Check Accept-Language header
            $acceptLanguage = $request->headers->get('Accept-Language');
            if ($acceptLanguage) {
                // Parse Accept-Language header and use first supported language
                $supported = ['en', 'fr', 'ar', 'es'];
                $languages = array_map(
                    function ($lang) {
                        return substr(trim($lang), 0, 2);
                    },
                    explode(',', $acceptLanguage)
                );

                foreach ($languages as $lang) {
                    if (in_array($lang, $supported)) {
                        $language = $lang;
                        break;
                    }
                }
            }
        }

        // Store language in request attributes for access in controllers
        $request->attributes->set('_language', $language);
    }
}
