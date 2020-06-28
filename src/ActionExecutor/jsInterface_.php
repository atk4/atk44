<?php

declare(strict_types=1);

namespace atk4\ui\ActionExecutor;

if (!class_exists(\SebastianBergmann\CodeCoverage\CodeCoverage::class, false)) {
    'trigger_error'('Use atk4\ui\UserAction\JsExecutorInterface instead', E_USER_DEPRECATED);
}

/**
 * @deprecated will be removed in dec-2020
 */
interface jsInterface_ extends \atk4\ui\UserAction\JsExecutorInterface
{
}
