<?php

namespace App\Controller;

use App\Entity\Address;
use App\Entity\Company;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Validator\Constraints as Assert;


class CompanyController extends AbstractController
{

    private const USERNAME = 'basile';
    private const PASSWORD = 'basile';
    private HttpClientInterface $client;

    private EntityManagerInterface $em;

    public function __construct(HttpClientInterface $client, EntityManagerInterface $em)
    {
        $this->client = $client;
        $this->em = $em;
    }


    /**
     * ---------------------- EXERCICE 1 ------------------------
     */

    #[Route('/', name: 'home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->render('home.html.twig');
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     */
    #[Route('/listCompanies', name: 'listCompanies', methods: ['POST'])]
    public function searchCompanies(Request $request): Response
    {
        $response = $this->client->request('GET', 'https://recherche-entreprises.api.gouv.fr/search', [
            'query' => [
                'q' => $request->get('companySearch'),
            ],
        ]);

        $datas = $response->toArray();

        return $this->render('companies.html.twig', ['datas' => $datas]);
    }

    #[Route('/getCompany', name: 'getCompany')]
    public function getCompany(Request $request): Response
    {
        $chosenCompany = $request->query->all('company');
        $company = $this->em->getRepository(Company::class)->findOneBy(['siren' => $chosenCompany['siren']]);
        if ($company === null) {
            $company = new Company();
            $company->setRaisonSociale($chosenCompany['nom_raison_sociale'] ?? '');
            $company->setSiren($chosenCompany['siren']);
            $company->setSiret($chosenCompany['siege']['siret']);

            $address = new Address();
            $address->setVille($chosenCompany['siege']['libelle_commune']);
            $address->setCdp($chosenCompany['siege']['code_postal'] ?? null);
            $voie = $chosenCompany['siege']['type_voie'] ?? '';
            $libelleVoie = $chosenCompany['siege']['libelle_voie'] ?? '';
            $address->setVoie($voie . ' ' . $libelleVoie);
            $address->setNumero($chosenCompany['siege']['numero_voie'] ?? null);
            $address->setGpsLatitude($chosenCompany['siege']['latitude'] ?? null);
            $address->setGpsLongitude($chosenCompany['siege']['longitude'] ?? null);

            $company->setAddress($address);

            $this->em->persist($company);
            $this->em->flush();
        }

        $request->getSession()->set('chosen_company', $company);

        return $this->render('show.html.twig', ['company' => $company]);
    }


    /**
     * ---------------------- EXERCICE 2 ------------------------
     */

    #[Route('/calculateSalary', name: 'calculateSalary')]
    public function calculateSalary(Request $request): Response
    {
        // Récupération du salaire fournit dans le formulaire
        $salaireBrut = floatval($request->get('salaireBrut'));
        $company = $request->getSession()->get('chosen_company');

        // Appels API pour tous les types de contrats
        $infosStage = $this->useUrssafApi('stage', $salaireBrut);
        $infosCDI = $this->useUrssafApi('CDI', $salaireBrut);
        $infosAlternance = $this->useUrssafApi('apprentissage', $salaireBrut);
        $infosCDD = $this->useUrssafApi('CDD', $salaireBrut);

        // Données à envoyer à la vue
        $datasCalculate = [
            'CDI' => $infosCDI,
            'Stage' => $infosStage,
            'Alternance' => $infosAlternance,
            'CDD' => $infosCDD
        ];

        return $this->render('show.html.twig', [
            'company' => $company,
            'datasCalculate' => $datasCalculate
        ]);
    }

