<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
//require_once __DIR__ . '../../../lib/http/ApiHttpStatus.class.php';

/**
 * Description of apiActions
 *
 * @author Glenn Cavarlé <glenn.cavarle@libre-informatique.fr>
 * @author Baptiste SIMON <baptiste.simon@libre-informatique.fr>
 */
abstract class jsonActions extends sfActions
{
    private $oauthService = 'api_oauth_service';

    /**
     *
     */
    public function preExecute()
    {
        $this->authenticate();
        $this->convertJsonToParameters();
        //disable layout
        $this->setLayout(false);
        //json response header
        $this->getResponse()->setHttpHeader('Content-type', 'application/json');
    }
    
    public function setOAuthService($service = 'api_oauth_service')
    {
        $this->oauthService = $service;
        return $this;
    }

    private function authenticate()
    {
      $request = $this->getRequest();

      $route = $request->getRequestParameters()['_sf_route'];
      $security = $route->getOptions();
      $secure = isset($security['secure']) ? $security['secure'] : true;

      if ( $secure ) {
            /* @var $oauthService ApiAuthService */
            $oauthService = $this->getService($this->oauthService);
            /* @var $sf_user sfBasicSecurityUser */
            $sf_user = sfContext::getInstance()->getUser();

            // check oauth authentification
            if ( !$oauthService->authenticate($request) ) {
                throw new liApiAuthCredentialsException('[OAuth] Invalid authentication credentials');
            }
            // assign user
            $sf_user->signIn($oauthService->getToken()->OcApplication->User, true);

            $cultures = array_keys(sfConfig::get('project_internals_cultures', ['fr' => 'Français']));
            $sf_user->setCulture($cultures[0]);

            // check credentials
            if ( isset($security['credentials']) && !$sf_user->isSuperAdmin() ) {
                $credentials = !is_array($security['credentials']) ? [$security['credentials']] : $security['credentials'];

                $hasCredentials = true;
                foreach ( $credentials as $credential ) {
                    if ( is_array($credential) ) {
                        // OR case
                        $tmp = false;
                        foreach ( $credential as $orcred ) {
                            $tmp = $tmp || $sf_user->hasCredential($orcred);
                        }
                        $hasCredentials = $hasCredentials || $tmp;
                    }
                    else {
                        // AND case
                        $hasCredentials = $hasCredentials && $sf_user->hasCredential($credential);
                    }
                }

                // unauthorized
                if ( !$hasCredentials ) {
                    ApiLogger::log('[Permissions] Invalid authentication credentials. Expected: '.json_encode($credentials,true).', having: '.json_encode($sf_user->getCredentials()).'.', $this);
                    throw new liApiAuthCredentialsException('[Permissions] Invalid authentication credentials');
                }
            }
      }
    }

    private function convertJsonToParameters()
    {
        $contentType = $this->getRequest()->getContentType();
        $content = $this->getRequest()->getContent();

        if ( $contentType == 'application/json' && $content ) {
            $jsonParams = json_decode($content, true);

            $this->getRequest()->setParameter('application/json', $jsonParams);
            foreach ( $jsonParams as $k => $v ) {
                $this->getRequest()->setParameter($k, $v);
            }
        }

    }

    /**
     * Create a json response from an array and a status code
     *
     * @param array|ArrayAccess $data
     * @return string (sfView::NONE)
     */
    protected function createJsonResponse($data, $status = ApiHttpStatus::SUCCESS)
    {
        // type check
        if ( !is_array($data) && !$data instanceof ArrayAccess ) {
            throw new liApiException('Argument 1 passed to jsonActions::createJsonResponse() must implement interface ArrayAccess or be an array, ' . (is_object($data) ? get_class($data) : gettype($data)) . ' given.');
        }

        $this->getResponse()->setStatusCode($status);
        return $this->renderText(json_encode($data, JSON_PRETTY_PRINT) . "\n");
    }

    /**
     * Create an empty response with a status code
     *
     * @param array|ArrayAccess $data
     * @return string (sfView::NONE)
     */
    protected function createEmptyResponse($status = ApiHttpStatus::NO_CONTENT)
    {
        $this->getResponse()->setStatusCode($status);
        return sfView::NONE;
    }

    /**
     * Create an error json response from a message and a status code
     *
     * @param string $message
     * @return string (sfView::NONE)
     */
    protected function createJsonErrorResponse($message, $status = ApiHttpStatus::SERVICE_UNAVAILABLE)
    {
        return $this->createJsonResponse(['code' => $status, 'message' => $message], $status);
    }

    /**
     * Retrieve a service by name
     * The service configurations is in SF_ROOT_DIR/config/services.yml and in SF_PLUGINS_DIR/[plugin]/config/services.yml
     */
    public function getService($aServiceName)
    {
        return $this->getContext()->getContainer()->get($aServiceName);
    }

    /**
     * Retrieve the current service
     *
     * @return ApiEntityService
     */
    public function getMyService()
    {
        throw new liApiNotImplementedException('No "getMyService" defined, and no specific get*() defined neither.');
    }
}
