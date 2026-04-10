<?php

namespace App\Security;

use App\Entity\User;
use App\Entity\Wallet;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client as GuzzleClient;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class GoogleAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private ClientRegistry $clientRegistry,
        private EntityManagerInterface $em,
        private RouterInterface $router,
        private UserRepository $userRepository,
        private string $googleRedirectUri,
    ) {}

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'connect_google_check';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('google');

        // Disable SSL verification for local dev (WampServer has no trusted CA bundle)
        $provider = $client->getOAuth2Provider();
        $provider->setHttpClient(new GuzzleClient(['verify' => false]));

        $accessToken = $this->fetchAccessToken($client, ['redirect_uri' => $this->googleRedirectUri]);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client) {
                /** @var GoogleUser $googleUser */
                $googleUser = $client->fetchUserFromToken($accessToken);

                $googleId = $googleUser->getId();
                $email    = $googleUser->getEmail();

                // 1. Fast path: already linked account
                $user = $this->userRepository->findOneBy(['googleId' => $googleId]);

                // 2. Link existing account by email
                if (!$user) {
                    $user = $this->userRepository->findOneBy(['email' => $email]);
                    if ($user) {
                        $user->setGoogleId($googleId);
                        $this->em->flush();
                    }
                }

                // 3. Create new account
                if (!$user) {
                    $user = $this->createUserFromGoogle($googleUser, $googleId, $email);
                }

                return $user;
            })
        );
    }

    private function createUserFromGoogle(GoogleUser $googleUser, string $googleId, string $email): User
    {
        $rawName  = $googleUser->getName() ?? $email;
        $fullName = $this->sanitizeFullName($rawName);

        $user = new User();
        $user->setGoogleId($googleId);
        $user->setEmail($email);
        $user->setFullName($fullName ?: 'Utilisateur Google');
        $user->setUsername($this->generateUsername($rawName));
        $user->setRole(User::ROLE_PATIENT);
        $user->setStatus(User::STATUS_ACTIVE);
        $user->setIsEmailValid(true);
        $user->setPassword(null);

        $this->em->persist($user);
        $this->em->flush();

        // Mirror RegistrationController: auto-create a wallet for every new user
        $wallet = new Wallet();
        $wallet->setUser($user);
        $this->em->persist($wallet);
        $this->em->flush();

        return $user;
    }

    /**
     * Strip characters outside the fullName validator pattern: letters, spaces, hyphens, apostrophes.
     */
    private function sanitizeFullName(string $name): string
    {
        // Transliterate UTF-8 to ASCII approximation when possible
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name;
        // Keep only letters, spaces, hyphens, apostrophes
        return trim(preg_replace('/[^a-zA-Z\s\-\']/', '', $ascii));
    }

    /**
     * Generate a unique username: lowercase alphanum + underscore, max 20 chars base, suffix if collision.
     */
    private function generateUsername(string $name): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name;
        $base  = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $ascii));
        $base  = trim(preg_replace('/_+/', '_', $base), '_');
        $base  = substr($base ?: 'user', 0, 20);

        $candidate = $base;
        $i = 1;
        while ($this->userRepository->findOneBy(['username' => $candidate])) {
            $candidate = $base . '_' . $i++;
        }

        return $candidate;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return new RedirectResponse($this->router->generate('app_dashboard'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->getSession()->set(
            'google_auth_error',
            'Connexion Google échouée. Veuillez réessayer.'
        );

        return new RedirectResponse($this->router->generate('app_login'));
    }
}
