<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\Commentaire;
use App\Entity\User;
use App\Repository\ArticleRepository;
use App\Repository\CommentaireRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

class ArticleController extends AbstractController
{
    #[Route('/article', name: 'app_article')]
    public function index(ArticleRepository $articleRepository): Response
    {
        $articles=$articleRepository->findAll();
        return $this->render('article/home.html.twig', ['articles'=>$articles]);
    }



    #[Route('/article/new', name: 'app_article_create')]
    #[Route('/article/edit/{id}', name: 'app_article_edit')]
    public function create(SessionInterface $session,Article $article=null,
                           Request $request ,EntityManagerInterface $manager,
                            UserRepository $userRepository): Response
    {
        $etat=1;
        if(!$article){
            $article=new Article();
            $etat=0;
        }
        $form=$this->createFormBuilder($article)
            ->add('titre')
            ->add('soustitre')
            ->add('contenu', TextareaType::class)
            ->add('image')
            ->getForm();
        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()){
            $user=$userRepository->find($session->get("idUser"));
            $article->setIdUser($user);
            $manager->persist($article);
            $manager->flush();
            return $this->redirectToRoute('app_article_details',['id'=>$article->getId()]);
        }
        return $this->render('article/create.html.twig',['formCreation'=>$form->createView() ,'etatButton'=>$etat]);
    }



    #[Route('/article/delete/{id?0}', name: 'app_article_delete')]
    public function delete(Article $article,ArticleRepository $articleRepository): Response
    {
        $articleRepository->remove($article,true);
        return $this->redirectToRoute('app_article' , ['articles'=>$articleRepository->findAll()]);
    }


    #[Route('/redirect/{id}', name: 'app_redirect')]
    public function direction(Article $article): Response
    {
        dump($article);
        return $this->render('article/delete.html.twig',['id'=>$article->getId()]);
    }


    #[Route('/article/comment/{id}', name: 'app_article_comment')]
    public function comment(SessionInterface $session,Article $article,Request $request,
                            Commentaire $commentaire=null,
                            CommentaireRepository $commentaireRepository,
                            EntityManagerInterface $manager,
                            UserRepository $userRepository): Response
    {
        $commentaire=new Commentaire();
        $form=$this->createFormBuilder($commentaire)
            ->add('contenu', TextareaType::class)
            ->getForm();
        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()){
            $user=$userRepository->find($session->get("idUser"));
            $commentaire->setIdUser($user);
            $commentaire->setIdArticle($article);
            $commentaire->setCreatedAt(new \DateTime());
            $manager->persist($commentaire);
            $manager->flush();
            return $this->redirectToRoute('app_article_details',['id'=>$article->getId()]);
        }
        return $this->render('article/comment.html.twig',['formComment'=>$form->createView()]);
    }

    #[Route('/article/login', name: 'app_article_loging')]
    public function loging(User $user =null,UserRepository $userRepository,
                           Request $request, EntityManagerInterface $manager,
                            SessionInterface $session): Response
    {
        $user=new User();
        $message=null;
        $id=null;
        $form=$this->createFormBuilder($user)
            ->add('email')
            ->add('mdp', PasswordType::class)
            ->getForm();
        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()){
            $userfindRepo=$userRepository->findBy(['email'=>$user->getEmail() ]);
            $userfind=$userfindRepo[0];
            if($userfind!=null && password_verify($user->getMdp(),$userfind->getMdp())){
                $id=$userfind->getId();
                $session->set('idUser', $id);
                return $this->redirectToRoute('app_article', ['idUser'=>$id]);
            }else{
                $message="Attention !! Email ou mot de passe est incorrecte";
                return $this->render('article/loging.html.twig', ['formLogin'=>$form->createView(), 'message'=>$message]);
            }
        }
        return $this->render('article/loging.html.twig', ['formLogin'=>$form->createView(),'message'=>$message]);
    }
    #[Route('/article/logout/{id}', name: 'app_article_logout')]
    public function logout(SessionInterface $session): Response
    {
        $session->remove('idUser');
        return $this->redirectToRoute('app_article_loging');
    }
    #[Route('/article/register', name: 'app_article_register')]
    public function register(User $user =null, Request $request, EntityManagerInterface $manager, UserRepository $userRepository): Response
    {
        $user=new User();
        $message=null;
        $form=$this->createFormBuilder($user)
            ->add('nom')
            ->add('prenom')
            ->add('email')
            ->add('mdp' , PasswordType::class)
            ->getForm();
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()){
            dump($user);
            $avecHachageMdp=password_hash($user->getMdp(), PASSWORD_BCRYPT);
            $user->setMdp($avecHachageMdp);
            $users=$userRepository->findBy(['email'=>$user->getEmail()]);
            if($users!=null){
                $message="Attention !! Email existe déjà";
                return $this->render('article/inscription.html.twig', ['formInscription'=>$form->createView() , 'message'=>$message]);
            }else{
                $manager->persist($user);
                $manager->flush();
                return $this->redirectToRoute('app_article_loging');
            }
        }
        return $this->render('article/inscription.html.twig', ['formInscription'=>$form->createView() , 'message'=>$message]);
    }

    #[Route('/article/searche', name: 'app_article_searche')]
    public function searche( Request $request, ArticleRepository $articleRepository,
                            EntityManagerInterface $entityManager): Response
    {
        $form = $this->createFormBuilder()
            ->add('chercher', TextType::class)
            ->getForm();
        $form->handleRequest($request);
        $articlesResult=null;
        $qb = $entityManager->createQueryBuilder();
        if ($form->isSubmitted() && $form->isValid()) {
            //$articlesResult=$articleRepository->findBy(['LOWER(titre)'=> 'LOWER(%'.$form->get('chercher')->getData().'%)']);
            $qb->select('a')
                ->from(Article::class, 'a')
                ->leftJoin(User::class, 'u')
                ->where($qb->expr()->like('LOWER(a.titre)', ':searchTerm1'))
                ->orWhere($qb->expr()->orX(
                    $qb->expr()->like('LOWER(u.nom)', ':searchTerm2'),
                    $qb->expr()->like('LOWER(u.prenom)', ':searchTerm2')
                ))
                ->setParameter('searchTerm1', '%' . strtolower($form->get('chercher')->getData()) . '%')
                ->setParameter('searchTerm2', '%' . strtolower($form->get('chercher')->getData()) . '%');
            $articlesResult=$qb->getQuery()->getResult();
            if($articlesResult!=null){
                return $this->render('article/searche.html.twig', ['formSearche'=>$form->createView(),'articleResult'=>$articlesResult]);
            }else{
                return $this->render('article/searche.html.twig', ['formSearche'=>$form->createView(),'articleResult'=>$articlesResult]);
            }
        }
        return $this->render('article/searche.html.twig', ['formSearche'=>$form->createView(),'articleResult'=>$articlesResult]);
    }

    #[Route('/article/{id}', name: 'app_article_details')]
    public function details(Article $article,Commentaire $commentaire=null,
                            CommentaireRepository $commentaireRepository,
                            UserRepository $userRepository): Response
    {
        $commentaire=$commentaireRepository->findBy(['idArticle'=>$article]);
        $user=$userRepository->find($article->getIdUser());
        $userNom=$user->getNom();
        $userPrenom=$user->getPrenom();
        return $this->render('article/details.html.twig',['article'=>$article , 'commentaires'=>$commentaire, "nomAuteur"=>$userNom,"prenomAuteur"=>$userPrenom]);
    }


    #[Route('/article/account/{id}', name: 'app_article_account')]
    public function account(User $user,SessionInterface $session, Request $request,
                            EntityManagerInterface $manager): Response
    {
        $form=$this->createFormBuilder($user)
            ->add('nom')
            ->add('prenom')
            ->getForm();
        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()){
            $manager->flush();
            return $this->redirectToRoute('app_article',['idUser'=>$session->get("idUser")]);
        }
        return $this->render('article/account.html.twig', ['formAccount'=>$form->createView()]);
    }
}
