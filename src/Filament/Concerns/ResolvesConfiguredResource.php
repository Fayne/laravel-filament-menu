<?php

namespace Fayne\Menu\Filament\Concerns;

trait ResolvesConfiguredResource
{
    public static function getResource(): string
    {
        return static::$resource::configuredResource();
    }
}
