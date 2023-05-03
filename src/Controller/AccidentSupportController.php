<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Entity\Accident;
use App\Form\AccidentType;
use App\Repository\AccidentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Knp\Component\Pager\PaginatorInterface;


class AccidentSupportController extends AbstractController
{
    #[Route('/accidentsupport', name: 'app_accident_support')]
    public function index(AccidentRepository $repository, PaginatorInterface $paginator, Request $request): Response
    {
        $accident = $paginator->paginate(
            $repository->findAll(),
            $request->query->getInt('page', 1), /* page number */
            10
        );

        return $this->render('accidentsupport/index.html.twig', [
            'accident' => $accident,
        ]);
    }

    #[Route('/accidentsupport/new', name: 'app_accident_support_new')]
    public function new(Request $request, EntityManagerInterface $manager): Response
    {
        $accident = new Accident();
        $form = $this->createForm(AccidentType::class, $accident);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $accident = $form->getData();

            $manager->persist($accident);
            $manager->flush();

            return $this->redirectToRoute('app_home');
        }

        return $this->render('accidentsupport/new.html.twig', [
            'form' => $form->createView()
        ]);
    }
}
