<?php

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Contacts\Controller;

use Exception;
use OC\App\CompareVersion;
use OCA\DAV\CardDAV\CardDavBackend;
use OCA\Contacts\AppInfo\Application;
use OCA\Contacts\Db\FederatedInvite;
use OCA\Contacts\Db\FederatedInviteMapper;
use OCA\Contacts\IWayfProvider;
use OCA\Contacts\Service\FederatedInvitesService;
use OCA\Contacts\Service\GroupSharingService;
use OCA\Contacts\Service\SocialApiService;
use OCA\FederatedFileSharing\AddressHandler;
use OCP\App\IAppManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Contacts\IManager;
use OCP\Defaults;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IInitialStateService;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use OCP\Mail\IMailer;
use OCP\Mail\IMessage;
use OCP\Util;
use Psr\Log\LoggerInterface;
use Sabre\DAV\UUIDUtil;

/**
 * Controller for federated invites related routes.
 * 
 */

class FederatedInvitesController extends PageController
{
	public function __construct(
		IRequest $request,
		private AddressHandler $addressHandler,
		private CardDavBackend $cardDavBackend,
		private Defaults $defaults,
		private FederatedInviteMapper $federatedInviteMapper,
		private FederatedInvitesService $federatedInvitesService,
		private IAppManager $appManager,
		private IClientService $httpClient,
		private IConfig $config,
		private IInitialStateService $initialStateService,
		private IFactory $languageFactory,
		private IManager $contactsManager,
		private IMailer $mailer,
		private IUserSession $userSession,
		private IWayfProvider $wayfProvider,
		private SocialApiService $socialApiService,
		private ITimeFactory $timeFactory,
		private CompareVersion $compareVersion,
		private GroupSharingService $groupSharingService,
		private IL10N $il10,
		private IURLGenerator $urlGenerator,
		private IUserManager $userManager,
		private LoggerInterface $logger,
	) {
		parent::__construct(
			$request,
			$federatedInvitesService,
			$config,
			$initialStateService,
			$languageFactory,
			$userSession,
			$socialApiService,
			$appManager,
			$compareVersion,
			$groupSharingService,
			$logger,
		);
	}

	/**
	 * Returns all open (not yet accepted) invites.
	 * 
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getInvites(): JSONResponse {
		$_invites = $this->federatedInviteMapper->findOpenInvitesByUid($this->userSession->getUser()->getUID());
		$invites = [];
		foreach ($_invites as $invite) {
			if ($invite instanceof FederatedInvite) {
				array_push(
					$invites, 
					$invite->jsonSerialize()
				);
			}
		}
		return new JSONResponse($invites, Http::STATUS_OK);
	}

	/**
	 * Deletes the invite with the specified token.
	 *
	 * @param string $token the token of the invite to delete
	 * @return JSONResponse with data signature ['token' | 'message'] - the token of the deleted invitation or an error message in case of error
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function deleteInvite(string $token): JSONResponse {
		if(!isset($token)) {
			return new JSONResponse(['message' => 'Token is required'], Http::STATUS_BAD_REQUEST);
		}
		try {
			$uid = $this->userSession->getUser()->getUID();
			$invite = $this->federatedInviteMapper->findInviteByTokenAndUidd($token, $uid);
			$this->federatedInviteMapper->delete($invite);
			return new JSONResponse(['token' => $token], Http::STATUS_OK);
		} catch(DoesNotExistException $e) {
			$this->logger->error("Could not find invite with token=$token for user with uid=$uid . Stacktrace: " . $e->getTraceAsString(), ['app' => Application::APP_ID]);
			return new JSONResponse(['message' => 'An unexpected error occurred trying to delete the invite'], Http::STATUS_NOT_FOUND);
		} catch (Exception $e) {
			$this->logger->error("An unexpected error occurred deleting invite with token=$token. Stacktrace: " . $e->getTraceAsString(), ['app' => Application::APP_ID]);
			return new JSONResponse(['message' => 'An unexpected error occurred trying to delete the invite'], Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Sets the token and provider states which triggers display of the invite accept dialog.
	 * 
	 * @param string $token
	 * @param string $provider
	 * @return TemplateResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function inviteAcceptDialog(string $token = "", string $provider = ""): TemplateResponse {
		$this->initialStateService->provideInitialState(Application::APP_ID, 'inviteToken', $token);
		$this->initialStateService->provideInitialState(Application::APP_ID, 'inviteProvider', $provider);
		$this->initialStateService->provideInitialState(Application::APP_ID, 'acceptInviteDialogUrl', FederatedInvitesService::OCM_INVITE_ACCEPT_DIALOG_ROUTE);

		return $this->index();
	}

	/**
	 * Creates an invitation to exchange contact info for the user with the specified uid.
	 * 
	 * @param string $emailAddress the recipient email address to send the invitation to
	 * @param string $message the optional message to send with the invitation 
	 * @return JSONResponse with data signature ['token' | 'message'] - the token of the invitation or an error message in case of error
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function createInvite(string $email, string $message): JSONResponse {
		if(!isset($email)) {
			return new JSONResponse(['message' => 'Recipient email is required'], Http::STATUS_BAD_REQUEST);
		}

		// check for existing open invite for the specified email and return 'invite exists'
		$uid = $this->userSession->getUser()->getUID();
		$existingInvites = $this->federatedInviteMapper->findOpenInvitesByRecipientEmail(
			$uid,
			$email,
		);
		if(count($existingInvites) > 0) {
			$this->logger->error("An open invite already exists for user with uid $uid and for recipient email $email", ['app' => Application::APP_ID]);
			return new JSONResponse(['message' => $this->il10->t('An open invite already exists.')], Http::STATUS_CONFLICT);
		}

		$invite = new FederatedInvite();
		$invite->setUserId($uid);
		$token = UUIDUtil::getUUID();
		$invite->setToken($token);
		// created-/expiredAt in seconds
		$invite->setCreatedAt($this->timeFactory->getTime());
		// TODO get expiration period from config
		// For now take 30 days
		$invite->setExpiredAt($invite->getCreatedAt() + 2592000);
		$invite->setRecipientEmail($email);
		$invite->setAccepted(false);
		try {
			$this->federatedInviteMapper->insert($invite);
		} catch(Exception $e) {
			$this->logger->error("An unexpected error occurred saving a new invite. Stacktrace: " . $e->getTraceAsString(), ['app' => Application::APP_ID]);
			return new JSONResponse(['message' => 'An unexpected error occurred creating the invite.'], Http::STATUS_NOT_FOUND);
		}

		/** @var DataResponse */
		$response = $this->sendEmail($token, $email, $message);
		if($response->getStatus() !== Http::STATUS_OK) {
			// delete invite in case sending the email has failed
			try {
				$this->federatedInviteMapper->delete($invite);
			} catch(Exception $e) {
				$this->logger->error("An unexpected error occurred deleting invite with token $token. Stacktrace: " . $e->getTraceAsString(), ['app' => Application::APP_ID]);
				return new JSONResponse(['message' => 'An unexpected error occurred creating the invite.'], Http::STATUS_NOT_FOUND);
			}
		}

