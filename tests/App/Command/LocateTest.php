<?php

declare(strict_types=1);

namespace App\Tests\App\Command;

use App\Entity\Location;
use App\Service\Municipality;
use App\Tests\AppTestCase;
use App\Tests\Factory\LocationFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class LocateTest extends AppTestCase
{
    use Factories;
    use ResetDatabase;

    public function test_it_fills_the_code_of_a_located_location_that_has_none(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $kiyomizu = LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera', 'latitude' => 34.994856, 'longitude' => 135.784997]);
        $sensoji = LocationFactory::createOne(['romanizedName' => 'Sensō-ji', 'latitude' => 35.714765, 'longitude' => 139.796655]);

        $this->strip();

        $tester = $this->locate();

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), 'The command reported a failure it did not have.');
        $this->assertSame('26100', $this->coded($kiyomizu->getId()), 'The command left a located location without its code.');
        $this->assertSame('13106', $this->coded($sensoji->getId()), 'The command left a located location without its code.');
        $this->assertStringContainsString('2 located', $tester->getDisplay(), 'The command did not report what it filled in.');
    }

    public function test_it_writes_what_it_fills_in(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $kiyomizu = LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera', 'latitude' => 34.994856, 'longitude' => 135.784997]);

        $this->strip();
        $this->locate();

        $manager = $this->manager();
        $manager->clear();

        $this->assertSame(
            '26100',
            $manager->getConnection()->fetchOne('SELECT municipality_code FROM gos_location WHERE id = ?', [$kiyomizu->getId()]),
            'The code never reached the database.',
        );
    }

    public function test_it_reports_a_location_no_unit_claims(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        LocationFactory::createOne(['romanizedName' => 'Notre-Dame', 'latitude' => 48.852968, 'longitude' => 2.349902]);
        LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera', 'latitude' => 34.994856, 'longitude' => 135.784997]);

        $this->strip();

        $tester = $this->locate();

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), 'One unmatched location failed the whole run.');
        $this->assertStringContainsString('Notre-Dame', $tester->getDisplay(), 'The unmatched location was not reported.');
        $this->assertStringContainsString('1 matched no administrative unit', $tester->getDisplay(), 'The unmatched count was not reported.');
    }

    public function test_it_fails_when_nothing_at_all_could_be_coded(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        LocationFactory::createOne(['romanizedName' => 'Notre-Dame', 'latitude' => 48.852968, 'longitude' => 2.349902]);

        $this->strip();

        $tester = $this->locate();

        $this->assertSame(Command::FAILURE, $tester->getStatusCode(), 'A run that coded nothing reported success.');
    }

    public function test_it_says_so_and_succeeds_when_there_is_nothing_to_fill(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera', 'latitude' => 34.994856, 'longitude' => 135.784997]);

        $tester = $this->locate();

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), 'A run with nothing to do reported a failure.');
        $this->assertStringContainsString('already carries a code', $tester->getDisplay(), 'A run with nothing to do did not say so.');
    }

    public function test_an_unreadable_index_fails_rather_than_silently_coding_nothing(): void
    {
        static::getContainer()->set(Municipality::class, new Municipality(static::getContainer()->getParameter('kernel.project_dir').'/var/no-such-place'));

        $this->client->loginUser(UserFactory::createOne());
        LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera', 'latitude' => 34.994856, 'longitude' => 135.784997]);

        $tester = $this->locate();

        $this->assertSame(Command::FAILURE, $tester->getStatusCode(), 'An unreadable index reported success.');
    }

    private function locate(): CommandTester
    {
        $tester = new CommandTester(new Application(static::$kernel)->find('app:locate'));
        $tester->execute([]);

        return $tester;
    }

    private function strip(): void
    {
        $manager = $this->manager();
        $manager->getConnection()->executeStatement('UPDATE gos_location SET municipality_code = NULL');
        $manager->clear();
    }

    private function coded(string $id): ?string
    {
        return $this->manager()->find(Location::class, $id)?->getMunicipalityCode();
    }
}
