<?php

namespace Fayne\Menu\Filament\Resources\MenuItemResource\Pages;

use Fayne\CMS\Filament\Operations\CreateRecord;
use Fayne\Menu\Filament\Resources\MenuItemResource;
use Fayne\Menu\Services\MenuItemService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateMenuItem extends CreateRecord
{
    protected static string $resource = MenuItemResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(MenuItemService::class)->create($data);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn ($messages, $field) => ['data.'.$field => $messages])->all());
        }
    }
}
