<?php

declare(strict_types=1);

namespace App\Tests\App\Controller;

use App\Repository\TagRepository;
use App\Tests\AppTestCase;
use App\Tests\Factory\GoshuinFactory;
use App\Tests\Factory\GoshuinchoFactory;
use App\Tests\Factory\TagFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class TagTest extends AppTestCase
{
    use Factories;
    use ResetDatabase;

    public function test_the_tags_are_private(): void
    {
        $id = TagFactory::createOne(['owner' => UserFactory::createOne()])->getId();

        foreach (['/tags', '/tag/'.$id.'/edit', '/tag/'.$id.'/delete'] as $url) {
            $this->client->request(Request::METHOD_GET, $url);

            $this->assertResponseRedirects();
            $this->client->followRedirect();
            $this->assertRouteSame('app_login', [], sprintf('%s is reachable signed out.', $url));
        }
    }

    public function test_a_tag_belonging_to_another_collector_is_not_found(): void
    {
        $tag = TagFactory::createOne(['name' => 'dog', 'owner' => UserFactory::createOne()]);
        $id = $tag->getId();
        $this->client->loginUser(UserFactory::createOne());

        foreach (['/tag/'.$id.'/edit', '/tag/'.$id.'/delete'] as $url) {
            $this->client->request(Request::METHOD_GET, $url);

            $this->assertResponseStatusCodeSame(404, sprintf('%s reaches another collector.', $url));
        }

        $this->assertStringNotContainsString(
            'dog',
            $this->client->request(Request::METHOD_GET, '/tags')->filter('main')->text(),
            'The index lists another collector\'s tag.',
        );
    }

    public function test_the_index_lists_the_tags_and_leads_each_to_its_goshuin(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $crane = TagFactory::createOne(['name' => 'crane']);
        $id = $crane->getId();
        TagFactory::createOne(['name' => 'dog']);

        $crawler = $this->client->request(Request::METHOD_GET, '/tags');

        $this->assertResponseIsSuccessful();
        $this->assertCount(2, $crawler->filter('main ul li'), 'The index does not list every tag.');

        $first = $crawler->filter('main ul li a[href^="/goshuin"]')->first();
        $this->assertSame('/goshuin?tag='.$id, $first->attr('href'), 'A tag does not lead to the goshuin bearing it.');
        $this->assertSame('crane', trim($first->text()));
    }

    public function test_each_row_counts_the_goshuin_bearing_the_tag(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $dog = TagFactory::createOne(['name' => 'dog']);
        TagFactory::createOne(['name' => 'unused']);
        GoshuinFactory::new()->in($goshuincho, 1)->create(['tags' => [$dog]]);
        GoshuinFactory::new()->in($goshuincho, 2)->create(['tags' => [$dog]]);

        $rows = $this->client->request(Request::METHOD_GET, '/tags')
            ->filter('main ul li')
            ->each(static fn (Crawler $row): string => preg_replace('/\s+/', ' ', trim($row->text())))
        ;

        $this->assertStringContainsString('2 goshuin', $rows[0], 'A tag does not count the goshuin bearing it.');
        $this->assertStringContainsString('0 goshuin', $rows[1], 'A tag no goshuin bears counts nothing.');
    }

    public function test_each_row_offers_the_tag_to_be_renamed_or_deleted(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $id = TagFactory::createOne(['name' => 'dog'])->getId();

        $row = $this->client->request(Request::METHOD_GET, '/tags')->filter('main ul li');

        $this->assertCount(1, $row->filter('a[href="/tag/'.$id.'/edit"]'), 'A tag cannot be renamed from the index.');
        $this->assertCount(1, $row->filter('a[href="/tag/'.$id.'/delete"]'), 'A tag cannot be deleted from the index.');
    }

    public function test_the_index_is_ordered_by_the_name(): void
    {
        $this->client->loginUser(UserFactory::createOne());

        foreach (['plum blossom', 'crane', 'dog'] as $name) {
            TagFactory::createOne(['name' => $name]);
        }

        $names = $this->client->request(Request::METHOD_GET, '/tags')
            ->filter('main ul li a[href^="/goshuin"]')
            ->each(static fn (Crawler $row): string => trim($row->text()))
        ;

        $this->assertSame(['crane', 'dog', 'plum blossom'], $names, 'The index is not ordered by the name.');
    }

    public function test_the_index_pages_through_the_tags(): void
    {
        $this->client->loginUser(UserFactory::createOne());

        foreach (range(1, 26) as $rank) {
            TagFactory::createOne(['name' => sprintf('motif %02d', $rank)]);
        }

        $first = $this->client->request(Request::METHOD_GET, '/tags');

        $this->assertCount(24, $first->filter('main ul li'), 'The index does not hold a full page of tags.');
        $this->assertStringNotContainsString('motif 25', $first->filter('main ul')->text(), 'The first page reaches past its own end.');

        $second = $this->client->click($first->filter('main nav a')->last()->link());

        $this->assertResponseIsSuccessful();
        $this->assertCount(2, $second->filter('main ul li'), 'The last page does not hold what the first left.');
        $this->assertStringContainsString('motif 26', $second->filter('main ul')->text());
    }

    public function test_paging_the_tags_keeps_the_search(): void
    {
        $this->client->loginUser(UserFactory::createOne());

        foreach (range(1, 25) as $rank) {
            TagFactory::createOne(['name' => sprintf('motif %02d', $rank)]);
        }

        TagFactory::createOne(['name' => 'dog']);

        $first = $this->client->request(Request::METHOD_GET, '/tags?q=motif');
        $this->assertCount(24, $first->filter('main ul li'), 'The search does not fill a page.');

        $second = $this->client->click($first->filter('main nav a')->last()->link());

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $second->filter('main ul li'), 'Paging a search dropped it and listed everything again.');
        $this->assertStringNotContainsString('dog', $second->filter('main ul')->text(), 'Paging a search reached what it excluded.');
    }

    public function test_a_page_of_tags_beyond_the_last_is_not_found(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        TagFactory::createOne(['name' => 'dog']);

        $this->client->request(Request::METHOD_GET, '/tags?page=2');

        $this->assertResponseStatusCodeSame(404);
    }

    public function test_the_index_states_that_no_tag_exists_yet(): void
    {
        $this->client->loginUser(UserFactory::createOne());

        $crawler = $this->client->request(Request::METHOD_GET, '/tags');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('No tag yet.', $crawler->filter('main')->text(), 'An empty index says nothing.');
    }

    public function test_a_search_matching_nothing_says_so_and_leads_back(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        TagFactory::createOne(['name' => 'dog']);

        $crawler = $this->client->request(Request::METHOD_GET, '/tags?q=lantern');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('No result', $crawler->filter('main')->text(), 'An empty search says nothing.');
        $this->assertSame('/tags', $crawler->filter('main p a')->attr('href'), 'An empty search does not lead back.');
    }

    public function test_the_search_matches_any_part_of_a_name(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        TagFactory::createOne(['name' => 'plum blossom']);
        TagFactory::createOne(['name' => 'dog']);

        $crawler = $this->client->request(Request::METHOD_GET, '/tags?q=BLOSS');

        $this->assertCount(1, $crawler->filter('main ul li'), 'The search does not match the middle of a name.');
        $this->assertStringContainsString('plum blossom', $crawler->filter('main ul')->text());
    }

    public function test_an_unknown_tag_is_not_found(): void
    {
        $this->client->loginUser(UserFactory::createOne());

        $this->client->request(Request::METHOD_GET, '/tag/'.Uuid::v7()->toRfc4122().'/edit');

        $this->assertResponseStatusCodeSame(404);
    }

    public function test_a_tag_is_renamed_and_the_new_name_shows_on_its_goshuin(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $page = '/goshuincho/'.$goshuincho->getId().'/goshuin/1';
        $tag = TagFactory::createOne(['name' => 'dog']);
        $id = $tag->getId();
        $id = $tag->getId();
        GoshuinFactory::new()->in($goshuincho)->create(['tags' => [$tag]]);

        $this->client->request(Request::METHOD_GET, '/tag/'.$id.'/edit');
        $this->client->submitForm('tag_submit', ['tag[name]' => 'shiba']);

        $this->assertResponseRedirects('/tags');

        $this->manager()->clear();
        $this->assertSame('shiba', static::getContainer()->get(TagRepository::class)->find($id)->getName());

        $read = $this->client->request(Request::METHOD_GET, $page)->filter('main')->text();
        $this->assertStringContainsString('shiba', $read, 'The renamed tag did not reach the goshuin bearing it.');
    }

    public function test_the_form_refuses_a_tag_with_no_name(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $tag = TagFactory::createOne(['name' => 'dog']);
        $id = $tag->getId();

        $this->client->request(Request::METHOD_GET, '/tag/'.$tag->getId().'/edit');
        $this->client->submitForm('tag_submit', ['tag[name]' => '']);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame('dog', $this->stored($id), 'A refused submission still wrote the tag away without a name.');
    }

    public function test_the_form_refuses_a_name_another_tag_already_bears(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        TagFactory::createOne(['name' => 'crane']);
        $dog = TagFactory::createOne(['name' => 'dog']);
        $id = $dog->getId();

        $this->client->request(Request::METHOD_GET, '/tag/'.$dog->getId().'/edit');
        $crawler = $this->client->submitForm('tag_submit', ['tag[name]' => 'crane']);

        $this->assertResponseStatusCodeSame(422);
        $this->assertStringContainsString('already bears that name', $crawler->filter('main')->text(), 'The collision is not stated.');
        $this->assertSame('dog', $this->stored($id), 'The colliding name was stored anyway.');
    }

    public function test_two_collectors_may_each_hold_a_tag_of_the_same_name(): void
    {
        $heldId = TagFactory::createOne(['name' => 'dog', 'owner' => UserFactory::createOne()])->getId();
        $this->client->loginUser(UserFactory::createOne());
        $own = TagFactory::createOne(['name' => 'crane']);
        $ownId = $own->getId();

        $this->client->request(Request::METHOD_GET, '/tag/'.$own->getId().'/edit');
        $this->client->submitForm('tag_submit', ['tag[name]' => 'dog']);

        $this->assertResponseRedirects('/tags');
        $this->assertSame('dog', $this->stored($ownId), 'A name another collector holds was refused.');
        $this->assertSame('dog', $this->stored($heldId), 'The other collector\'s tag was touched.');
    }

    public function test_a_tag_no_goshuin_bears_is_deleted(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $tag = TagFactory::createOne(['name' => 'dog']);
        $id = $tag->getId();

        $this->client->request(Request::METHOD_GET, '/tag/'.$tag->getId().'/delete');
        $this->client->submitForm('delete_submit');

        $this->assertResponseRedirects('/tags');
        $this->manager()->clear();
        $this->assertNull(static::getContainer()->get(TagRepository::class)->find($id), 'The tag survived its deletion.');
    }

    public function test_a_tag_a_goshuin_bears_cannot_be_deleted(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $tag = TagFactory::createOne(['name' => 'dog']);
        $id = $tag->getId();
        $id = $tag->getId();
        $free = TagFactory::createOne(['name' => 'unused']);
        $spare = $free->getId();
        GoshuinFactory::new()->in($goshuincho)->create(['tags' => [$tag]]);

        $crawler = $this->client->request(Request::METHOD_GET, '/tag/'.$id.'/delete');

        $this->assertStringContainsString('still on a goshuin', $crawler->filter('main')->text(), 'The refusal is not stated.');
        $this->assertCount(0, $crawler->filter('main button'), 'A tag a goshuin bears offers to be deleted.');

        $confirmation = $this->client->request(Request::METHOD_GET, '/tag/'.$spare.'/delete');
        $forged = $confirmation->selectButton('delete_submit')->form()->getPhpValues();

        $this->client->request(Request::METHOD_POST, '/tag/'.$id.'/delete', $forged);

        $this->assertResponseRedirects('/tag/'.$id.'/delete');
        $this->manager()->clear();
        $this->assertNotNull(static::getContainer()->get(TagRepository::class)->find($id), 'A tag a goshuin bears was deleted anyway.');
    }

    private function stored(string $id): ?string
    {
        return static::getContainer()->get('doctrine')->getConnection()->fetchOne(
            'SELECT name FROM gos_tag WHERE id = :id',
            ['id' => $id],
        ) ?: null;
    }
}
