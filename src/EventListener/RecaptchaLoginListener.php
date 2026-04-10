<?php

namespace App\EventListener;

use App\Service\RecaptchaService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

/**
 * Intercepts POST /login at priority 9 (before form_login at priority 8)
 * to verify the reCAPTCHA v3 token before credentials are processed.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 9)]
class RecaptchaLoginListener
{
    public function __construct(
        private RecaptchaService $recaptchaService,
        private RouterInterface $router,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest()) {
            return;
        }

        // Only intercept the login form submission
        if ($request->getPathInfo() !== '/login' || $request->getMethod() !== 'POST') {
            return;
        }

        $token = (string) $request->request->get('_recaptcha_token', '');

        if ($this->recaptchaService->isTokenValid($token)) {
            return; // Score acceptable — let form_login handle credentials
        }

        // Score too low: store the error the same way form_login does, then redirect
        $exception = new CustomUserMessageAuthenticationException(
            'Vérification de sécurité échouée. Veuillez réessayer.'
        );

        $request->getSession()->set(
            SecurityRequestAttributes::AUTHENTICATION_ERROR,
            $exception
        );

        $event->setResponse(new RedirectResponse($this->router->generate('app_login')));
    }
}
