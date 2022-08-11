<?php

namespace App\Controller;

use App\Entity\Song;
use App\Entity\User;
use App\Entity\Vote;
use App\Form\SongType;
use App\Form\VoteType;
use App\Repository\SongRepository;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Finder\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/song')]
class SongController extends AbstractController
{

    //List all songs for administrator
    #[Route('/', name: 'song_index', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function index(SongRepository $songRepository): Response
    {
        return $this->render('song/index.html.twig', [
            'songs' => $songRepository->findAll(),
        ]);
    }

    // user related songs (My Songs)
    #[Route('/mySongs', name: 'user_songs', methods: ['GET'])]
    public function userSongs(SongRepository $songRepository): Response
    {
        return $this->render('song/index.html.twig', [
            'songs' => $songRepository->findBy(['user' => $this->getUser()]),
        ]);
    }

    // Propose a song action
    #[Route('/new', name: 'song_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $song = new Song();
        $form = $this->createForm(SongType::class, $song);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $song->setUser($this->getUser())
                ->setStatus(Song::PROPOSED_STATUS)
                ->setCreatedAt(new \DateTimeImmutable())
                ->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->persist($song);
            $entityManager->flush();

            return $this->redirectToRoute('user_songs', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('song/new.html.twig', [
            'song' => $song,
            'form' => $form,
        ]);
    }


    // Edit a song
    #[Route('/{id}/edit', name: 'song_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Song $song, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(SongType::class, $song);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('song_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('song/edit.html.twig', [
            'song' => $song,
            'form' => $form,
        ]);
    }

    // list of proposed songs
    #[Route('/proposed', name: 'proposed_songs', methods: ['GET'])]
    public function proposedSongs(SongRepository $songRepository): Response
    {
        return $this->render('song/proposed.html.twig', [
            'songs' => $songRepository->findBy(["status" => Song::PROPOSED_STATUS]),
        ]);
    }

    // list of accepted songs
    #[Route('/accepted', name: 'accepted_songs', methods: ['GET'])]
    public function acceptedSongs(SongRepository $songRepository, EntityManagerInterface $em): Response
    {
        $acceptedSongs = [];
        foreach ( $songRepository->findBy(["status" => Song::ACCEPTED_STATUS]) as $song){
            $acceptedSongs[] = [
                'song' =>   $song,
                'votes' => $em->getRepository(Vote::class)->getSongVotesCount($song),
            ];
        }

        //sort accepted song array by votes count
        usort($acceptedSongs, function ($song1, $song2) {
            return $song2['votes'] <=> $song1['votes'];
        });


        return $this->render('song/accepted.html.twig', [
            'acceptedSongs' => $acceptedSongs,
        ]);
    }

    // show song voting details (pattern)
    #[Route('/details/{id}', name: 'song_details', methods: ['GET'])]
    #[IsGranted('ROLE_COMMITEE')]
    public function songDetails(Song $song, EntityManagerInterface $em)
    {
        return $this->render('song/details.html.twig', [
            'song' => $song,
            'votes' => $em->getRepository(Vote::class)->findBy(['song' => $song]),
        ]);
    }

    // voting for a song (Proposed or accepted)
    #[Route('/vote/{id}/', name: 'song_vote', methods: ['GET', 'POST'])]
    public function vote(Request $request, Song $song, EntityManagerInterface $entityManager)
    {
        $currentUser = $this->getUser();
        $blocked = false;

        // for up to 1 week after they were proposed
        // clone the date to not edit the createdAt
        if ($song->getStatus() == Song::PROPOSED_STATUS){
            $lasVoteDate = clone($song->getCreatedAt())->add(new \DateInterval('P7D'));
            $canVote = $lasVoteDate >= new \DateTimeImmutable();
        }elseif ($song->getStatus() == Song::ACCEPTED_STATUS){
            $lasVoteDate = clone($song->getAcceptedAt())->add(new \DateInterval('P7D'));
            $canVote = $lasVoteDate >= new \DateTimeImmutable();
        }else{
            $canVote = false;
        }

        // if song proposed by current logged user or already voted for this song or more than 1 week passed
        if ($song->getUser() === $currentUser ||
            $entityManager->getRepository(Vote::class)->findOneBy(['user' => $currentUser, "song" => $song]) ||
            !$canVote)
        {
           $blocked = true;
        }

        // if cannot vote, redirect to last path with error message
        if ($blocked){
            $this->addFlash("danger", "You cannot vote for this song !");
            return $this->redirect($request->headers->get('referer'));
        }

        // if can vote display form
        $vote = new Vote();
        $voteForm = $this->createForm(VoteType::class, $vote);
        $voteForm->handleRequest($request);

        if ($voteForm->isSubmitted() && $voteForm->isValid()) {
            $vote->setUser($currentUser)
                ->setSong($song)
                ->setCreatedAt(new \DateTimeImmutable());
            $entityManager->persist($vote);
            $entityManager->flush();

            return $this->redirect($request->headers->get('referer'));
        }

        return $this->renderForm('song/vote.html.twig', [
            'song' => $song,
            'voteForm' => $voteForm,
        ]);
    }

    // ROLE COMMITEE member may Accept or Reject Songs
    #[Route('/moderate/{id}/{status}', name: 'song_moderate')]
    public function moderateSong(Song $song, string $status, EntityManagerInterface $em): RedirectResponse
    {
        if ($this->isGranted('ROLE_COMMITEE')){
            $song->setStatus($status);
            if ($status == Song::ACCEPTED_STATUS)
                $song->setAcceptedAt(new \DateTimeImmutable());
            $em->persist($song);
            $em->flush();
        }else{
            throw new AccessDeniedException();
        }

        return $this->redirectToRoute('proposed_songs');
    }

    // delete a song
    #[Route('/delete/{id}', name: 'song_delete', methods: ['POST'])]
    public function delete(Request $request, Song $song, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if ($this->isCsrfTokenValid('delete'.$song->getId(), $request->request->get('_token'))) {
            $entityManager->remove($song);
            $entityManager->flush();
        }

        return $this->redirectToRoute('song_index', [], Response::HTTP_SEE_OTHER);
    }

    // show song infos
    #[Route('/show/{id}', name: 'song_show', methods: ['GET'])]
    public function show(Song $song): Response
    {
        return $this->render('song/show.html.twig', [
            'song' => $song,
        ]);
    }

    // Update songs order in playlist (used by ajax call) --see default\index.html.twig--
    #[Route('/order/update', name: 'song_reorder', methods: ['POST'])]
    public function updateSongPosition(Request $request, EntityManagerInterface $em): JsonResponse
    {
        try {
            $songsOrder = $request->get('orders');
            foreach ($songsOrder as $order => $songId) {
                $song = $em->getRepository(Song::class)->findOneBy(['id' => $songId]);
                $song->setPlayListOrder($order);
                $em->persist($song);
            }
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Order updated successfully'
            ]);
        }catch (\Exception){
            return new JsonResponse([
                'success' => false,
                'message' => 'Order can not be updated'
            ]);
        }

    }
}
