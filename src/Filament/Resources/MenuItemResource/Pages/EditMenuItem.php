<?php

namespace Fayne\Menu\Filament\Resources\MenuItemResource\Pages;

use Fayne\CMS\Filament\Operations\EditRecord;
use Fayne\Menu\Filament\Concerns\ResolvesConfiguredResource;
use Fayne\Menu\Filament\Resources\MenuItemResource;
use Fayne\Menu\Services\MenuItemService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditMenuItem extends EditRecord
{
    use ResolvesConfiguredResource;

    protected static string $resource = MenuItemResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(MenuItemService::class)->update($record, $data);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn ($messages, $field) => ['data.'.$field => $messages])->all());
        }
    }
}
