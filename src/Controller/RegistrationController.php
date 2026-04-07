<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Wallet;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Hash password
            $plainPassword = $form->get('plainPassword')->getData();
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));

            // Set role from hidden input (values: PATIENT or PSYCHOLOGUE)
            $roleInput = $form->get('roles')->getData();
            $allowed   = [User::ROLE_PATIENT, User::ROLE_PSYCHOLOGUE];
            $role      = in_array($roleInput, $allowed) ? $roleInput : User::ROLE_PATIENT;
            $user->setRole($role);

            // Psychologues require admin approval before they can log in
            if ($role === User::ROLE_PSYCHOLOGUE) {
                $user->setStatus(User::STATUS_PENDING);
            }

            $em->persist($user);
            $em->flush();

            // Auto-create wallet
            $wallet = new Wallet();
            $wallet->setUser($user);
            $em->persist($wallet);
            $em->flush();

            if ($role === User::ROLE_PSYCHOLOGUE) {
                $this->addFlash('success', 'Votre compte psychologue a été créé. Il sera activé après validation par l\'administrateur.');
            } else {
                $this->addFlash('success', 'Compte créé avec succès ! Connectez-vous.');
            }
            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }
}