    private function useUrssafApi(string $contrat, float $salaireBrut): array
    {
        $expressions = $this->getExpressions($contrat);
        $url = 'https://mon-entreprise.urssaf.fr/api/v1/evaluate';
        // Construction de nos paramètres à envoyer à l'API en fonction du contrat
        $params = [
            'situation' => [
                'salarié . contrat . salaire brut' => [
                    'valeur' => $salaireBrut,
                    'unité' => '€ / mois',
                ],
                'salarié . contrat' => "'" . $contrat . "'",
            ],
            'expressions' => $expressions
        ];

        $datas = [];
        // Appel à l'API
        $response = $this->client->request('POST', $url, [
            'json' => $params,
        ]);

        // On définit les clefs pour accéder aux données de sorte à pouvoir les afficher plus facilement dans notre vue par la suite
        foreach ($expressions as $key => $expression) {
            $formattedExpressionKey = $this->formatExpressionKey($expression);
            $datas[$formattedExpressionKey] = $response->toArray()['evaluate'][$key]['nodeValue'];
        }

        return $datas;
    }

    /**
     * Récupération des expressions à mettre dans les paramètres de l'appel à l'API evaluate
     * En fonction du contrat donné en paramètre de la méthode
     * @param string $contrat
     * @return string[]|null
     */
    private function getExpressions(string $contrat): ?array
    {
        return match ($contrat) {
            'CDI', 'apprentissage' => [
                'salarié . rémunération . net . à payer avant impôt',
                'salarié . cotisations . salarié',
                'salarié . coût total employeur'
            ],
            'stage' => [
                'salarié . contrat . stage . gratification minimale'
            ],
            'CDD' => [
                'salarié . rémunération . net . à payer avant impôt',
                'salarié . cotisations . salarié',
                'salarié . coût total employeur',
                'salarié . contrat . CDD . indemnité de fin de contrat'
            ],
            default => null,
        };
    }

    private function formatExpressionKey(string $expression): ?string
    {
        $expressions = [
            'salarié . rémunération . net . à payer avant impôt' => 'Salaire net avec impôt',
            'salarié . cotisations . salarié' => 'Cotisation salariale',
            'salarié . coût total employeur' => 'Coût employeur',
            'salarié . contrat . stage . gratification minimale' => 'Gratification minimale'
        ];

        if (isset($expressions[$expression])) {
            return $expressions[$expression];
        }
        return null;
    }

    /**
     * ---------------------- EXERCICE 3 ------------------------
     */

    #[Route('/api-ouverte-ent-liste', name: 'api_ouverte_ent_liste', methods: ['GET'])]
    public function listeEntreprises(Request $request, SerializerInterface $serializer): Response
    {
        if ($request->getMethod() !== 'GET') {
            return new Response('Méthode : ' . $request->getMethod() . ' non autorisée. Méthode GET uniquement', 405);
        }

        $formatDemande = $request->headers->get('Accept');

        $companies = $this->em->getRepository(Company::class)->findAll();

        if (empty($companies)) {
            return new Response("Aucune entreprise enregistrée", 200);
        }

        if ($formatDemande === 'application/json') {
            // Récupérer et formater la liste des entreprises au format JSON
            $response = new Response($serializer->serialize($companies, 'json'), 200);
            $response->headers->set('Content-Type', 'application/json');
            return $response;
        } elseif ($formatDemande === 'text/csv') {
            // Récupérer et formater la liste des entreprises au format CSV
            foreach ($companies as $company) {
                $rows[] = [
                    'id' => $company->getId(),
                    'raisonSociale' => $company->getRaisonSociale(),
                    'siren' => $company->getSiren(),
                    'siret' => $company->getSiret(),
                    'numero' => $company->getAddress()->getNumero(),
                    'voie' => $company->getAddress()->getVoie(),
                    'code_postale' => $company->getAddress()->getCdp(),
                    'ville' => $company->getAddress()->getVille(),
                    'longitude' =>   $company->getAddress()->getGpsLongitude(),
                    'latitude' =>  $company->getAddress()->getGpsLatitude()
                ];
            }
            $csvData = $this->arrayToCsv($rows);
            $response = new Response($csvData, 200);
            $response->headers->set('Content-Type', 'text/csv');
            return $response;
        } else {
            return new Response('Format non pris en compte', 406);
        }
    }

