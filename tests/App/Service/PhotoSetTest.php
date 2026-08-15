<?php

declare(strict_types=1);

namespace App\Tests\App\Service;

use App\Entity\Goshuin;
use App\Entity\Photo;
use App\Enum\PhotoType;
use App\Model\PhotoInstructions;
use App\Repository\PhotoRepository;
use App\Service\PhotoSet;
use App\Tests\AppTestCase;
use App\Tests\Factory\GoshuinFactory;
use App\Tests\Factory\PhotoFactory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class PhotoSetTest extends AppTestCase
{
    use Factories;
    use ResetDatabase;

    private function goshuin(): Goshuin
    {
        $goshuin = GoshuinFactory::createOne();
        $this->signIn($goshuin->getOwner());

        return $goshuin;
    }

    public function test_nothing_submitted_changes_nothing(): void
    {
        $goshuin = $this->goshuin();
        $this->shots($goshuin, ['first', 'second']);

        $this->assertSame(0, $this->apply($goshuin, new PhotoInstructions()));
        $this->assertSame(['first', 'second'], $this->captions($goshuin));
        $this->assertSame([1, 2], $this->spots($goshuin));
    }

    public function test_a_label_is_trimmed_and_an_empty_one_is_stored_as_nothing(): void
    {
        $goshuin = $this->goshuin();
        [$first, $second] = $this->shots($goshuin, ['first', 'second']);

        $this->apply($goshuin, new PhotoInstructions(labels: [
            $first->getId() => '  The torii  ',
            $second->getId() => '   ',
        ]));

        $this->assertSame(['The torii', null], $this->captions($goshuin), 'A label kept its padding or an empty one was stored as a string.');
    }

    public function test_a_removed_photograph_is_gone_and_the_set_closes_the_hole(): void
    {
        $goshuin = $this->goshuin();
        [, $second] = $this->shots($goshuin, ['first', 'second', 'third']);

        $this->apply($goshuin, new PhotoInstructions(removed: [$second->getId()]));

        $this->assertSame(['first', 'third'], $this->captions($goshuin));
        $this->assertSame([1, 2], $this->spots($goshuin), 'Removing one left a hole in the set.');
    }

    public function test_a_submitted_order_is_applied(): void
    {
        $goshuin = $this->goshuin();
        [$first, $second, $third] = $this->shots($goshuin, ['first', 'second', 'third']);

        $this->apply($goshuin, new PhotoInstructions(order: [$third->getId(), $first->getId(), $second->getId()]));

        $this->assertSame(['third', 'first', 'second'], $this->captions($goshuin));
        $this->assertSame([1, 2, 3], $this->spots($goshuin));
    }

    public function test_a_photograph_left_out_of_the_order_keeps_a_place_at_the_end(): void
    {
        $goshuin = $this->goshuin();
        [$first, , $third] = $this->shots($goshuin, ['first', 'second', 'third']);

        $this->apply($goshuin, new PhotoInstructions(order: [$third->getId(), $first->getId()]));

        $this->assertSame(['third', 'first', 'second'], $this->captions($goshuin), 'The one left out was dropped instead of kept at the end.');
        $this->assertSame([1, 2, 3], $this->spots($goshuin));
    }

    public function test_an_id_from_another_set_is_ignored(): void
    {
        $goshuin = $this->goshuin();
        [$first] = $this->shots($goshuin, ['first', 'second']);
        $elsewhere = PhotoFactory::new()->of(GoshuinFactory::createOne())->create(['label' => 'not here']);

        $this->apply($goshuin, new PhotoInstructions(
            order: [$elsewhere->getId(), $first->getId()],
            labels: [$elsewhere->getId() => 'renamed from outside'],
            removed: [$elsewhere->getId()],
        ));

        $this->assertSame(['first', 'second'], $this->captions($goshuin));
        $this->assertSame('not here', $this->photos()->find($elsewhere->getId())->getLabel(), 'A photograph of another goshuin was touched.');
    }

    public function test_a_photograph_of_another_type_is_left_alone(): void
    {
        $goshuin = $this->goshuin();
        $this->shots($goshuin, ['first']);
        $other = PhotoFactory::new()->of($goshuin, PhotoType::Other)->create(['label' => 'Omamori']);

        $this->apply($goshuin, new PhotoInstructions(removed: [$other->getId()]));

        $this->assertSame('Omamori', $this->photos()->find($other->getId())->getLabel(), 'The other set was reached through the wrong type.');
    }

    public function test_added_photographs_land_after_the_ones_already_there(): void
    {
        $goshuin = $this->goshuin();
        $this->shots($goshuin, ['first']);

        $refused = $this->apply($goshuin, new PhotoInstructions(
            added: [$this->createImage(600, 450), $this->createImage(600, 450)],
            addedLabels: ['The torii', '  '],
        ));

        $this->assertSame(0, $refused);
        $this->assertSame(['first', 'The torii', null], $this->captions($goshuin));
        $this->assertSame([1, 2, 3], $this->spots($goshuin));

        $this->clean($goshuin);
    }

    public function test_a_file_that_is_not_a_photograph_is_counted_and_stored_nowhere(): void
    {
        $goshuin = $this->goshuin();
        $this->shots($goshuin, ['first']);

        $refused = $this->apply($goshuin, new PhotoInstructions(added: [$this->createTextFile(), $this->createImage(600, 450)]));

        $this->assertSame(1, $refused, 'A text file was not counted as refused.');
        $this->assertCount(2, $this->captions($goshuin), 'A text file reached the set.');
        $this->assertSame([1, 2], $this->spots($goshuin), 'The refused file took a place in the numbering.');

        $this->clean($goshuin);
    }

    public function test_an_added_photograph_belongs_to_whoever_owns_the_goshuin(): void
    {
        $goshuin = $this->goshuin();

        $this->apply($goshuin, new PhotoInstructions(added: [$this->createImage(600, 450)]));

        $stored = $this->photos()->ofType($goshuin, PhotoType::Location)[0];
        $this->assertSame($goshuin->getOwner()->getId(), $stored->getOwner()->getId(), 'A photograph landed without the owner of its goshuin.');
        $this->assertFileExists($this->uploadsDir().'/'.$stored->getImageCard(), 'The card derivative was not written.');

        $this->clean($goshuin);
    }

    /**
     * @param list<string> $labels
     *
     * @return list<Photo>
     */
    private function shots(Goshuin $goshuin, array $labels): array
    {
        $shots = [];

        foreach (array_values($labels) as $spot => $label) {
            $shots[] = PhotoFactory::new()->of($goshuin, PhotoType::Location, $spot + 1)->create(['label' => $label]);
        }

        return $shots;
    }

    private function apply(Goshuin $goshuin, PhotoInstructions $instructions): int
    {
        return static::getContainer()->get(PhotoSet::class)->apply($goshuin, PhotoType::Location, $instructions);
    }

    /**
     * @return list<?string>
     */
    private function captions(Goshuin $goshuin): array
    {
        return array_map(
            static fn (Photo $photo): ?string => $photo->getLabel(),
            $this->photos()->ofType($goshuin, PhotoType::Location),
        );
    }

    /**
     * @return list<int>
     */
    private function spots(Goshuin $goshuin): array
    {
        return array_map(
            static fn (Photo $photo): int => (int) $photo->getPosition(),
            $this->photos()->ofType($goshuin, PhotoType::Location),
        );
    }

    private function clean(Goshuin $goshuin): void
    {
        foreach ($this->photos()->ofType($goshuin, PhotoType::Location) as $photo) {
            $this->removeUploads($photo->getImage(), $photo->getImageMini(), $photo->getImageCard(), $photo->getImageFull());
        }
    }

    private function photos(): PhotoRepository
    {
        $this->manager();

        return static::getContainer()->get(PhotoRepository::class);
    }
}
