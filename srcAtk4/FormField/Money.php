<?php

declare(strict_types=1);

namespace Atk4\Ui\FormField;

if (!class_exists(\SebastianBergmann\CodeCoverage\CodeCoverage::class, false)) {
    'trigger_error'('Class atk4\ui\FormField\Money is deprecated. Use atk4\ui\Form\Control\Money instead', E_USER_DEPRECATED);
}

/**
 * @deprecated will be removed dec-2020
 */
class Money extends \atk4\ui\Form\Control\Money
{
}
