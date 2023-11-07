<?php
// src/Controller/LuckyController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\JsonResponse;



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
    public function getitem(Request $request)
    { 
        dd($request->query->all('item'));
        // dd($request);
    }


}