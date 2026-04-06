<?php

namespace App\Form;

use App\Entity\Feedback;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

class FeedbackType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('commentaire', TextareaType::class, [
                'label' => 'Votre avis',
                'attr' => ['rows' => 3, 'placeholder' => 'Partagez votre expérience...', 'class' => 'form-control'],
                'constraints' => [
                    new NotBlank(['message' => 'Le commentaire est obligatoire']),
                ],
            ])
            ->add('note', ChoiceType::class, [
                'label' => 'Note',
                'choices' => [
                    '5 - Excellent' => 5,
                    '4 - Très bien' => 4,
                    '3 - Moyen' => 3,
                    '2 - Pas terrible' => 2,
                    '1 - Mauvais' => 1,
                ],
                'expanded' => false,
                'multiple' => false,
                'attr' => ['class' => 'form-select'],
                'constraints' => [
                    new NotBlank(['message' => 'La note est obligatoire']),
                    new Range(['min' => 1, 'max' => 5]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Feedback::class,
        ]);
    }
}
