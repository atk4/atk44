<?php

declare(strict_types=1);

namespace atk4\ui\FormLayout;

if (!class_exists(\SebastianBergmann\CodeCoverage\CodeCoverage::class, false)) {
    'trigger_error'('Use atk4\ui\Form\Layout\Custom instead', E_USER_DEPRECATED);
}

/**
 * @deprecated will be removed jun-2021
 */
class Custom extends \atk4\ui\Form\Layout\Custom
{
}
