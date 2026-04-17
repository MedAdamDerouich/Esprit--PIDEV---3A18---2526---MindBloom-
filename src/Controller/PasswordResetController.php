<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\PasswordResetEmailService;
use App\Service\PasswordResetService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Flux en 3 étapes — port de ForgotPasswordController.java (JavaFX) vers HTTP/JSON.
 */
#[Route('/forgot-password')]
class PasswordResetController extends AbstractController
{
    #[Route('/send-code', name: 'app_pwd_send_code', methods: ['POST'])]
    public function sendCode(
        Request $request,
        UserRepository $users,
        PasswordResetService $reset,
        PasswordResetEmailService $mailer,
    ): JsonResponse {
        $email = strtolower(trim((string) $request->request->get('email', '')));

        if ($email === '') {
            return $this->json(['ok' => false, 'message' => 'Veuillez entrer votre adresse email.'], 400);
        }
        if (!preg_match('/^[\w.+-]+@[\w.-]+\.[a-zA-Z]{2,}$/', $email)) {
            return $this->json(['ok' => false, 'message' => 'Adresse email invalide.'], 400);
        }

        $user = $users->findOneBy(['email' => $email]);
        if (!$user) {
            return $this->json(['ok' => false, 'message' => 'Aucun compte trouvé avec cet email.'], 404);
        }

        try {
            $code = $reset->generateCode($email);
            $mailer->sendPasswordResetEmail($email, $user->getFullName() ?? '', $code);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'message' => 'Erreur lors de l\'envoi : ' . $e->getMessage()], 500);
        }

        return $this->json([
            'ok'     => true,
            'masked' => $this->maskEmail($email),
        ]);
    }

    #[Route('/verify-code', name: 'app_pwd_verify_code', methods: ['POST'])]
    public function verifyCode(
        Request $request,
        PasswordResetService $reset,
    ): JsonResponse {
        $email = strtolower(trim((string) $request->request->get('email', '')));
        $code  = trim((string) $request->request->get('code', ''));

        if (!preg_match('/^\d{6}$/', $code)) {
            return $this->json(['ok' => false, 'message' => 'Le code doit contenir exactement 6 chiffres.'], 400);
        }
        if (!$reset->validateCode($email, $code)) {
            return $this->json(['ok' => false, 'message' => 'Code incorrect ou expiré. Renvoyez un nouveau code.'], 400);
        }

        return $this->json(['ok' => true]);
    }

    #[Route('/reset', name: 'app_pwd_reset', methods: ['POST'])]
    public function reset(
        Request $request,
        UserRepository $users,
        PasswordResetService $reset,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
    ): JsonResponse {
        $email    = strtolower(trim((string) $request->request->get('email', '')));
        $code     = trim((string) $request->request->get('code', ''));
        $password = (string) $request->request->get('password', '');
        $confirm  = (string) $request->request->get('confirm', '');

        if ($password === '' || $confirm === '') {
            return $this->json(['ok' => false, 'message' => 'Veuillez remplir les deux champs.'], 400);
        }
        if (strlen($password) < 6) {
            return $this->json(['ok' => false, 'message' => 'Le mot de passe doit contenir au moins 6 caractères.'], 400);
        }
        if ($password !== $confirm) {
            return $this->json(['ok' => false, 'message' => 'Les mots de passe ne correspondent pas.'], 400);
        }
        if (!$reset->validateCode($email, $code)) {
            return $this->json(['ok' => false, 'message' => 'Code incorrect ou expiré. Recommencez la procédure.'], 400);
        }

        $user = $users->findOneBy(['email' => $email]);
        if (!$user) {
            return $this->json(['ok' => false, 'message' => 'Utilisateur introuvable.'], 404);
        }

        $user->setPassword($hasher->hashPassword($user, $password));
        $em->flush();
        $reset->consumeCode($email);

        return $this->json(['ok' => true, 'message' => 'Mot de passe réinitialisé avec succès.']);
    }

    private function maskEmail(string $email): string
    {
        $at = strpos($email, '@');
        if ($at === false || $at <= 2) return $email;
        return $email[0] . '***' . substr($email, $at - 1);
    }
}
