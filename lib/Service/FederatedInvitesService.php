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

	public function __construct(
		private IAppConfig $appConfig,
		private IClientService $httpClient,
		private IURLGenerator $urlGenerator,
		private LoggerInterface $logger,
	) {
	}

	public function isOcmInvitesEnabled():bool {
		$val = $this->appConfig->getValueString(Application::APP_ID, 'ocm_invites_enabled', FederatedInvitesService::OCM_INVITES_ENABLED_BY_DEFAULT);
		$boolval = (is_string($val) ? filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : (bool)$val);
		return ($boolval === null ? false : $boolval);
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
}
