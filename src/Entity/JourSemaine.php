<?php


namespace App\Entity;


enum JourSemaine: string
{
    case LUNDI = 'Lundi';
    case MARDI = 'Mardi';
    case MERCREDI = 'Mercredi';
    case JEUDI = 'Jeudi';
    case VENDREDI = 'Vendredi';
    case SAMEDI = 'Samedi';
    case DIMANCHE = 'Dimanche';
}