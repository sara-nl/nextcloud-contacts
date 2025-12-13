<?php

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Contacts\Service;

use OCA\Contacts\AppInfo\Application;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

class FederatedInvitesService {

	// Is OCM invites capability enabled by default ?
	private const OCM_INVITES_ENABLED_BY_DEFAULT = false;
	// The default route of the invite accept dialog
	public const OCM_INVITE_ACCEPT_DIALOG_ROUTE = '/ocm/invite-accept-dialog';
	// The default expiration period of a new invite in seconds, ie. 30 days
	private const INVITE_EXPIRATION_PERIOD_SECONDS = 2592000;

	public function __construct(
		private IAppConfig $appConfig,
		private IClientService $httpClient,
		private IURLGenerator $urlGenerator,
		private LoggerInterface $logger,
	) {
	}

	public function isOcmInvitesEnabled(): bool {
		return $this->appConfig->getValueBool(Application::APP_ID, 'ocm_invites_enabled', FederatedInvitesService::OCM_INVITES_ENABLED_BY_DEFAULT);
	}

	/**
	 * Whether email is optional when creating invites (default: false = email required)
	 */
	public function isOptionalMailEnabled(): bool {
		return $this->appConfig->getValueBool(Application::APP_ID, 'ocm_invites_optional_mail', false);
	}

	/**
	 * Whether CC sender checkbox is available (default: true)
	 */
	public function isCcSenderEnabled(): bool {
		return $this->appConfig->getValueBool(Application::APP_ID, 'ocm_invites_cc_sender', true);
	}

	/**
	 * Whether the encoded copy button is shown (default: false)
	 */
	public function isEncodedCopyButtonEnabled(): bool {
		return $this->appConfig->getValueBool(Application::APP_ID, 'ocm_invites_encoded_copy_button', false);
	}

	/**
	 * Returns all OCM invites config flags for frontend consumption
	 */
	public function getOcmInvitesConfig(): array {
		return [
			'optionalMail' => $this->isOptionalMailEnabled(),
			'ccSender' => $this->isCcSenderEnabled(),
			'encodedCopyButton' => $this->isEncodedCopyButtonEnabled(),
		];
	}

	/**
	 * Returns the provider's server FQDN.
	 * @return string the FQDN
	 */
	public function getProviderFQDN(): string {
		$serverUrl = $this->urlGenerator->getAbsoluteURL('/');
		$fqdn = parse_url($serverUrl)['host'];
		return $fqdn;
	}

	/**
	 * Returns the expiration date.
	 * @param int $creationDate
	 * @return int the expiration date
	 */
	public function getInviteExpirationDate(int $creationDate): int {
		return $creationDate + self::INVITE_EXPIRATION_PERIOD_SECONDS;
	}
}