    /**
     * Transformation des données de $datas en CSV
     * @param array $datas
     * @return bool|string
     */
    private function arrayToCsv(array $datas): bool|string
    {
        $output = fopen('php://tempListeEntreprises', 'w');

        // Écriture de l'en-tête CSV
        $header = ['ID', 'Raison Sociale', 'SIREN', 'SIRET', 'Numéro adresse', 'Voie adresse', 'Code postale', 'Ville', 'Longitude', 'Latitude'];
        fputcsv($output, $header, ';');

        foreach ($datas as $row) {
            // Écrit chaque ligne du tableau dans le fichier CSV
            fputcsv($output, $row, ';');
        }

        rewind($output);

        $csv = stream_get_contents($output);

        fclose($output);

        return $csv;
    }

    #[Route('/api-ouverte-ent', name: 'entreprise_info', methods: ['GET'])]
    public function getInfoBySiren(Request $request): Response
    {
        if ($request->getMethod() !== 'GET') {
            return new Response('Méthode : '. $request->getMethod()  . ' non autorisée. Méthode GET uniquement', 405);
        }

        if ($request->query->get('siren')) {
            return new Response('Bad request le siren doit être définis', 400);
        }
        $siren = $request->query->get('siren');

        $companyExists = $this->em->getRepository(Company::class)->findOneBy(['siren' => $siren]);

        if (!$companyExists) {
            return new Response('Aucune entreprise trouvée avec le siren : ' . $siren, 404);
        } else {
            // Formater les informations de l'entreprise en tableau associatif
            $entrepriseInfo = [
                'raison_sociale' => $companyExists->getRaisonSociale(),
                'adresse_complete' => json_encode($companyExists->getAddress()),
                'siret' => $companyExists->getSiret(),
                'siren' => $companyExists->getSiren()
            ];

            // Répondre au format JSON
            $response = new Response(json_encode($entrepriseInfo), 200);
            $response->headers->set('Content-Type', 'application/json');
            return $response;
        }
    }

    #[Route('/api-ouverte-entreprise', name: 'create_entreprise', methods: ['POST'])]
    public function createEntreprise(Request $request): Response
    {
        if ($request->getMethod() !== 'POST') {
            return new Response('Méthode : '. $request->getMethod()  . ' non autorisée. Méthode POST uniquement', 405);
        }

        $jsonData = $request->getContent();
        $data = json_decode($jsonData, true);

        if (!$data) {
            return new Response('Format JSON invalide', 400);
        }

        $errors = $this->validateData($data);
        if ($errors !== true && is_array($errors)) {
            $responseContent = '';
            foreach ($errors as $errorMessages) {
                $responseContent .= " " . $errorMessages;
            }
            return new Response('Données manquantes ou invalides : ' . $responseContent, 400);
        }

        $companyExists = $this->em->getRepository(Company::class)->findOneBy(['siren' => $data['siren']]);

        if ($companyExists) {
            return new Response('L\'Entreprise avec le siren : ' . $companyExists->getSiren() .' existe déjà', 409);
        }

        // Traitement pour créer l'entreprise
        $company = new Company();
        $company->setRaisonSociale($data['raison_sociale']);
        $company->setSiren($data['siren']);
        $company->setSiret($data['siren']);

        $address = new Address();
        $address->setVille($data['adresse']['ville']);
        $address->setCdp($data['adresse']['code_postale'] ?? null);
        $address->setVoie($data['adresse']['voie'] ?? null);
        $address->setNumero($data['adresse']['num'] ?? null);
        $address->setGpsLatitude($data['adresse']['gps']['latitude'] ?? null);
        $address->setGpsLongitude($data['adresse']['gps']['longitude'] ?? null);
        $company->setAddress($address);

        $this->em->persist($company);
        $this->em->flush();

        return new Response('L\'Entreprise avec le siren : ' . $company->getSiren() .' a été créée avec succès.', 201);
    }

