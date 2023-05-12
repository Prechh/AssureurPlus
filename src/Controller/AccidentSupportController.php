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
use GuzzleHttp\Client;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;


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
    public function new(Request $request, EntityManagerInterface $manager,  SessionInterface $session): Response
    {
        $accident = new Accident();
        $form = $this->createForm(AccidentType::class, $accident);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $latitude = $request->request->get('latitude');
            $longitude = $request->request->get('longitude');

            // Stockez les coordonnées dans la session
            $session->set('latitude', $latitude);
            $session->set('longitude', $longitude);

            $accident->setCurrentAccidentPosition($latitude . ',' . $longitude);
            $accident = $form->getData();


            $manager->persist($accident);
            $manager->flush();

            return $this->redirectToRoute('app_accident_support_test');
        }

        return $this->render('accidentsupport/new.html.twig', [
            'form' => $form->createView()
        ]);
    }

    private function getDistance($lat1, $lng1, $lat2, $lng2)
    {
        $earthRadius = 6371; // Rayon de la Terre en kilomètres

        // Convertir les latitudes et longitudes en radians
        $lat1 = deg2rad($lat1);
        $lng1 = deg2rad($lng1);
        $lat2 = deg2rad($lat2);
        $lng2 = deg2rad($lng2);

        // Calculer la différence entre les latitudes et longitudes
        $latDiff = $lat2 - $lat1;
        $lngDiff = $lng2 - $lng1;

        // Calculer la distance en utilisant la formule de la haversine
        $a = sin($latDiff / 2) * sin($latDiff / 2) + cos($lat1) * cos($lat2) * sin($lngDiff / 2) * sin($lngDiff / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = $earthRadius * $c;

        return $distance;
    }

    #[Route('/accidentsupport/test', name: 'app_accident_support_test')]
    public function trouverRemorqueurs(Request $request, SessionInterface $session)
    {
        // Récupérez les variables depuis la session
        $latitude = $session->get('latitude');
        $longitude = $session->get('longitude');

        // Définissez l'URL de l'API du fournisseur de services de remorquage et les paramètres de la requête
        $url = 'https://maps.googleapis.com/maps/api/place/nearbysearch/json';
        $params = [
            'location' => $latitude . ',' . $longitude,
            'radius' => 5000, // Rayon de recherche en mètres
            'keyword' => 'Service Remorquage',
            'key' => 'AIzaSyCuXOMkq5XtWdRyLR6EH-K9ImvrPaBCGDk',
        ];

        // Initialisez un client Guzzle pour envoyer une requête HTTP à l'API du fournisseur de services de remorquage
        $client = new Client(['verify' => false]);

        // Envoyez une requête HTTP GET à l'API Adresse avec les paramètres
        $response = $client->request('GET', $url, ['query' => $params]);

        // Récupérez le corps de la réponse de l'API
        $body = $response->getBody()->getContents();

        // Analysez les données JSON de la réponse
        $services = json_decode($body, true);


        if (isset($services['results']) && !empty($services['results'])) {
            // Traitez les données de la réponse pour récupérer les adresses des garages et leur géolocalisation
            $nearestGarage = null;
            $nearestDistance = PHP_INT_MAX;


            foreach ($services['results'] as $result) {
                $address = mb_convert_encoding($result['name'], 'UTF-8');
                $garageLatitude = $result['geometry']['location']['lat'];
                $garageLongitude = $result['geometry']['location']['lng'];
                $fullAddress = isset($result['vicinity']) ? mb_convert_encoding($result['vicinity'], 'UTF-8') : '';
                $distance = $this->getDistance($latitude, $longitude, $garageLatitude, $garageLongitude);
                if ($distance < $nearestDistance) {
                    $nearestGarage = [
                        'Adresse' => $address,
                        'latitude' => $garageLatitude,
                        'longitude' => $garageLongitude,
                        'fullAdress' => $fullAddress,
                        'distance' => $distance,
                    ];
                    $nearestDistance = $distance;
                }
            }


            // Vérifiez s'il y a un garage trouvé
            if ($nearestGarage !== null) {
                $garageName = $nearestGarage['Adresse'];
                $garageAddress = $nearestGarage['fullAdress'];
                $message = "Appel en cours du garage ' $garageName ' situe à $garageAddress";
                return $this->render('accidentsupport/message.html.twig', [
                    'message' => $message,
                ]);
            } else {
                // Aucun service de remorquage trouvé, retournez un message d'erreur
                return new JsonResponse(['error' => 'Aucun service de remorquage trouvé.']);
            }
        } else {
            // Aucun service de remorquage trouvé, retournez un message d'erreur
            return new JsonResponse(['error' => 'Aucun service de remorquage trouvé.']);
        }
    }
}
