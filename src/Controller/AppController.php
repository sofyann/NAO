<?php
/**
 * Created by PhpStorm.
 * User: jeromebutel
 * Date: 10/04/2018
 * Time: 00:25
 */

namespace App\Controller;

use App\Entity\User;
use App\Form\LoginType;
use App\Form\RegistrationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class AppController extends AbstractController
{
    private $em;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->em = $entityManager;
    }

    /**
     * App homepage
     * @Route("/", name="app.homepage")
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function homepage()
    {
        return $this->render('app/index.html.twig');
    }

    /**
     * Register page
     * @Route("/register", name="app.register")
     *
     * @param Request $request
     * @param UserPasswordEncoderInterface $passwordEncoder
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function register(Request $request, UserPasswordEncoderInterface $passwordEncoder)
    {
        if ($this->getUser())
            return $this->redirectToRoute('app.homepage');

        $user = new User();

        $form = $this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // dump($form->getData()); die;

            $password = $passwordEncoder->encodePassword($user, $user->getPassword());
            $user->setPassword($password);
            $this->em->persist($user);
            $this->em->flush();

            /** Send welcome email */
            /** Add flash message */

            return $this->redirectToRoute('app.homepage');
        }

        return $this->render('app/registration.html.twig', [
            'registration_form' => $form->createView(),
        ]);
    }

    /**
     * Login page
     * @Route("/login", name="app.login")
     *
     * @param AuthenticationUtils $utils
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function login(AuthenticationUtils $utils)
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app.homepage');
        }

        $lastUsername = $utils->getLastUsername();
        $lastError = $utils->getLastAuthenticationError();

        $form = $this->createForm(LoginType::class, ['username' => $lastUsername]);

        return $this->render('app/login.html.twig', [
            'form' => $form->createView(),
            'error' => $lastError
        ]);
    }

    /**
     * Logout
     * @Route("/logout", name="app.logout")
     */
    public function logout()
    {
    }
}