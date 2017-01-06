<?php

namespace AdminBundle\Controller;

use AppBundle\Base\BaseController;
use BaseBundle\Entity\User;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Validator\Constraints;

/**
 * @Route("/users")
 * @Security("has_role('ROLE_ADMIN')")
 */
class UsersController extends BaseController
{
    /**
     * @Route("/", name="admin_users")
     * @Template()
     */
    public function listAction(Request $request)
    {
        $filter = $request->query->get('filter');

        $qb = $this
           ->getManager()
           ->createQueryBuilder()
           ->select('u')
           ->from(User::class, 'u')
        ;

        if ($filter) {
            $qb
               ->where('u.nickname LIKE :criteria OR u.contact LIKE :criteria')
               ->setParameter('criteria', '%'.$filter.'%')
            ;
        }

        return [
            'pager'  => $this->getPager($request, $qb),
            'me'     => $this->getUser()->getId(),
        ];
    }

    /**
     * @Route("/toggle/{token}", name="admin_users_toggle")
     * @Template()
     */
    public function toggleAction(Request $request, $token)
    {
        if ($token !== $this->get('security.csrf.token_manager')->getToken('administration')->getValue()) {
            throw new InvalidCsrfTokenException('Invalid CSRF token');
        }

        $this->get('admin.storage.user')->toggleAdmin(
           intval($request->request->get('id'))
        );

        return new Response();
    }

    /**
     * @Route("/edit/contact/{id}", name="admin_users_edit_contact")
     * @Template("AdminBundle::_editOnClick.html.twig")
     */
    public function _editContactAction(Request $request, $id)
    {
        $manager = $this->getManager('BaseBundle:User');

        $entity = $manager->findOneById($id);
        if (!$entity) {
            throw $this->createNotFoundException();
        }

        $form = $this
           ->createNamedFormBuilder("edit-contact-{$id}", Type\FormType::class, $entity)
           ->add('contact', Type\EmailType::class, [
               'label'       => "admin.users.edit.contact",
               'constraints' => [
                   new Constraints\NotBlank(),
                   new Constraints\Email(),
               ],
           ])
           ->add('submit', Type\SubmitType::class, [
               'label' => "admin.users.",
           ])
           ->getForm()
           ->handleRequest($request)
        ;

        if ($form->isSubmitted() && $form->isValid()) {
            $this
                ->get('doctrine')
                ->getManager()
                ->persist($entity)
                ->flush()
            ;

            return [
                'text' => $entity->getContact(),
            ];
        }

        return [
            'form' => $form->createView(),
        ];
    }
}
