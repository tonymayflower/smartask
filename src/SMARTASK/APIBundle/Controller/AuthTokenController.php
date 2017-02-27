<?php
namespace SMARTASK\APIBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use FOS\RestBundle\Controller\Annotations as Rest; // alias pour toutes les annotations
use SMARTASK\APIBundle\Form\Type\CredentialsType;
use SMARTASK\APIBundle\Entity\AuthToken;
use SMARTASK\APIBundle\Entity\Credentials;
use SMARTASK\UserBundle\Entity\User;

class AuthTokenController extends Controller
{
	/**
	 * @Rest\View(statusCode=Response::HTTP_CREATED, serializerGroups={"auth-token"})
	 * @Rest\Post("/api/auth-tokens")
	 */
	public function postAuthTokensAction(Request $request)
	{
		$credentials = new Credentials();
		$form = $this->createForm(CredentialsType::class, $credentials);

		$form->submit($request->request->all());

		if (!$form->isValid()) {
			return $form;
		}
		$em =$this->getDoctrine()->getManager();
		$user = $em->getRepository('SMARTASKUserBundle:User')->findOneBy(array('email' => $credentials->getLogin()));

		if (!$user) { // L'utilisateur n'existe pas
			return $this->invalidCredentials();
		}

		$encoder = $this->get('security.password_encoder');
		$isPasswordValid = $encoder->isPasswordValid($user, $credentials->getPassword());

		if (!$isPasswordValid) { // Le mot de passe n'est pas correct
			return $this->invalidCredentials();
		}

		$authToken = new AuthToken();
		$authToken->setValue(base64_encode(random_bytes(50)));
		$authToken->setCreatedAt(new \DateTime('now'));
		$authToken->setUser($user);

		$em->persist($authToken);
		$em->flush();

		return $authToken;
	}
	
	/**
	 * @Rest\View(statusCode=Response::HTTP_NO_CONTENT)
	 * @Rest\Delete("/api/auth-tokens/{id}")
	 */
	public function removeAuthTokenAction(Request $request)
	{
		$em = $this->get('doctrine.orm.entity_manager');
		$authToken = $em->getRepository('SMARTASKAPIBundle:AuthToken')
		->find($request->get('id'));
		/* @var $authToken AuthToken */
	
		$connectedUser = $this->get('security.token_storage')->getToken()->getUser();
	
		if ($authToken && $authToken->getUser()->getId() === $connectedUser->getId()) {
			$em->remove($authToken);
			$em->flush();
		} else {
			throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException();
		}
	}

	private function invalidCredentials()
	{
		return \FOS\RestBundle\View\View::create(['message' => 'Invalid credentials'], Response::HTTP_BAD_REQUEST);
	}
}