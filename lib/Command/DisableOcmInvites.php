<?php

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Contacts\Command;

use OCA\Contacts\AppInfo\Application;
use OCA\Contacts\ConfigLexicon;
use OCA\Contacts\Service\FederatedInvitesService;
use OCP\IAppConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DisableOcmInvites extends Command {
	public function __construct(
		protected IAppConfig $appConfig,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		$this
			->setName('contacts:disable-ocm-invites')
			->setDescription('Disable OCM Invites.');
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$isEnabled = $this->appConfig->getValueBool(Application::APP_ID, ConfigLexicon::OCM_INVITES_ENABLED);
		if (!$isEnabled) {
			$output->writeln('OCM Invites already disabled.');
			return self::SUCCESS;
		}

		$this->appConfig->setValueBool(Application::APP_ID, ConfigLexicon::OCM_INVITES_ENABLED, false);
		$this->appConfig->setValueString('core', FederatedInvitesService::CORE_OCM_INVITE_ACCEPT_DIALOG_KEY, '');
		$output->writeln('OCM Invites successfully disabled.');
		return self::SUCCESS;
	}
}
