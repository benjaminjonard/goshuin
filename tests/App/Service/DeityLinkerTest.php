<?php

declare(strict_types=1);

namespace App\Tests\App\Service;

use App\Entity\Deity;
use App\Service\DeityLinker;
use App\Tests\AppTestCase;
use App\Tests\Factory\DeityFactory;
use App\Tests\Factory\UserFactory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class DeityLinkerTest extends AppTestCase
{
    use Factories;
    use ResetDatabase;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->signIn(UserFactory::createOne());
    }

    public function test_a_name_becomes_a_link_to_the_deity(): void
    {
        $inari = DeityFactory::createOne(['romanizedName' => 'Inari']);

        $this->assertSame(
            'Received at a shrine to '.$this->anchor($inari, 'Inari').'.',
            $this->link('Received at a shrine to Inari.'),
        );
    }

    public function test_each_of_the_three_names_reaches_the_deity(): void
    {
        $inari = DeityFactory::createOne([
            'romanizedName' => 'Inari',
            'kanjiName' => '稲荷',
            'kanaName' => 'いなり',
        ]);

        foreach (['Inari', '稲荷', 'いなり'] as $name) {
            $this->assertSame(
                $this->anchor($inari, $name),
                $this->link($name),
                sprintf('The deity is not reached from "%s".', $name),
            );
        }
    }

    public function test_a_deity_named_in_one_script_alone_is_still_reached(): void
    {
        $hachiman = DeityFactory::createOne(['romanizedName' => null, 'kanjiName' => '八幡神', 'kanaName' => null]);

        $this->assertSame($this->anchor($hachiman, '八幡神'), $this->link('八幡神'));
    }

    public function test_any_of_the_names_reaches_the_same_deity(): void
    {
        $inari = DeityFactory::createOne(['romanizedName' => 'Inari', 'additionalNames' => ['稲荷大明神', 'Oinari-san']]);

        $this->assertSame($this->anchor($inari, '稲荷大明神'), $this->link('稲荷大明神'));
        $this->assertSame($this->anchor($inari, 'Oinari-san'), $this->link('Oinari-san'));
    }

    public function test_a_name_is_read_whatever_its_case(): void
    {
        $inari = DeityFactory::createOne(['romanizedName' => 'Inari']);

        $this->assertSame($this->anchor($inari, 'INARI'), $this->link('INARI'));
        $this->assertSame($this->anchor($inari, 'inari'), $this->link('inari'));
    }

    public function test_the_fullest_name_wins_where_two_overlap(): void
    {
        $short = DeityFactory::createOne(['romanizedName' => '稲荷']);
        $full = DeityFactory::createOne(['romanizedName' => '稲荷大明神']);

        $this->assertSame($this->anchor($full, '稲荷大明神'), $this->link('稲荷大明神'));
        $this->assertSame($this->anchor($short, '稲荷'), $this->link('稲荷'));
    }

    public function test_a_latin_name_must_stand_on_its_own(): void
    {
        DeityFactory::createOne(['romanizedName' => 'Inari']);

        $this->assertSame('Inarite', $this->link('Inarite'), 'A name was read inside a longer word.');
        $this->assertSame('Kitsune-Inarism', $this->link('Kitsune-Inarism'));
    }

    public function test_a_kanji_name_is_read_inside_the_words_around_it(): void
    {
        $deity = DeityFactory::createOne(['romanizedName' => '八幡神']);

        $this->assertSame(
            '鎌倉の'.$this->anchor($deity, '八幡神').'を祀る',
            $this->link('鎌倉の八幡神を祀る'),
        );
    }

    public function test_a_deity_is_not_linked_to_the_page_it_is_already_on(): void
    {
        $inari = DeityFactory::createOne(['romanizedName' => 'Inari']);
        $hachiman = DeityFactory::createOne(['romanizedName' => '八幡神']);

        $this->assertSame(
            'Inari and '.$this->anchor($hachiman, '八幡神'),
            $this->link('Inari and 八幡神', $inari),
        );
    }

    public function test_a_deity_past_the_first_page_is_still_linked(): void
    {
        for ($at = 1; $at <= 24; ++$at) {
            DeityFactory::createOne(['romanizedName' => sprintf('Aaa %02d', $at)]);
        }

        $late = DeityFactory::createOne(['romanizedName' => 'Zzz Inari']);

        $this->assertSame(
            $this->anchor($late, 'Zzz Inari'),
            $this->link('Zzz Inari'),
            'A deity past the first page of the list was never linked.',
        );
    }

    public function test_a_note_naming_nobody_is_left_alone(): void
    {
        DeityFactory::createOne(['romanizedName' => 'Inari']);

        $this->assertSame('A quiet morning.', $this->link('A quiet morning.'));
    }

    public function test_a_note_is_escaped_and_its_line_breaks_kept(): void
    {
        $inari = DeityFactory::createOne(['romanizedName' => 'Inari']);

        $this->assertSame(
            '&lt;script&gt;'.$this->anchor($inari, 'Inari').' &amp; foxes&lt;/script&gt;',
            $this->link('<script>Inari & foxes</script>'),
            'A note was allowed to carry markup of its own.',
        );

        $this->assertSame($this->anchor($inari, 'Inari')."<br />\nBelow", $this->link("Inari\nBelow"));
    }

    public function test_an_empty_note_says_nothing(): void
    {
        DeityFactory::createOne(['romanizedName' => 'Inari']);

        $this->assertSame('', $this->link(null));
        $this->assertSame('', $this->link('   '));
    }

    private function link(?string $text, ?Deity $except = null): string
    {
        return static::getContainer()->get(DeityLinker::class)->link($text, $except);
    }

    private function anchor(Deity $deity, string $text): string
    {
        return sprintf(
            '<a href="/deity/%s" class="font-semibold text-accent-text no-underline hover:underline">%s</a>',
            $deity->getId(),
            $text,
        );
    }
}
