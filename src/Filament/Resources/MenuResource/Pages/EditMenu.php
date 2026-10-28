<?php

namespace Fayne\Menu\Filament\Resources\MenuResource\Pages;

use Fayne\CMS\Filament\Operations\EditRecord;
use Fayne\CMS\Support\ContentLocale;
use Fayne\Menu\Filament\Resources\MenuResource;
use Fayne\Menu\Models\Menu;
use Fayne\Menu\Services\MenuTranslationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class EditMenu extends EditRecord
{
    protected static string $resource = MenuResource::class;

    protected function hasUnsavedDataChangesAlert(): bool
    {
        return true;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createTranslation')->label(__('filament-menu::translations.create'))
                ->visible(fn () => Menu::isMultilingualEnabled() && $this->creatableLanguages() !== [] && Gate::allows('create', MenuResource::getModel()) && Gate::allows('view', $this->record))
                ->modalDescription(__('filament-menu::translations.review_links'))
                ->schema([
                    Select::make('locale')->label(__('filament-menu::translations.locale'))->options(fn () => $this->creatableLanguages())->required(),
                    TextInput::make('slug')->required()->maxLength(255),
                ])
                ->action(function (array $data): void {
                    $this->requireSavedChanges();
                    $target = app(MenuTranslationService::class)->create($this->record, $data['locale'], $data['slug']);
                    $this->redirect(MenuResource::getUrl('edit', ['record' => $target]));
                }),
            Action::make('switchLanguage')->label(__('filament-menu::translations.switch'))
                ->visible(fn () => $this->otherLanguageTargets()->isNotEmpty())
                ->modalDescription(fn () => $this->otherLanguageTargets()->count() > 1 ? __('filament-menu::translations.unsaved') : null)
                ->schema(fn (): array => $this->otherLanguageTargets()->count() > 1 ? [
                    Select::make('locale')->label(__('filament-menu::translations.locale'))->required()
                        ->options(fn () => $this->otherLanguageTargets()->mapWithKeys(fn ($target) => [$target->locale => ContentLocale::supported()[$target->locale].' · '.($target->is_active ? __('filament-menu::translations.active') : __('filament-menu::translations.inactive'))])->all()),
                ] : [])
                ->action(function (array $data): void {
                    $this->requireSavedChanges();
                    abort_unless(Menu::isMultilingualEnabled(), 403);
                    $targets = $this->otherLanguageTargets();
                    $target = $targets->count() === 1 ? $targets->first() : $targets->firstWhere('locale', $data['locale'] ?? null);
                    abort_unless($target, 404);
                    Gate::authorize('view', $target);
                    Gate::authorize('update', $target);
                    $this->redirect(MenuResource::getUrl('edit', ['record' => $target]));
                }),
        ];
    }

    private function creatableLanguages(): array
    {
        return array_diff_key(ContentLocale::supported(), array_flip($this->record->getAvailableLocales()));
    }

    private function otherLanguageTargets(): Collection
    {
        if (! Menu::isMultilingualEnabled()) {
            return collect();
        }

        return $this->record->translations()->filter(fn ($target): bool => $target->getKey() !== $this->record->getKey()
            && array_key_exists($target->locale, ContentLocale::supported())
            && Gate::allows('view', $target) && Gate::allows('update', $target));
    }

    private function requireSavedChanges(): void
    {
        $hash = md5((string) str(json_encode($this->data, JSON_UNESCAPED_UNICODE))->replace('\\', ''));
        if ($this->savedDataHash !== $hash) {
            throw ValidationException::withMessages([($this->getMountedActionSchema()?->getStatePath() ?? 'data').'.locale' => __('filament-menu::translations.save_first')]);
        }
    }
}
