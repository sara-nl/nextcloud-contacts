<?php

namespace OCA\Contacts;

use Exception;
use OCA\Contacts\AppInfo\Application;
use OCA\Contacts\Service\FederatedInvitesService;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;

/**
 * Class WayfProvider.
 * Provides a basic WAYF (Where Are You From) login implementation build from the list of available mesh providers.
 * 
 */
class WayfProvider implements IWayfProvider {

    public function __construct(
        private IAppConfig $appConfig,
        private IClientService $httpClient,
		private FederatedInvitesService $federatedInvitesService,
        private LoggerInterface $logger,
    )
    {}

    /**
     * Returns all mesh providers.
     * 
     * @return array an array containing all mesh providers
     */
	public function getMeshProviders(): array {
		$meshProvidersServiceUrl = $this->appConfig->getValueString(Application::APP_ID, 'mesh_providers_service');
		if($meshProvidersServiceUrl === '') {
			$this->logger->error("Unable to retrieve mesh providers. App config key 'mesh_providers_service' is not configured.", ['app' => Application::APP_ID]);
			return [];
		}
		try {
			$client = $this->httpClient->newClient();
			$response = $client->get($meshProvidersServiceUrl);
			$responseData = $response->getBody();
			$data = json_decode($responseData, true);

            // TODO implement /invite-accept-dialog endpoint discovery through the https://<providerFQDN>/.well-known/ocm endpoint
            // and add it to the providers if not present.

            return $data;
		} catch(Exception $e) {
			$this->logger->error("Could not retrieve the list of mesh providers. Stacktrace: " . $e->getTraceAsString(), ['app' => Application::APP_ID]);
			return [];
		}
	}

	/**
	 * Returns the WAYF (Where Are You From) login page endpoint to be used in the invitation link.
     * Can be read from the app config key 'wayf_endpoint'.
     * If not set the endpoint the WAYF page implementation of this app is returned.
	 * Note that the invitation link still needs the token and provider parameters, eg. "https://<wayf-page-endpoint>?token=$token&provider=$provider"
	 * @return string the WAYF login page endpoint
	 */
	public function getWayfEndpoint(): string {
		$wayfEndpoint = 'https://' . $this->federatedInvitesService->getProviderFQDN() . '/apps/' . Application::APP_ID . self::WAYF_ROUTE;
		return $this->appConfig->getValueString(Application::APP_ID, 'wayf_endpoint', $wayfEndpoint);
	}
}