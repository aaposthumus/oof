<?php

namespace App\Filament\Resources\DriverNicknames\Pages;

use App\Enums\NicknameStatus;
use App\Filament\Resources\DriverNicknames\DriverNicknameResource;
use App\Models\DriverNickname;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * A driver's first nickname (active), or the planned next stage of one.
 */
class CreateDriverNickname extends CreateRecord
{
    protected static string $resource = DriverNicknameResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $parent = isset($data['evolves_from_id']) ? DriverNickname::query()->find($data['evolves_from_id']) : null;

        return [
            ...$data,
            'stage' => $parent === null ? 1 : $parent->stage + 1,
            'status' => $parent === null ? NicknameStatus::Active : NicknameStatus::Planned,
            'evolution_trigger' => $parent === null || ($data['evolution_trigger'] ?? []) === [] ? null : $data['evolution_trigger'],
        ];
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return parent::handleRecordCreation($data);
        } catch (LogicException $exception) {
            Notification::make()->danger()->title('Nickname not added')->body($exception->getMessage())->send();

            throw new Halt;
        }
    }
}
