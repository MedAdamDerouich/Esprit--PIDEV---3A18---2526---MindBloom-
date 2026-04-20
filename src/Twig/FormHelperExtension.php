<?php

namespace App\Twig;

use Symfony\Component\Form\FormView;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class FormHelperExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('field_name', [$this, 'getFieldName']),
            new TwigFunction('field_value', [$this, 'getFieldValue']),
        ];
    }

    public function getFieldName(FormView $form): string
    {
        return $form->vars['full_name'];
    }

    public function getFieldValue(FormView $form): mixed
    {
        return $form->vars['value'];
    }
}
