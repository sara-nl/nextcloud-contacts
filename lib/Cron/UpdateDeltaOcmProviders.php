<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Contacts\Cron;

use OCA\Contacts\ConfigLexicon;
use OCA\Contacts\MeshProvidersCache;
use OCA\Contacts\WayfProvider;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

class UpdateDeltaOcmProviders extends TimedJob {
	// Run every 15 minutes
	private int $expire_time = 60 * 15;

	public function __construct(
		ITimeFactory $time,
		private MeshProvidersCache $cache,
		private WayfProvider $wayfProvider,
	) {
		parent::__construct($time);
		$this->setInterval($this->expire_time);
	}

	#[\Override]
	protected function run($argument) {
		$this->wayfProvider->updateMeshProvidersCache(ConfigLexicon::FEDERATIONS_CACHE_DELTA_EXPIRES);
		$this->cache->setExpirationTime(ConfigLexicon::FEDERATIONS_CACHE_DELTA_EXPIRES, time() + $this->expire_time);
	}
}
