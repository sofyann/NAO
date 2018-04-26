<?php
/**
 * Created by PhpStorm.
 * User: jeromebutel
 * Date: 27/03/2018
 * Time: 18:14
 */

namespace App\Controller;

use App\Entity\Observation;
use App\Entity\Species;
use App\Entity\User;
use App\Form\ObservationType;
use Doctrine\ORM\EntityManagerInterface;
use function dump;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

class ObservationController extends AbstractController
{
    /** @var EntityManagerInterface $entityManager */
    private $em;
    private $choices;

    public function __construct($entityManager, $choices)
    {
        $this->em = $entityManager;
        $this->choices = $choices;
    }

    /**
     * List of observations
     * @Route("/observation", name="observation.search")
     */
    public function searchObservation()
    {
        $observationList = $this->em->getRepository(Observation::class)->findAll();

        return $this->render('observation/search.html.twig', [
            'observationList' => $observationList,
        ]);
    }

    /**
     * Add an observation
     * @Route("/observation/add", name="observation.add")
     * @Security("is_granted('IS_AUTHENTICATED_REMEMBERED')")
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function add(Request $request)
    {
        return $this->setObservation($request, new Observation(), false);
    }

    /**
     * Update an observation
     * @Route("/observation/{id}/update", name="observation.update", requirements={"id" = "\d+"})
     * @Security("is_granted('IS_AUTHENTICATED_REMEMBERED')")
     *
     * @param Request $request
     * @param Observation $observation
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function update(Request $request, Observation $observation)
    {
        return $this->setObservation($request, $observation, false);
    }

    /**
     * Delete an observation
     * @Route("observation/{id}/delete", name="observation.delete", requirements={"id" = "\d+"})
     * @Security("is_granted('ROLE_NATURALISTE')")
     *
     * @param Request $request
     * @param Observation $observation
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function delete(Request $request, Observation $observation)
    {
        $form = $this->getDeleteForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $request->isMethod('DELETE')) {
            $this->em->remove($observation);
            $this->em->flush();
            $this->addFlash('notice', 'L\'observation a bien été supprimée !');

            return $this->redirectToRoute('observation.awaiting');
        }

        return $this->render('observation/delete.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("observation/awaiting", name="observation.awaiting")
     * @Security("is_granted('ROLE_NATURALISTE')")
     */
    public function awaiting(){
        $obsAwaiting = $this->em->getRepository('App:Observation')->findAwaitingObservationsOrderedByMoreOlder();
        return $this->render('observation/awaiting.html.twig', [
           'obsAwaiting' => $obsAwaiting
        ]);
    }

    /**
     * @Route("observation/awaiting/{id}", name="observation.validation")
     * @Security("is_granted('ROLE_NATURALISTE')")
     */
    public function validation(Request $request, Observation $observation){
        return $this->setObservation($request, $observation, true);
    }

    /**
     * Private method used to add or edit an observation
     *
     * @param Request $request
     * @param Observation $observation
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    private function setObservation(Request $request, Observation $observation, $validation)
    {
        $user = $this->getUser();
        $isNewObservation = $observation->getId() === null;
        $speciesList = $this->em->getRepository(Species::class)->findBy(
            array(),
            array('id' => 'desc'),
            null,
            null
        );
        $speciesId = 0;
        if (!$isNewObservation){
            $speciesId = $observation->getSpecies()->getId();
        }
        $form = $this->createForm(ObservationType::class, $observation, ['choices_data' => $this->choices]);
//        dump($speciesId);die;
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$validation){
                $observation->setUser($user);
            }

            if (!$isNewObservation) {
                $observation->setUpdatedAt(new \DateTime());
            }

            if (User::ADMIN === $user->getRoles()[0] ||
                User::NATURALISTE === $user->getRoles()[0]) {
                $observation->setValidated(true);
            }

            $this->em->persist($observation);
            $this->em->flush();

            $notice = "L'observation a bien été ";
            $notice .= $isNewObservation ? "ajoutée !" : "mise à jour !";

            $this->addFlash('notice', $notice);

            return $this->redirectToRoute('observation.search');
        }
        return $this->render('observation/set.html.twig', [
            'form' => $form->createView(),
            'isNewObservation' => $isNewObservation,
            'observation' => $observation,
            'speciesList' => $speciesList,
            'validation' => $validation,
            'speciesId' => $speciesId
        ]);
    }

    /**
     * @return \Symfony\Component\Form\FormInterface
     */
    public function getDeleteForm()
    {
        $form = $this->createFormBuilder()->setMethod('DELETE')->getForm();

        return $form;
    }

    /**
     * @Route("/ajax_search_observation", name="observation.ajax.search_observation")
     * @param Request $request
     * @return JsonResponse|Response
     */
    public function ajaxGetObservation(Request $request) {
        $data = $request->request->get('id');
        $results = $this->em->getRepository(Species::class)->findOneBy(["id" => $data]);

        $observations = [];
        /** @var Observation $observation */
        foreach ($results->getObservations() as $observation) {
            /** @var User $user */
            $user = $observation->getUser();
            $userFirstname = ucfirst($user->getFirstname());
            $userLastname = ucfirst($user->getLastname());
            $dateFormat = 'd/m/Y';
            $observedAt = date_format($observation->getObservedAt(), $dateFormat);
            $updatedAt = null;

            if ($observation->getUpdatedAt())
                $updatedAt = date_format($observation->getUpdatedAt(), $dateFormat);

            if ($observation->isValidated()) {
                $observations[] = [
                    'id' => $observation->getId(),
                    'longitude' => $observation->getLongitude(),
                    'latitude' => $observation->getLatitude(),
                    'flightDirection' => $observation->getFlightDirection(),
                    'sex' => $observation->getSex(),
                    'deceased' => $observation->getDeceased(),
                    'deathCause' => $observation->getDeathCause(),
                    'atlasCode' => $observation->getAtlasCode(),
                    'comment' => $observation->getComment(),
                    'observedAt' => $observedAt,
                    'updatedAt' => $updatedAt,
                    'image' => $observation->getImage(),
                    'userFirstname' => $userFirstname,
                    'userLastname' => $userLastname
                ];
            }
        }

        return $this->json($observations);
    }

    /**
     * @Route("/species", name="observation.get_species", methods={"GET"})
     * Get species (async request)
     * @param Request $request
     * @return JsonResponse
     */
    public function getSpecies(Request $request)
    {
        if ($request->isXmlHttpRequest()) {
            $results = $this->em->getRepository(Species::class)->findAll();
            $output = [];
            /** @var Species $result */
            foreach ($results as $result) {
                $output[] = [
                    'id' => $result->getId(),
                    'name' => $result->getName(),
                    'family' => $result->getFamily(),
                    'order' => $result->getOrder()
                ];
            }
            $output = ['count' => count($output), 'items' => $output];
            return $this->json($output);
        }
        throw new NotFoundHttpException();
    }
}
