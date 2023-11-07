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


}