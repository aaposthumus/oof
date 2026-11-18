<?php

namespace App\Models;

use App\Enums\Breakthrough;
use App\Enums\NicknameStatus;
use Carbon\CarbonInterface;
use Database\Factories\DriverNicknameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/**
 * One stage of a driver's nickname, with its motto. The single place the
 * text lives: awards, edition subtitles and evolutions refer to this row or
 * to the driver.
 *
 * A correction (fixing or renaming) edits this row in place: no new stage,
 * no event. An evolution is a different row: a planned next stage
 * (evolves_from) that NicknameEvolution makes active on an off-season date,
 * retiring this one the day before. Draft rows are not final yet.
 *
 * @property list<string>|null $evolution_trigger Breakthrough values that may propose this stage; any when null
 */
#[Fillable(['driver_id', 'nickname', 'motto', 'stage', 'evolves_from_id', 'status', 'is_draft', 'active_from', 'active_to', 'evolution_trigger', 'note'])]
class DriverNickname extends Model
{
    /** @use HasFactory<DriverNicknameFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stage' => 'integer',
            'status' => NicknameStatus::class,
            'is_draft' => 'boolean',
            'active_from' => 'date',
            'active_to' => 'date',
            'evolution_trigger' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $nickname): void {
            if ($nickname->status === NicknameStatus::Active && self::query()
                ->where('driver_id', $nickname->driver_id)
                ->where('status', NicknameStatus::Active)
                ->when($nickname->exists, fn (Builder $query) => $query->whereKeyNot($nickname->id))
                ->exists()) {
                throw new LogicException('This driver already has an active nickname: correct that one, or plan an evolution.');
            }
        });
    }

    /**
     * Whether a breakthrough of this kind may propose this stage.
     */
    public function evolvesOn(Breakthrough $breakthrough): bool
    {
        return $this->evolution_trigger === null || in_array($breakthrough->value, $this->evolution_trigger, true);
    }

    /**
     * Whether it was the driver's nickname on that date.
     */
    public function wasInUseOn(CarbonInterface $date): bool
    {
        return $this->status !== NicknameStatus::Planned
            && ($this->active_from === null || $this->active_from->lte($date))
            && ($this->active_to === null || $this->active_to->gte($date));
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopePlanned(Builder $query): void
    {
        $query->where('status', NicknameStatus::Planned);
    }

    /**
     * @return BelongsTo<Driver, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /**
     * @return BelongsTo<DriverNickname, $this>
     */
    public function evolvesFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'evolves_from_id');
    }

    /**
     * @return HasOne<DriverNickname, $this>
     */
    public function nextStage(): HasOne
    {
        return $this->hasOne(self::class, 'evolves_from_id');
    }

    /**
     * The evolution into this stage, once proposed.
     *
     * @return HasOne<NicknameEvolution, $this>
     */
    public function evolution(): HasOne
    {
        return $this->hasOne(NicknameEvolution::class, 'to_nickname_id');
    }
}
