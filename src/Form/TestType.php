<?php

namespace App\Form;

use App\Entity\Test;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class TestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom_test', TextType::class, ['label' => 'Nom du test'])
            ->add('categorie', ChoiceType::class, [
                'choices'  => [
                    'Dépression' => 'Depression',
                    'Anxiété' => 'Anxiete',
                    'ADHD' => 'ADHD',
                    'Autre' => 'Autre',
                ],
            ])
            ->add('date_test', DateType::class, [
                'widget' => 'single_text',
            ])
            ->add('duree', NumberType::class, [
                'label' => 'Durée (en minutes)',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Test::class,
        ]);
    }
}