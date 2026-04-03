<?php

namespace App\Form;

use App\Entity\Creneau;
use App\Entity\JourSemaine;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

class CreneauType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('jour', EnumType::class, [
                'class' => JourSemaine::class,
                'placeholder' => 'Choisir un jour',
                'constraints' => [new NotBlank(['message' => 'Veuillez choisir un jour.'])],
            ])
            ->add('dateCreneau', DateType::class, [
                'widget' => 'single_text',
                'constraints' => [
                    new NotBlank(['message' => 'La date est obligatoire.']),
                    new GreaterThanOrEqual([
                        'value' => 'today',
                        'message' => 'Vous ne pouvez pas choisir une date passée !'
                    ])
                ],
            ])
            ->add('heureDebut', TimeType::class, [
                'widget' => 'single_text',
                'constraints' => [new NotBlank(['message' => 'L\'heure est obligatoire.'])],
            ])
            ->add('dureeMinutes', IntegerType::class, [
                'constraints' => [new NotBlank(['message' => 'La durée est obligatoire.'])],
            ])
            ->add('disponible', CheckboxType::class, [
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Creneau::class,
        ]);
    }
}
