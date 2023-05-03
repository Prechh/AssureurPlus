<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AccidentSupportController extends AbstractController
{
    #[Route('/accidentsupport', name: 'app_accident_support')]
    public function index(): Response
    {
        $article = $paginator->paginate(
            $repository->findAll(),
            $request->query->getInt('page', 1), /* page number */
            10
        );

        return $this->render('article/article.html.twig', [
            'article' => $article,
        ]);
    }

    #[Route('/accidentsupport/new', name: 'app_accident_support_new')]
    public function new(): Response
    {
        return $this->render('accident_support/index.html.twig', [
            'controller_name' => 'AccidentSupportController',
        ]);
    }
}
