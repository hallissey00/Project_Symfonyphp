<?php

namespace App\DataFixtures;

use App\Entity\Song;
use App\Entity\User;
use App\Entity\Vote;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;


class VoteFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        //we will create a vote for each song for each user
        foreach ($manager->getRepository(User::class)->findAll() as $key => $user){
            foreach ($manager->getRepository(Song::class)->findAll() as $song){
                $vote = new Vote();
                $vote->setSong($song)
                    ->setUser($user)
                    ->setMessage("Love it ! . ")
                    ->setCreatedAt(new \DateTimeImmutable())
                ;
                $manager->persist($vote);
            }
        }

        $manager->flush();
    }

    public function getDependencies()
    {
        return [
            AppFixtures::class,
            SongFixtures::class,
        ];
    }
}
