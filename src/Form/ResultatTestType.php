<?php

namespace App\Form;

use App\Entity\ResultatTest;
use App\Entity\Test;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class ResultatTestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('score', NumberType::class, ['label' => 'Score (0-100)'])
            ->add('commentaire', TextType::class, ['required' => false])
            ->add('etat', TextType::class, ['required' => false])
            ->add('test', EntityType::class, [
                'class' => Test::class,
                'choice_label' => 'nom_test',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ResultatTest::class,
        ]);
    }
}