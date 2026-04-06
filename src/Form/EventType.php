<?php

namespace App\Form;

use App\Entity\Event;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

class EventType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre *',
                'attr' => [
                    'placeholder' => 'Titre de l\'événement',
                    'class' => 'form-control'
                ],
                'required' => true
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => [
                    'placeholder' => 'Description détaillée de l\'événement',
                    'class' => 'form-control',
                    'rows' => 4
                ],
                'required' => false
            ])
            ->add('dateDebut', DateTimeType::class, [
                'label' => 'Date de début *',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control'
                ],
                'required' => true
            ])
            ->add('dateFin', DateTimeType::class, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control'
                ],
                'required' => false
            ])
            ->add('lieu', TextType::class, [
                'label' => 'Lieu *',
                'attr' => [
                    'placeholder' => 'Lieu de l\'événement',
                    'class' => 'form-control'
                ],
                'required' => false
            ])
            ->add('capaciteMax', IntegerType::class, [
                'label' => 'Capacité maximale',
                'attr' => [
                    'placeholder' => 'Ex : 50',
                    'class' => 'form-control',
                    'min' => 1
                ],
                'required' => false,
                'constraints' => [
                    new Positive(message: 'La capacité doit être un nombre positif')
                ]
            ])
            ->add('organisateur', TextType::class, [
                'label' => 'Organisateur *',
                'attr' => [
                    'placeholder' => 'Nom de l\'organisateur',
                    'class' => 'form-control'
                ],
                'required' => false
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut *',
                'choices' => [
                    'Actif' => Event::STATUT_ACTIF,
                    'Annulé' => Event::STATUT_ANNULE,
                    'Terminé' => Event::STATUT_TERMINE
                ],
                'attr' => [
                    'class' => 'form-select'
                ],
                'required' => true
            ])
            ->add('prix', NumberType::class, [
                'label' => 'Prix (DT)',
                'attr' => [
                    'placeholder' => '0.00 (laisser vide = gratuit)',
                    'class' => 'form-control',
                    'step' => '0.01',
                    'min' => '0'
                ],
                'required' => false,
                'scale' => 2,
                'constraints' => [
                    new PositiveOrZero(message: 'Le prix doit être positif ou zéro')
                ]
            ])
            ->add('photoFile', FileType::class, [
                'label' => 'Photo de l\'événement',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => 'image/*'
                ],
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/jpg',
                            'image/png',
                            'image/gif',
                            'image/webp'
                        ],
                        'mimeTypesMessage' => 'Veuillez télécharger une image valide (JPG, PNG, GIF, WebP)',
                        'maxSizeMessage' => 'L\'image ne doit pas dépasser 5 Mo'
                    ])
                ],
                'help' => 'Formats acceptés: JPG, PNG, GIF, WebP. Taille max: 5 Mo'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Event::class,
        ]);
    }
}
