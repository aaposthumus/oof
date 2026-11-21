<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\NationalityCountries\Pages\CreateNationalityCountry;
use App\Filament\Resources\NationalityCountries\Pages\EditNationalityCountry;
use App\Filament\Resources\NationalityCountries\Pages\ListNationalityCountries;
use App\Models\NationalityCountry;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NationalitiesTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['admin.emails' => ['admin@example.com']]);

        $this->actingAs(User::factory()->create(['email' => 'admin@example.com']));
        Filament::setCurrentPanel('nimda');
    }

    public function test_lists_the_nationalities(): void
    {
        Livewire::test(ListNationalityCountries::class)
            ->searchTable('Monegasque')
            ->assertCanSeeTableRecords(NationalityCountry::query()->where('nationality', 'Monegasque')->get());
    }

    public function test_a_nationality_is_added_and_edited(): void
    {
        Livewire::test(CreateNationalityCountry::class)
            ->fillForm(['nationality' => 'Estonian', 'country' => 'Estonia'])
            ->call('create')
            ->assertHasNoFormErrors();

        $estonian = NationalityCountry::firstWhere('nationality', 'Estonian');
        $this->assertSame('Estonia', $estonian->country);

        Livewire::test(EditNationalityCountry::class, ['record' => $estonian->getRouteKey()])
            ->fillForm(['country' => 'EST'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('EST', $estonian->fresh()->country);
    }

    public function test_a_nationality_is_unique(): void
    {
        Livewire::test(CreateNationalityCountry::class)
            ->fillForm(['nationality' => 'British', 'country' => 'UK'])
            ->call('create')
            ->assertHasFormErrors(['nationality' => 'unique']);
    }
}
