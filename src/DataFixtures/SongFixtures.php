<?php

namespace App\DataFixtures;

use App\Entity\Song;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;


class SongFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        //we will create a song for each user
        foreach ($manager->getRepository(User::class)->findAll() as $key => $user){
            $song = new Song();
            $song->setTitle('Song'.$key. " ". $user->getUsername())
                ->setUser($user)
                ->setStatus(Song::PROPOSED_STATUS)
                ->setMessage("This song is really amazing . ".$user->getId())
                ->setCreatedAt(new \DateTimeImmutable())
                ->setUpdatedAt(new \DateTimeImmutable())
                ;
            $manager->persist($song);
        }

        $manager->flush();
    }

    public function getDependencies()
    {
        return [
            AppFixtures::class,
        ];
    }
}
