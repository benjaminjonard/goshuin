<?php

declare(strict_types=1);

namespace App\Tests\App;

use App\Entity\Location;
use App\Enum\LocationType;
use App\Repository\LocationRepository;
use App\Tests\Factory\LocationFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class LocationTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private KernelBrowser $client;

    #[\Override]
    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function test_a_location_belongs_to_the_instance_and_not_to_a_user(): void
    {
        $metadata = static::getContainer()->get('doctrine')->getManager()->getClassMetadata(Location::class);

        $this->assertNotContains('owner_id', $metadata->getColumnNames(), 'Location was given an owner, which would scope shared reference data.');
        $this->assertSame([], $metadata->getAssociationNames(), 'Location grew an association it should not have.');
    }

    public function test_every_user_sees_the_same_locations(): void
    {
        LocationFactory::createOne(['romanizedName' => 'Fushimi Inari-taisha']);
        LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera']);

        foreach ([1, 2] as $ignored) {
            $this->client->loginUser(UserFactory::createOne());
            $this->client->request(Request::METHOD_GET, '/');

            $this->assertCount(2, $this->locations()->findAll(), 'A user could not see the shared locations.');
        }
    }

    public function test_it_searches_on_either_name(): void
    {
        LocationFactory::createOne(['romanizedName' => 'Fushimi Inari-taisha', 'japaneseName' => '伏見稲荷大社']);
        LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera', 'japaneseName' => '清水寺']);
        $this->client->loginUser(UserFactory::createOne());
        $this->client->request(Request::METHOD_GET, '/');

        $this->assertCount(1, $this->locations()->search('伏見'), 'The Japanese name was not searched.');
        $this->assertCount(1, $this->locations()->search('kiyomizu'), 'The romaji name was not searched.');
        $this->assertCount(1, $this->locations()->search('KIYOMIZU'), 'The search was case-sensitive.');
        $this->assertCount(0, $this->locations()->search('nothing here'));
    }

    public function test_it_finds_the_names_that_already_exist(): void
    {
        LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera']);
        LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera']);
        LocationFactory::createOne(['romanizedName' => 'Byodo-in']);
        $this->client->loginUser(UserFactory::createOne());
        $this->client->request(Request::METHOD_GET, '/');

        $this->assertCount(2, $this->locations()->namedExactly('Kiyomizu-dera'), 'A probable duplicate would not be warned about.');
        $this->assertCount(1, $this->locations()->namedExactly('Byodo-in'));
    }

    public function test_an_unrecorded_type_stays_null_and_is_not_other(): void
    {
        $location = LocationFactory::createOne(['romanizedName' => 'Somewhere']);
        $recorded = LocationFactory::createOne(['romanizedName' => 'Hiroshima Castle', 'type' => LocationType::Other]);

        $this->assertNull($location->getType(), 'An unrecorded type was given a value.');
        $this->assertSame(LocationType::Other, $recorded->getType());
    }

    public function test_coordinates_are_optional_and_removable(): void
    {
        $location = LocationFactory::createOne(['romanizedName' => 'Placed', 'latitude' => 34.9671, 'longitude' => 135.7727]);
        $id = $location->getId();
        $this->assertTrue($location->hasCoordinates());

        $manager = static::getContainer()->get('doctrine')->getManager();
        $location->setLatitude(null)->setLongitude(null);
        $manager->flush();
        $manager->clear();

        $stored = $this->locations()->find($id);
        $this->assertFalse($stored->hasCoordinates(), 'The coordinates could not be removed.');
        $this->assertSame('Placed', $stored->getRomanizedName(), 'Removing the coordinates invalidated the location.');
    }

    private function locations(): LocationRepository
    {
        return static::getContainer()->get(LocationRepository::class);
    }
}