    /**
     * Valider les données JSON reçues
     * @param $data
     * @return bool|array
     */
    private function validateData($data): bool|array
    {
        $validator = Validation::createValidator();

        $expectedKeys = ['siren', 'raison_sociale', 'adresse'];
        $result = $this->checkKeys($data, $expectedKeys, '');
        if ($result !== null) {
            return $result;
        }

        $result = $this->checkMissingKeys($data, $expectedKeys, '');
        if ($result !== null) {
            return $result;
        }

        $adresseExpectedKeys = ['code_postale', 'ville', 'voie', 'num', 'gps'];
        $result = $this->checkKeys($data['adresse'], $adresseExpectedKeys, 'Clé dans adresse ');
        if ($result !== null) {
            return $result;
        }

        $adresseObligatoryKeys = ['ville'];
        $result = $this->checkMissingKeys($data['adresse'], $adresseObligatoryKeys, 'Clé dans adresse ');
        if ($result !== null) {
            return $result;
        }

        if (isset($data['adresse']['gps'])) {
            // Vérification des clés du GPS
            $gpsExpectedKeys = ['latitude', 'longitude'];
            $result = $this->checkKeys($data['adresse']['gps'], $gpsExpectedKeys, 'Clé dans adresse gps ');
            if ($result !== null) {
                return $result;
            }

            $gpsObligatoryKeys = ['latitude', 'longitude'];
            $result = $this->checkMissingKeys($data['adresse']['gps'], $gpsObligatoryKeys, 'Clé dans adresse gps ');
            if ($result !== null) {
                return $result;
            }
        }

        $violations = $validator->validate($data['siren'], [
            new Assert\NotBlank([
                'message' => 'La valeur du siren ne doit pas être vide'
            ]),
            new Assert\Regex([
                'pattern' => '/^\d{9}$/',
                'message' => 'Le Siren doit contenir exactement 9 chiffres',
            ]),
        ]);

        $violations->addAll(
            $validator->validate($data['raison_sociale'], new Assert\NotBlank([
                'message' => 'La valeur de la raison_sociale ne doit pas être vide'
            ]))
        );

        $violations->addAll(
            $validator->validate($data['adresse'], new Assert\NotBlank([
                'message' => 'La valeur adresse ne doit pas être vide'
            ]))
        );

        $violations->addAll(
            $validator->validate($data['adresse']['code_postale'], [
            new Assert\NotBlank([
                'message' => 'La valeur du code_postale ne doit pas être vide'
            ]),
            new Assert\Regex([
                'pattern' => '/^\d{5}$/',
                'message' => 'Le Code postale doit contenir exactement 5 chiffres',
            ]),
        ]));

        $violations->addAll(
            $validator->validate($data['adresse']['ville'], new Assert\NotBlank([
                'message' => 'La valeur de la ville ne doit pas être vide'
            ]))
        );

        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = $violation->getMessage();
            }
            return $errors;
        }

        return true;
    }

    /**
     * On regarde s'il n'y a pas des clefs qui ne devraient pas être dans le tableau $data
     * @param $data
     * @param $expectedKeys
     * @param $messagePrefix
     * @return string[]|null
     */
    private function checkKeys($data, $expectedKeys, $messagePrefix): ?array
    {
        $unexpectedKeys = array_diff(array_keys($data), $expectedKeys);
        if (!empty($unexpectedKeys)) {
            return [$messagePrefix . 'Clé inattendue trouvée : ' . implode(' ', $unexpectedKeys)];
        }
        return null;
    }

    /**
     * On regarde s'il n'y a pas des clefs obligatoires qui ne sont pas présente dans le tableau $data
     * @param $data
     * @param $expectedKeys
     * @param $messagePrefix
     * @return string[]|null
     */
    private function checkMissingKeys($data, $expectedKeys, $messagePrefix): ?array
    {
        $keysMissing = array_diff($expectedKeys, array_keys($data));
        if (!empty($keysMissing)) {
            return [$messagePrefix . 'Clé manquantes : ' . implode(' ', $keysMissing)];
        }
        return null;
    }

    /**
     * ---------------------- EXERCICE 4 ------------------------
     */

    #[Route('/api-protege', name: 'protege_entreprise_path', methods: ['PATCH'])]
    public function patchEntreprise(Request $request): Response
    {
        $headers = $request->headers;

        // Vérification de l'authentification basique
        if (!$this->isAuthenticated($headers)) {
            return new Response('Non authentifié', 401);
        }


        if ($request->getMethod() !== 'PATCH') {
            return new Response('Méthode : '. $request->getMethod()  . ' non autorisée. Méthode PATH uniquement', 405);
        }

        $jsonData = $request->getContent();
        $data = json_decode($jsonData, true);

        if (!$data) {
            return new Response('Format JSON invalide', 400);
        }

        // Vérifier si l'entreprise existe déjà
        $companyExists = $this->em->getRepository(Company::class)->findOneBy(['siren' => $data['siren']]);

        if (!$companyExists) {
            return new Response('L\'Entreprise avec le siren : ' . $data['siren'] .' n\'existe pas', 404);
        }

        // Données possibles envoyées dans la requête
        $raisonSociale = $data['raison_sociale'] ?? null;
        $adresse = $data['adresse'] ?? null;
        $ville = $adresse['ville'] ?? null;
        $codePostale = $adresse['code_postale'] ?? null;
        $voie = $adresse['voie'] ?? null;
        $num = $adresse['num'] ?? null;
        $gps = $adresse['gps'] ?? null;
        $latitude = $gps['latitude'] ?? null;
        $longitude = $gps['longitude'] ?? null;

        if ($raisonSociale !== null) {
            $companyExists->setRaisonSociale($raisonSociale);
        }

        if ($ville !== null) {
            $companyExists->getAddress()->setVille($ville);
        }

        if ($codePostale !== null) {
            if (strlen($codePostale) > 5) {
                return new Response('Le code postal ne peut pas dépasser 5 caractères', 400);
            }
            $companyExists->getAddress()->setCdp($codePostale);
        }

        if ($voie !== null) {
            $companyExists->getAddress()->setVoie($voie);
        }

        if ($num !== null) {
            $companyExists->getAddress()->setNumero($num);
        }

        if ($latitude !== null) {
            $companyExists->getAddress()->setGpsLatitude($latitude);
        }

        if ($longitude !== null) {
            $companyExists->getAddress()->setGpsLongitude($longitude);
        }

        // Enregistrer les modifications
        $this->em->persist($companyExists);
        $this->em->flush();

        // Répondre avec un message et un code HTTP approprié
        return new Response('Entreprise modifiée', 200);
    }

    #[Route('/api-protege', name: 'protege_entreprise_delete', methods: ['DELETE'])]
    public function deleteEntreprise(Request $request): Response
    {
        $headers = $request->headers;

        // Vérification de l'authentification basique
        if (!$this->isAuthenticated($headers)) {
            return new Response('Non authentifié', 401);
        }

        if ($request->getMethod() !== 'DELETE') {
            return new Response('Méthode : '. $request->getMethod()  . ' non autorisée. Méthode DELETE uniquement', 405);
        }

        $jsonData = $request->getContent();

        $data = json_decode($jsonData, true);

        if (!$data || !isset($data['siren'])) {
            return new Response('Format JSON invalide', 400);
        }

        // Vérifier si l'entreprise existe déjà
        $companyExists = $this->em->getRepository(Company::class)->findOneBy(['siren' => $data['siren']]);

        if (!$companyExists) {
            return new Response('L\'Entreprise avec le siren : ' . $data['siren'] .' n\'existe pas', 404);
        }

        $oldSiren = $companyExists->getSiren();
        $this->em->remove($companyExists);
        $this->em->flush();

        return new Response('Entreprise supprimée : ' . $oldSiren, 200);
    }

    private function isAuthenticated($headers): bool
    {
        $authHeader = $headers->get('Authorization');

        if (!$authHeader) {
            return false;
        }

        $authHeaderParts = explode(' ', $authHeader);

        if (count($authHeaderParts) !== 2 || $authHeaderParts[0] !== 'Basic') {
            return false;
        }


        $credentials = base64_decode($authHeaderParts[1]);
        [$username, $password] = explode(':', $credentials);

        return $username === self::USERNAME && $password === self::PASSWORD;
    }
}