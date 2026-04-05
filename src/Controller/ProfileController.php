<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ProfileType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $form = $this->createForm(ProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Profil mis à jour avec succès !');
            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/index.html.twig', [
            'profileForm' => $form->createView(),
        ]);
    }

    #[Route('/profile/photo', name: 'app_profile_photo', methods: ['POST'])]
    public function uploadPhoto(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('upload-photo', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('app_profile');
        }

        $file = $request->files->get('photo');
        if (!$file) {
            $this->addFlash('error', 'Aucun fichier reçu.');
            return $this->redirectToRoute('app_profile');
        }

        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowed)) {
            $this->addFlash('error', 'Format non autorisé. Utilisez JPG, PNG ou WebP.');
            return $this->redirectToRoute('app_profile');
        }

        if ($file->getSize() > 3 * 1024 * 1024) {
            $this->addFlash('error', 'Fichier trop volumineux (max 3 Mo).');
            return $this->redirectToRoute('app_profile');
        }

        /** @var User $user */
        $user       = $this->getUser();
        $uploadsDir = $this->getParameter('kernel.project_dir') . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'profiles';

        if ($user->getProfileImage()) {
            $old = $uploadsDir . DIRECTORY_SEPARATOR . $user->getProfileImage();
            if (file_exists($old)) {
                unlink($old);
            }
        }

        $filename = uniqid() . '.' . $file->guessExtension();
        $file->move($uploadsDir, $filename);

        $user->setProfileImage($filename);
        $em->flush();

        $this->addFlash('success', 'Photo de profil mise à jour !');
        return $this->redirectToRoute('app_profile');
    }

    #[Route('/profile/change-password', name: 'app_change_password', methods: ['POST'])]
    public function changePassword(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        if (!$this->isCsrfTokenValid('change-password', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_profile');
        }

        /** @var User $user */
        $user    = $this->getUser();
        $current = $request->request->get('current_password');
        $new     = $request->request->get('new_password');
        $confirm = $request->request->get('confirm_password');

        if (!$passwordHasher->isPasswordValid($user, $current)) {
            $this->addFlash('error', 'Mot de passe actuel incorrect.');
            return $this->redirectToRoute('app_profile');
        }

        if ($new !== $confirm) {
            $this->addFlash('error', 'Les nouveaux mots de passe ne correspondent pas.');
            return $this->redirectToRoute('app_profile');
        }

        if (strlen($new) < 8) {
            $this->addFlash('error', 'Le mot de passe doit contenir au moins 8 caractères.');
            return $this->redirectToRoute('app_profile');
        }

        $user->setPassword($passwordHasher->hashPassword($user, $new));
        $em->flush();

        $this->addFlash('success', 'Mot de passe modifié avec succès !');
        return $this->redirectToRoute('app_profile');
    }
}
