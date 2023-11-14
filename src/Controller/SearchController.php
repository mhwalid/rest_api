<?php
// src/Controller/LuckyController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\CompanyRepository;
use App\Entity\Company;



class SearchController extends AbstractController
{

    #[Route('/search',name: 'getfrom' , methods: ['GET'])]
    public function search(): Response
    {
        
        return $this->render('home.html.twig');
    }

    #[Route('/search', name: 'getname', methods: ['POST'])]
    public function getsearch(Request $request)
    {
        $client = HttpClient::create();
        $response = $client->request('GET', "https://recherche-entreprises.api.gouv.fr/search?q=".$request->get('searchvar'));
        $content = $response->getContent();
        $data = json_decode($content, true); 
        
        return $this->render('companies.html.twig', ['data' => $data['results']]);
    }

    #[Route('/details', name: 'getitem')]
    public function getitem(Request $request, CompanyRepository $companyrepo,EntityManagerInterface $em)
    {  
       
        // Get the Entity Manager
        $result=$request->query->all('item');
         // Create a new Company 
         $company = $companyrepo->findOneBy(['siren' => $result['siren']]);
         if($company === null){
            $company = new Company();
            $company->setRaisonSociale($result['nom_raison_sociale'] ?? '');
            $company->setSiren($result['siren']);
            $company->setSiret($result['siege']['siret']);
            $company->setAdresse($result['siege']['adresse']);

            // Persist the object
            $em->persist($company);
            // Flush changes to the database
            $em->flush();
         }

      return $this->render('show.html.twig', ['company' => $company]);
    }

    #[Route('/details_api', name: 'getDetailsApi')]
    public function getDetailsApi(Request $request,CompanyRepository $companyrepo,EntityManagerInterface $em )
    {  

        $url = 'https://mon-entreprise.urssaf.fr/api/v1/evaluate';
        $data = [
            'situation' => [
                'salarié . contrat . salaire brut' => [
                    'valeur' => $request->get('salaire'),
                    'unité' => '€ / mois',
                ],
                'salarié . contrat' => 'CDI',
            ],
            'expressions' => [
                'salarié . rémunération . net . à payer avant impôt',
                'salarié . contrat . stage . gratification minimale',
                'salarié . coût total employeur',
                'salarié . cotisations . salarié',
                'salarié . contrat . CDD . indemnité de fin de contrat',
            ],
        ];
        $client = HttpClient::create();
        $response = $client->request('POST', $url, [
            'json' => $data,
        ]);

        // Traitez la réponse ici
        $statusCode = $response->getStatusCode();
        $content = $response->toArray();
        $company = $companyrepo->findOneBy(['siren' => $request->get('siren')]);
        // dd($company);
        // Faites quelque chose avec $statusCode et $content
            // dd($content['evaluate'][0]['missingVariables']["salarié . cotisations . prévoyances . santé . montant"]);
        // Retournez la réponse ou effectuez d'autres opérations nécessaires
        return $this->render('show.html.twig', ['company' => $company, 'content'=>$content]);
    }


}