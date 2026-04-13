<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\FormLoginAuthenticator;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(
        AuthenticationUtils $authenticationUtils,
        Request $request,
        #[Autowire('%env(RECAPTCHA_SITE_KEY)%')] string $recaptchaSiteKey,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $registerForm = $this->createForm(RegistrationFormType::class, new User());

        return $this->render('security/login.html.twig', [
            'last_username'      => $authenticationUtils->getLastUsername(),
            'error'              => $authenticationUtils->getLastAuthenticationError(),
            'recaptcha_site_key' => $recaptchaSiteKey,
            'registrationForm'   => $registerForm->createView(),
            'mode'               => $request->query->get('mode', 'login'),
        ]);
    }

    #[Route('/connect/google', name: 'connect_google_start')]
    public function connectGoogle(
        ClientRegistry $clientRegistry,
        UrlGeneratorInterface $urlGenerator,
    ): Response {
        $redirectUri = $urlGenerator->generate(
            'connect_google_check',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        return $clientRegistry
            ->getClient('google')
            ->redirect(['email', 'profile', 'openid'], ['redirect_uri' => $redirectUri]);
    }

    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function connectGoogleCheck(): never
    {
        // This route is intercepted entirely by GoogleAuthenticator::supports().
        // The controller body is never reached during a normal OAuth callback.
        throw new \LogicException('This line should not be reached.');
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        // Symfony handles this automatically
    }

    #[Route('/dev/login/{role}', name: 'dev_login')]
    public function devLogin(
        string $role,
        Request $request,
        UserRepository $userRepository,
        UserAuthenticatorInterface $userAuthenticator,
        FormLoginAuthenticator $formLoginAuthenticator,
    ): Response {
        if ($this->getParameter('kernel.environment') !== 'dev') {
            throw $this->createNotFoundException();
        }

        $map = [
            'admin'   => 'admin123',
            'patient' => 'patient123',
            'psycho'  => 'psycho123',
        ];

        $username = $map[$role] ?? null;
        if (!$username) {
            throw $this->createNotFoundException();
        }

        $user = $userRepository->findOneBy(['username' => $username]);
        if (!$user) {
            throw $this->createNotFoundException("User $username not found.");
        }

        return $userAuthenticator->authenticateUser(
            $user,
            $formLoginAuthenticator,
            $request,
        ) ?? $this->redirectToRoute('app_dashboard');
    }
}
