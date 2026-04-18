<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Contacts\Command;

use OCA\Contacts\AppInfo\Application;
use OCA\Contacts\ConfigLexicon;
use OCP\IAppConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SetMeshProvidersService extends Command {

	public function __construct(
		protected IAppConfig $appConfig,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		$this
			->setName('contacts:set-mesh-providers-service')
			->setDescription('Set the mesh provider service URLs for OCM discovery.');
		$this->addArgument(
			'mesh-providers-service',
			InputArgument::REQUIRED,
			'Space-separated list of OCM discovery service URLs'
		);
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$discoveryServices = (string)$input->getArgument('mesh-providers-service');
		$this->appConfig->setValueString(Application::APP_ID, ConfigLexicon::MESH_PROVIDERS_SERVICE, $discoveryServices);
		$output->writeln('OCM discovery services successfully configured.');
		return self::SUCCESS;
	}
}
