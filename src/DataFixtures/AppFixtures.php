<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

use App\Factory\UserFactory;
use App\Factory\MakeFactory;
use App\Factory\PhoneFactory;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        UserFactory::createOne([
            'username' => 'admin',
            'password' => 'password',
            'role' => 'ROLE_ADMIN'
        ]);

        UserFactory::createOne([
            'username' => 'user1',
            'password' => 'password',
            'role' => 'ROLE_USER'
        ]);

        UserFactory::createOne([
            'username' => 'user2',
            'password' => 'password',
            'role' => 'ROLE_COMMITEE'
        ]);

    }
}