		// the new invite url
		$inviteUrl = $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->linkToRoute('contacts.page.index') . 'ocm-invites/' . $email
		);
		return new JSONResponse(['invite' => $inviteUrl], Http::STATUS_OK);
	}

	/**
	 * Accepts the invite and creates a new contact from the inviter.
	 * On success the user is redirected to the new contact url.
	 * 
	 * @param string $token the token of the invite
	 * @param string $provider the provider of the sender of the invite 
	 * @return JSONResponse with data signature ['contact' | 'message'] - the new contact url or an error message in case of error
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function inviteAccepted(string $token = "", string $provider = ""): JSONResponse {
		if ($token === "" || $provider === "") {
			$this->logger->error("Both token and provider must be specified. Received: token=$token, provider=$provider", ['app' => Application::APP_ID]);
			return new JSONResponse(['message' => 'Both token and provider must be specified.'], Http::STATUS_NOT_FOUND);
		}
		try {
			// accept the invite by calling provider OCM /invite-accepted
			// this returns a response with the following data signature: ['userID', 'email', 'name']
			// @link https://cs3org.github.io/OCM-API/docs.html?branch=v1.1.0&repo=OCM-API&user=cs3org#/paths/~1invite-accepted/post
			$localUser = $this->userSession->getUser();
			$recipientProvider = $this->federatedInvitesService->getProviderFQDN();
			$client = $this->httpClient->newClient();
			$responseData = null;
			$response = $client->post(
				// TODO take provider as is, or do some verification ??
				"https://$provider/ocm/invite-accepted",
				[
					'body' =>
					[
						'recipientProvider' => $recipientProvider,
						'token' => $token,
						'userId' => $localUser->getUID(),
						'email' => $localUser->getEMailAddress(),
						'name' => $localUser->getDisplayName(),
					],
					'connect_timeout' => 10,
				]
			);
			$responseData = $response->getBody();
			$data = json_decode($responseData, true);

			// Creating a contact does not return a specific 'contact already exists' error,
			// so we must check that explicitly
			$cloudId = $data['userID'] . "@" . $this->addressHandler->removeProtocolFromUrl($provider);
			$searchResult = $this->contactsManager->search($cloudId, ['CLOUD']);
			if (count($searchResult) > 0) {
				$this->logger->info("Contact with cloud id " . $cloudId . " already exists.", ['app' => Application::APP_ID]);
				return new JSONResponse(['message' => "Contact with cloudID $cloudId already exists."], Http::STATUS_CONFLICT);
			}

			$newContact = $this->socialApiService->createFederatedContact(
				// the ocm address: nextcloud cloud id format
				$cloudId,
				$data['email'],
				$data['name'],
				$localUser->getUID(),
			);
			if (!isset($newContact)) {
				$this->logger->error("Error accepting invite (token=$token, provider=$provider): Could not create new contact.", ['app' => Application::APP_ID]);
				return new JSONResponse(['message' => 'An unexpected error occurred trying to accept invite: could not create new contact'], Http::STATUS_NOT_FOUND);
			}
			$this->logger->info("Created new contact with UID: " . $newContact['UID'] . " for user with UID: " . $localUser->getUID(), ['app' => Application::APP_ID]);

			$contact = $newContact['UID'] . "~" . CardDavBackend::PERSONAL_ADDRESSBOOK_URI;
			$url = $this->urlGenerator->getAbsoluteURL(
				$this->urlGenerator->linkToRoute('contacts.page.index') . $this->il10->t('All contacts') . '/' . $contact
			);
			return new JSONResponse(['contact' => $url], Http::STATUS_OK);
		} catch (\GuzzleHttp\Exception\RequestException $e) {
			$this->logger->error("/invite-accepted returned an error: " . print_r($responseData, true), ['app' => Application::APP_ID]);
			/**
			 * 400: Invalid or non existing token
			 * 409: Invite already accepted
			 */
			$statusCode = $e->getCode();
			switch ($statusCode) {
				case Http::STATUS_BAD_REQUEST:
					return new JSONResponse(['message' => 'Invalid, non existing or expired token'], $e->getCode());
				case Http::STATUS_CONFLICT:
					return new JSONResponse(['message' => 'Invite already accepted'], $e->getCode());
			}
			$this->logger->error("An unexpected error occurred accepting invite with token=$token and provider=$provider. Stacktrace: " . $e->getTraceAsString(), ['app' => Application::APP_ID]);
			return new JSONResponse(['message' => 'An unexpected error occurred trying to accept invite.'], Http::STATUS_NOT_FOUND);
		} catch (Exception $e) {
			$this->logger->error("An unexpected error occurred accepting invite with token=$token and provider=$provider. Stacktrace: " . $e->getTraceAsString(), ['app' => Application::APP_ID]);
			return new JSONResponse(['message' => 'An unexpected error occurred trying to accept invite'], Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Accepts the invite and creates a new contact from the inviter.
	 * On success the user is redirected to the new contact url.
	 * 
	 * @param string $token the token of the invite
	 * @param string $provider the provider of the sender of the invite 
	 * @return TemplateResponse the WAYF page
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	public function wayf(string $token = "", string $provider = ""): TemplateResponse {
        try {
			$providers = $this->wayfProvider->getMeshProviders();
			$params = ['providers' => $providers, 'token' => $token, 'provider' => $provider];
			$template = new TemplateResponse('contacts', 'wayf', $params, TemplateResponse::RENDER_AS_BLANK);
            return $template;

        } catch (Exception $e) {
            $this->logger->error($e->getMessage() . ' Trace: ' . $e->getTraceAsString(), ['app' => Application::APP_ID]);
			$params = ['error' => 'An error has occurred'];
			$template = new TemplateResponse('contacts', 'wayf', $params, TemplateResponse::RENDER_AS_BLANK);
            return $template;
        }
	}

	/**
	 * @param string $token the invite token
	 * @param string $address the recipient email address to send the invitation to
	 * @param string $message the optional message to send with the invitation
	 * @return JSONResponse
	 */
	private function sendEmail(string $token, string $address, string $message): JSONResponse {
		/** @var IMessage */
		$email = $this->mailer->createMessage();
		if(!$this->mailer->validateMailAddress($address)) {
			$this->logger->error("Could not sent invite, invalid email address '$address'", ['app' => Application::APP_ID]);
			return new JSONResponse(['message' => 'Recipient email address is invalid'], Http::STATUS_NOT_FOUND);
		}
		$email->setTo([$address]);

		// TODO do we want to share the inviter's name ??
		$instanceName = $this->defaults->getName();
		$initiatorDisplayName = $this->userSession->getUser()->getDisplayName();
		$senderName = $this->il10->t(
			'%1$s via %2$s',
			[
				$initiatorDisplayName,
				$instanceName
			]
		);
		$email->setFrom([Util::getDefaultEmailAddress($instanceName) => $senderName]);

		$fqdn = $this->federatedInvitesService->getProviderFQDN();
		$wayfEndpoint = $this->wayfProvider->getWayfEndpoint();
		$inviteLink = "$wayfEndpoint?token=$token&provider=$fqdn";

		$body = "$message\nThe invite link: $inviteLink";
		$email->setPlainBody($body);

		/** @var string[] */
		$failedRecipients = $this->mailer->send($email);
		if (!empty($failedRecipients)) {
			$this->logger->error("Could not sent invite to '$address'", ['app' => Application::APP_ID]);
			return new JSONResponse(['message' => "Could not sent invite to '$address'"], Http::STATUS_NOT_FOUND);
		}

		return new JSONResponse([], Http::STATUS_OK);
	}

}
