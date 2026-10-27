<?php

namespace App\Filament\Support;

use Closure;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

/**
 * The red box shown first on any screen about a sensitive moment, so the
 * note cannot be missed.
 */
class SensitiveNotice
{
    /**
     * @param  Closure(mixed): bool  $isVisible
     * @param  Closure(mixed): ?string  $note
     */
    public static function make(Closure $isVisible, Closure $note): Section
    {
        return Section::make('Sensitive: read before using')
            ->icon(Heroicon::OutlinedExclamationTriangle)
            ->iconColor('danger')
            ->extraAttributes(['style' => 'background-color: var(--danger-50); --tw-ring-color: var(--danger-600); border-left: 6px solid var(--danger-600);'])
            ->visible($isVisible)
            ->schema([
                TextEntry::make('sensitivity_note')
                    ->hiddenLabel()
                    ->size(TextSize::Large)
                    ->weight(FontWeight::Bold)
                    ->color('danger')
                    ->state($note)
                    ->placeholder('Marked sensitive, but no note was recorded.'),
            ])
            ->columnSpanFull();
    }
}
