<?php

declare(strict_types=1);

namespace App\Tests\App\LiveComponent;

use App\Entity\User;
use App\Tests\Factory\DeityFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\SignsIn;
use App\Twig\Components\DeityForm;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class DeityFormTest extends KernelTestCase
{
    use Factories;
    use InteractsWithLiveComponents;
    use ResetDatabase;
    use SignsIn;

    private User $collector;

    #[\Override]
    protected function setUp(): void
    {
        $this->collector = UserFactory::createOne();
        $this->signIn($this->collector);
    }

    public function test_the_form_offers_every_field(): void
    {
        $rendered = $this->form(DeityFactory::createOne())->render()->toString();

        foreach (['name', 'description', 'photographFile', 'removePhotograph'] as $field) {
            $this->assertStringContainsString('deity['.$field.']', $rendered, $field.' is missing from the form.');
        }
    }

    public function test_a_deity_known_by_one_name_still_offers_a_field_for_another(): void
    {
        $rendered = $this->form(DeityFactory::createOne())->render()->toString();

        $this->assertStringContainsString('name="deity[additionalNames][0]"', $rendered, 'There was nowhere to record a first additional name.');
        $this->assertSame(1, substr_count($rendered, 'data-live-action-param="addCollectionItem"'), 'The field was rendered twice.');
        $this->assertStringContainsString('Add a name', $rendered, 'The form offers no way to add a name.');
    }

    public function test_the_form_starts_from_the_stored_names(): void
    {
        $deity = DeityFactory::createOne(['name' => 'Inari', 'additionalNames' => ['稲荷大明神', 'Oinari-san']]);

        $rendered = $this->form($deity)->render()->toString();

        $this->assertStringContainsString('name="deity[additionalNames][0]"', $rendered);
        $this->assertStringContainsString('name="deity[additionalNames][1]"', $rendered, 'The second stored name was not shown.');
        $this->assertStringContainsString('稲荷大明神', $rendered);
        $this->assertStringContainsString('Oinari-san', $rendered);
    }

    public function test_a_row_can_be_added_and_taken_back(): void
    {
        $component = $this->form(DeityFactory::createOne())->call('addCollectionItem', ['name' => 'deity[additionalNames]']);

        $this->assertStringContainsString('name="deity[additionalNames][1]"', $component->render()->toString(), 'A second row was never offered.');

        $component->call('removeCollectionItem', ['name' => 'deity[additionalNames]', 'index' => 1]);

        $this->assertStringNotContainsString('name="deity[additionalNames][1]"', $component->render()->toString(), 'The row taken back stayed.');
    }

    private function form(object $deity): TestLiveComponent
    {
        return $this->createLiveComponent(DeityForm::class, ['deity' => $deity])->actingAs($this->collector);
    }
}
