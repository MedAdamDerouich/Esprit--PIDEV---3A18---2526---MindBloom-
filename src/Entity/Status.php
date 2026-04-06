<?php


namespace App\Entity;


enum Status: string
{
    case CONFIRME = 'Confirmé';
    case ANNULE = 'Annulé';
}