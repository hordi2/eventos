<?php

declare(strict_types=1);

namespace App\Domain\Form\Models;

/**
 * Les 8 opérateurs du CDC (M2.2) : est / n'est pas / contient / ne contient
 * pas / supérieur à / inférieur à / est vide / n'est pas vide.
 */
enum ConditionOperator: string
{
    case Is = 'is';
    case IsNot = 'is_not';
    case Contains = 'contains';
    case DoesNotContain = 'does_not_contain';
    case GreaterThan = 'greater_than';
    case LessThan = 'less_than';
    case IsEmpty = 'is_empty';
    case IsNotEmpty = 'is_not_empty';
}
