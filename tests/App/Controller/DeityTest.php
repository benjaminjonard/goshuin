<?php

declare(strict_types=1);

namespace App\Tests\App\Controller;

use App\Repository\DeityRepository;
use App\Tests\AppTestCase;
use App\Tests\Factory\DeityFactory;
use App\Tests\Factory\LocationFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class DeityTest extends AppTestCase
{
    use Factories;
    use ResetDatabase;

    public function test_the_deities_are_private(): void
    {
        $deity = DeityFactory::createOne(['owner' => UserFactory::createOne()]);

        foreach (['/deities', '/deity/'.$deity->getSlug(), '/deity/'.$deity->getSlug().'/edit'] as $url) {
            $this->client->request(Request::METHOD_GET, $url);

            $this->assertResponseRedirects();
            $this->client->followRedirect();
            $this->assertRouteSame('app_login', [], sprintf('%s is reachable signed out.', $url));
        }
    }

    public function test_the_index_lists_the_deities_and_opens_each_one(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        DeityFactory::createOne(['name' => '八幡神']);
        $inari = DeityFactory::createOne(['name' => 'Inari']);

        $crawler = $this->client->request(Request::METHOD_GET, '/deities');

        $this->assertResponseIsSuccessful();
        $this->assertCount(2, $crawler->filter('main ul li a'), 'The index does not list every deity.');

        $first = $crawler->filter('main ul li a')->first();
        $this->assertSame('/deity/'.$inari->getSlug(), $first->attr('href'), 'A deity does not open its own page.');
        $this->assertSame('Inari', trim($first->text()));
    }

    public function test_the_index_is_ordered_by_the_name(): void
    {
        $this->client->loginUser(UserFactory::createOne());

        foreach (['Zaō Gongen', 'Amaterasu', 'Inari'] as $name) {
            DeityFactory::createOne(['name' => $name]);
        }

        $names = $this->client->request(Request::METHOD_GET, '/deities')
            ->filter('main ul li a')
            ->each(static fn (Crawler $row): string => trim($row->text()))
        ;

        $this->assertSame(['Amaterasu', 'Inari', 'Zaō Gongen'], $names, 'The index is not ordered by the name.');
    }

    public function test_the_index_pages_through_the_deities(): void
    {
        $this->client->loginUser(UserFactory::createOne());

        foreach (range(1, 26) as $rank) {
            DeityFactory::createOne(['name' => sprintf('Kami %02d', $rank)]);
        }

        $first = $this->client->request(Request::METHOD_GET, '/deities');
        $listed = $first->filter('main ul')->text();

        $this->assertCount(24, $first->filter('main ul li a'), 'The index does not hold a full page of deities.');
        $this->assertStringContainsString('Kami 01', $listed);
        $this->assertStringNotContainsString('Kami 25', $listed, 'The first page reaches past its own end.');

        $second = $this->client->click($first->filter('main nav a')->last()->link());

        $this->assertResponseIsSuccessful();
        $this->assertCount(2, $second->filter('main ul li a'), 'The last page does not hold what the first left.');
        $this->assertStringContainsString('Kami 26', $second->filter('main ul')->text());
    }

    public function test_a_single_page_of_deities_is_not_paged(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        DeityFactory::createOne(['name' => 'Inari']);

        $crawler = $this->client->request(Request::METHOD_GET, '/deities');

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('main nav'), 'A single page still offered a way through.');
    }

    public function test_paging_the_deities_keeps_the_search(): void
    {
        $this->client->loginUser(UserFactory::createOne());

        foreach (range(1, 25) as $rank) {
            DeityFactory::createOne(['name' => sprintf('Kami %02d', $rank)]);
        }

        DeityFactory::createOne(['name' => 'Inari']);

        $first = $this->client->request(Request::METHOD_GET, '/deities?q=kami');
        $this->assertCount(24, $first->filter('main ul li a'), 'The search does not fill a page.');

        $second = $this->client->click($first->filter('main nav a')->last()->link());

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $second->filter('main ul li a'), 'Paging a search dropped it and listed everything again.');
        $this->assertStringNotContainsString('Inari', $second->filter('main ul')->text(), 'Paging a search reached what it excluded.');
    }

    public function test_a_page_of_deities_beyond_the_last_is_not_found(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        DeityFactory::createOne(['name' => 'Inari']);

        $this->client->request(Request::METHOD_GET, '/deities?page=2');

        $this->assertResponseStatusCodeSame(404);
    }

    public function test_the_index_states_that_no_deity_exists_yet(): void
    {
        $this->client->loginUser(UserFactory::createOne());

        $crawler = $this->client->request(Request::METHOD_GET, '/deities');

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('main ul li'), 'The index invented a row.');
        $this->assertStringContainsString('No deity yet', $crawler->filter('main')->text());
        $this->assertGreaterThan(0, $crawler->filter('main a, main button')->count(), 'The empty index offers nothing to do.');
    }

    public function test_the_search_matches_any_part_of_a_name_in_any_script(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        DeityFactory::createOne(['name' => '八幡神']);
        DeityFactory::createOne(['name' => 'Inari']);

        foreach (['nari' => 'Inari', 'INA' => 'Inari', '八幡' => '八幡神'] as $term => $expected) {
            $crawler = $this->client->request(Request::METHOD_GET, '/deities?q='.urlencode((string) $term));
            $rows = $crawler->filter('main ul li a');

            $this->assertCount(1, $rows, sprintf('Searching "%s" does not match exactly one deity.', $term));
            $this->assertStringContainsString($expected, $rows->text(), sprintf('Searching "%s" matched the wrong deity.', $term));
        }
    }

    public function test_the_search_reaches_the_additional_names_too(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        DeityFactory::createOne(['name' => '八幡神']);
        DeityFactory::createOne(['name' => 'Inari', 'additionalNames' => ['稲荷大明神', 'Oinari-san']]);

        foreach (['稲荷' => 'Inari', 'OINARI' => 'Inari', 'nari-sa' => 'Inari'] as $term => $expected) {
            $rows = $this->client->request(Request::METHOD_GET, '/deities?q='.urlencode((string) $term))->filter('main ul li a');

            $this->assertCount(1, $rows, sprintf('Searching "%s" does not reach the deity through its other names.', $term));
            $this->assertStringContainsString($expected, $rows->text(), sprintf('Searching "%s" matched the wrong deity.', $term));
        }
    }

    public function test_the_index_reads_the_additional_names_back(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        DeityFactory::createOne(['name' => 'Inari', 'additionalNames' => ['稲荷大明神', 'Oinari-san']]);

        $row = $this->client->request(Request::METHOD_GET, '/deities')->filter('main ul li a')->text();

        $this->assertStringContainsString('稲荷大明神', $row, 'The index does not say what else the deity is called.');
        $this->assertStringContainsString('Oinari-san', $row);
    }

    public function test_a_search_matching_nothing_says_so_and_leads_back(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        DeityFactory::createOne(['name' => 'Inari']);

        $crawler = $this->client->request(Request::METHOD_GET, '/deities?q=nobody');

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('main ul li'), 'A search matching nothing still listed a deity.');
        $this->assertStringContainsString('No result', $crawler->filter('main')->text());
        $this->assertSame('/deities', $crawler->filter('main p a')->attr('href'), 'A fruitless search is a dead end.');
    }

    public function test_a_deity_page_names_it_and_leads_to_every_location_enshrining_it(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $deity = DeityFactory::createOne(['name' => '八幡神']);
        LocationFactory::createOne(['romanizedName' => 'Usa Jingū', 'deities' => [$deity]]);
        $tsurugaoka = LocationFactory::createOne(['romanizedName' => 'Tsurugaoka Hachimangū', 'deities' => [$deity]]);
        LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera']);

        $crawler = $this->client->request(Request::METHOD_GET, '/deity/'.$deity->getSlug());

        $this->assertResponseIsSuccessful();
        $this->assertSame('八幡神', trim($crawler->filter('h1')->text()), 'The page does not name the deity.');

        $links = $crawler->filter('main ul li a');
        $this->assertCount(2, $links, 'The page does not list exactly the locations enshrining the deity.');
        $this->assertSame('/location/'.$tsurugaoka->getSlug(), $links->first()->attr('href'), 'A location does not open its own page.');
        $this->assertStringContainsString('Usa Jingū', $links->last()->text());
        $this->assertStringNotContainsString('Kiyomizu-dera', $crawler->filter('main')->text(), 'The page claims a location that does not enshrine the deity.');

        $this->client->click($links->first()->link());
        $this->assertResponseIsSuccessful('The link out of the deity page leads nowhere.');
        $this->assertStringContainsString('Tsurugaoka Hachimangū', $this->client->getCrawler()->filter('h1')->text());
    }

    public function test_a_deity_no_location_enshrines_is_simply_shown_alone(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $deity = DeityFactory::createOne(['name' => 'Inari']);

        $crawler = $this->client->request(Request::METHOD_GET, '/deity/'.$deity->getSlug());

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('main ul li'), 'A location was invented.');
        $this->assertStringContainsString('No location yet', $crawler->filter('main')->text());
    }

    public function test_an_unknown_deity_is_not_found(): void
    {
        $this->client->loginUser(UserFactory::createOne());

        $this->client->request(Request::METHOD_GET, '/deity/'.Uuid::v7()->toRfc4122());

        $this->assertResponseStatusCodeSame(404);
    }

    public function test_a_deity_is_renamed_and_the_new_name_shows_everywhere(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $deity = DeityFactory::createOne(['name' => '八幡']);
        $location = LocationFactory::createOne(['romanizedName' => 'Usa Jingū', 'deities' => [$deity]]);

        $this->client->request(Request::METHOD_GET, '/deity/'.$deity->getSlug().'/edit');
        $this->client->submitForm('deity_submit', ['deity[name]' => '八幡神']);

        $this->assertResponseRedirects('/deity/'.$deity->getSlug());
        $this->assertStringContainsString('八幡神', $this->client->followRedirect()->filter('h1')->text());

        $this->manager()->clear();
        $this->assertSame('八幡神', static::getContainer()->get(DeityRepository::class)->find($deity->getId())->getName());

        $page = $this->client->request(Request::METHOD_GET, '/location/'.$location->getSlug())->filter('main')->text();
        $this->assertStringContainsString('八幡神', $page, 'The renamed deity did not reach the location enshrining it.');
    }

    public function test_the_form_refuses_a_deity_with_no_name(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $deity = DeityFactory::createOne(['name' => 'Inari']);

        $this->client->request(Request::METHOD_GET, '/deity/'.$deity->getSlug().'/edit');
        $this->client->submitForm('deity_submit', ['deity[name]' => '']);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame('Inari', $this->stored($deity->getId()), 'A refused submission still wrote the deity away without a name.');
    }

    public function test_the_form_refuses_a_name_another_deity_already_bears(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        DeityFactory::createOne(['name' => '八幡神']);
        $inari = DeityFactory::createOne(['name' => 'Inari']);

        $this->client->request(Request::METHOD_GET, '/deity/'.$inari->getSlug().'/edit');
        $crawler = $this->client->submitForm('deity_submit', ['deity[name]' => '八幡神']);

        $this->assertResponseStatusCodeSame(422);
        $this->assertStringContainsString('already bears that name', $crawler->filter('main')->text(), 'The collision is not stated.');
        $this->assertSame('Inari', $this->stored($inari->getId()), 'The colliding name was stored anyway.');
    }

    public function test_a_deity_records_the_other_names_it_is_known_by(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $deity = DeityFactory::createOne(['name' => 'Inari']);

        $crawler = $this->client->request(Request::METHOD_GET, '/deity/'.$deity->getSlug().'/edit');
        $form = $crawler->selectButton('deity_submit')->form();
        $values = $form->getPhpValues();
        $values['deity']['additionalNames'] = ['稲荷大明神', 'Oinari-san'];

        $this->client->request(Request::METHOD_POST, $form->getUri(), $values);
        $this->assertResponseRedirects('/deity/'.$deity->getSlug());

        $this->manager()->clear();
        $stored = static::getContainer()->get(DeityRepository::class)->find($deity->getId());

        $this->assertSame(['稲荷大明神', 'Oinari-san'], $stored->getAdditionalNames(), 'The additional names were not all kept.');

        $text = $this->client->followRedirect()->filter('main')->text();

        $this->assertStringContainsString('稲荷大明神', $text, 'The page does not read the additional names back.');
        $this->assertStringContainsString('Oinari-san', $text);
    }

    public function test_a_blank_or_repeated_additional_name_is_dropped(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $deity = DeityFactory::createOne(['name' => 'Inari']);

        $crawler = $this->client->request(Request::METHOD_GET, '/deity/'.$deity->getSlug().'/edit');
        $form = $crawler->selectButton('deity_submit')->form();
        $values = $form->getPhpValues();
        $values['deity']['additionalNames'] = ['  稲荷大明神  ', '', 'oinari-san', 'Oinari-San', '   '];

        $this->client->request(Request::METHOD_POST, $form->getUri(), $values);
        $this->assertResponseRedirects();

        $this->manager()->clear();
        $stored = static::getContainer()->get(DeityRepository::class)->find($deity->getId());

        $this->assertSame(['稲荷大明神', 'oinari-san'], $stored->getAdditionalNames(), 'Blank and repeated names were written away.');
    }

    public function test_a_deity_known_by_one_name_alone_says_nothing_more(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $deity = DeityFactory::createOne(['name' => 'Inari']);

        $text = $this->client->request(Request::METHOD_GET, '/deity/'.$deity->getSlug())->filter('main')->text();

        $this->assertStringNotContainsString('Additional names', $text, 'An empty section was held open.');
    }

    public function test_a_deity_named_in_a_description_leads_to_its_page_but_never_to_itself(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $hachiman = DeityFactory::createOne(['name' => '八幡神']);
        $inari = DeityFactory::createOne([
            'name' => 'Inari',
            'description' => 'Inari is often enshrined beside 八幡神.',
        ]);

        $crawler = $this->client->request(Request::METHOD_GET, '/deity/'.$inari->getSlug());

        $this->assertCount(1, $crawler->filter('main a[href="/deity/'.$hachiman->getSlug().'"]'), 'The deity named in the description does not lead to its page.');
        $this->assertCount(0, $crawler->filter('main a[href="/deity/'.$inari->getSlug().'"]'), 'The deity was linked to the page it is already on.');
    }

    public function test_a_deity_carries_a_description_and_a_photograph(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $deity = DeityFactory::createOne(['name' => 'Inari']);

        $this->client->request(Request::METHOD_GET, '/deity/'.$deity->getSlug().'/edit');
        $this->client->submitForm('deity_submit', [
            'deity[name]' => 'Inari',
            'deity[description]' => 'Kami of rice, foxes and prosperity.',
            'deity[photographFile]' => $this->createImage(1600, 1100),
        ]);

        $this->assertResponseRedirects();
        $crawler = $this->client->followRedirect();

        $this->manager()->clear();
        $stored = static::getContainer()->get(DeityRepository::class)->find($deity->getId());

        $this->assertSame('Kami of rice, foxes and prosperity.', $stored->getDescription(), 'The description was not stored.');
        $this->assertNotNull($stored->getPhotographFull(), 'The photograph was not stored.');
        $this->assertStringContainsString('Kami of rice', $crawler->filter('main')->text(), 'The page does not read the description back.');
        $this->assertSame('/uploads/'.$stored->getPhotographFull(), $crawler->filter('main img')->first()->attr('src'), 'The page does not serve the photograph at size.');
        $this->assertSame('/uploads/'.$stored->getPhotograph(), $crawler->filter('main .stage a')->attr('href'), 'The photograph does not open at full size.');

        $row = $this->client->request(Request::METHOD_GET, '/deities')->filter('main ul li a img');
        $this->assertCount(1, $row, 'The index does not show the photograph of the deity.');
        $this->assertSame('/uploads/'.$stored->getPhotographCard(), $row->attr('src'), 'The index does not serve the card photograph.');

        $this->removeUploads($stored->getPhotograph(), $stored->getPhotographMini(), $stored->getPhotographCard(), $stored->getPhotographFull());
    }

    public function test_deleting_a_deity_takes_its_photograph_with_it(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $deity = DeityFactory::createOne(['name' => 'Inari']);
        $id = $deity->getId();
        $slug = $deity->getSlug();

        $this->client->request(Request::METHOD_GET, '/deity/'.$slug.'/edit');
        $this->client->submitForm('deity_submit', [
            'deity[name]' => 'Inari',
            'deity[photographFile]' => $this->createImage(1600, 1100),
        ]);

        $this->manager()->clear();
        $stored = static::getContainer()->get(DeityRepository::class)->find($id);
        $paths = [$stored->getPhotograph(), $stored->getPhotographMini(), $stored->getPhotographCard(), $stored->getPhotographFull()];

        $this->client->request(Request::METHOD_GET, '/deity/'.$slug.'/delete');
        $this->client->submitForm('delete_submit');

        $this->manager()->clear();
        $this->assertNull(static::getContainer()->get(DeityRepository::class)->find($id), 'The deity survived.');
        $this->assertFileDoesNotExist($this->uploadsDir().'/'.$paths[0], 'The stored image outlived its deity.');

        $this->removeUploads(...$paths);
    }

    public function test_the_deity_page_offers_a_collector_nothing_to_change(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $deity = DeityFactory::createOne(['description' => 'Kami of rice.']);

        $crawler = $this->client->request(Request::METHOD_GET, '/deity/'.$deity->getSlug());

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('main form'), 'A collector was offered something to change.');
        $this->assertCount(0, $crawler->filter('input[type="file"]'), 'A collector was offered a way to add a photograph.');
    }

    public function test_a_deity_no_location_enshrines_is_deleted_and_leaves_the_index(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $deity = DeityFactory::createOne(['name' => 'Never named']);
        $id = $deity->getId();
        $slug = $deity->getSlug();

        $this->client->request(Request::METHOD_GET, '/deity/'.$slug.'/delete');
        $this->client->submitForm('delete_submit');

        $this->assertResponseRedirects('/deities');
        $crawler = $this->client->followRedirect();

        $this->manager()->clear();
        $this->assertNull(static::getContainer()->get(DeityRepository::class)->find($id), 'The deity was not deleted.');
        $this->assertStringNotContainsString('Never named', $crawler->filter('main')->text(), 'The deleted deity is still listed.');
    }

    public function test_a_deity_a_location_enshrines_cannot_be_deleted(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $deity = DeityFactory::createOne(['name' => '八幡神']);
        LocationFactory::createOne(['romanizedName' => 'Usa Jingū', 'deities' => [$deity]]);
        $id = $deity->getId();
        $slug = $deity->getSlug();

        $crawler = $this->client->request(Request::METHOD_GET, '/deity/'.$slug.'/delete');

        $this->assertStringContainsString('still enshrined', $crawler->filter('main')->text(), 'The refusal is not stated.');
        $this->assertCount(0, $crawler->filter('main button'), 'A deity still enshrined offers to be deleted.');

        $free = DeityFactory::createOne(['name' => 'Unused']);
        $confirmation = $this->client->request(Request::METHOD_GET, '/deity/'.$free->getSlug().'/delete');
        $forged = $confirmation->selectButton('delete_submit')->form()->getPhpValues();

        $this->client->request(Request::METHOD_POST, '/deity/'.$slug.'/delete', $forged);

        $this->assertResponseRedirects('/deity/'.$slug);
        $this->manager()->clear();
        $this->assertNotNull(static::getContainer()->get(DeityRepository::class)->find($id), 'A deity still enshrined was deleted anyway.');
    }

    public function test_a_deity_another_collector_keeps_is_out_of_reach(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $slug = DeityFactory::createOne()->getSlug();

        $this->client->loginUser(UserFactory::createOne());

        foreach (['', '/edit', '/delete'] as $suffix) {
            $this->client->request(Request::METHOD_GET, '/deity/'.$slug.$suffix);

            $this->assertResponseStatusCodeSame(404, sprintf('A deity kept by another collector answered on %s.', $suffix === '' ? 'its page' : $suffix));
        }
    }

    public function test_a_collector_is_offered_edition_and_deletion(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $deity = DeityFactory::createOne();

        $crawler = $this->client->request(Request::METHOD_GET, '/deity/'.$deity->getSlug());

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('a[href$="/edit"]'), 'The collector lost the way in.');
        $this->assertCount(1, $crawler->filter('a[href$="/delete"]'));
    }

    public function test_the_home_page_leads_to_the_deities(): void
    {
        $this->client->loginUser(UserFactory::createOne());

        $crawler = $this->client->request(Request::METHOD_GET, '/');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('a[href="/deities"]'), 'The deities are unreachable from the home page.');
    }

    private function stored(string $id): ?string
    {
        return static::getContainer()->get('doctrine')->getConnection()->fetchOne(
            'SELECT name FROM gos_deity WHERE id = :id',
            ['id' => $id],
        ) ?: null;
    }
}
