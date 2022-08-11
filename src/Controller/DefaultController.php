<?php

namespace App\Controller;

use App\Entity\Song;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends AbstractController
{
    // This is the home page. We can the final accepted playlist
    // Committee members can slide and edit the order of playlist songs
    #[Route('/', name: 'default')]
    public function index(EntityManagerInterface $em): Response
    {
        $template = 'default/index.html.twig';
        $argsArray = [
            'acceptedSongs' => $em->getRepository(Song::class)->findBy(['status' => Song::ACCEPTED_STATUS], ["playListOrder" => "ASC"]),
            'today' => new \DateTimeImmutable(),
        ];

        return $this->render($template, $argsArray);
    }
}
