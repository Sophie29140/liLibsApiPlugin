<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of liApiExceptionCatcherFilter
 *
 * @author Glenn Cavarlé <glenn.cavarle@libre-informatique.fr>
 */
class liApiExceptionCatcherFilter
{

    public function execute($filterChain)
    {
        // use this filter only if the context expects "JSON" answers
        if ( sfContext::hasInstance() ) {
            if ( $aEntry = sfContext::getInstance()->getActionStack()->getLastEntry() ) {
                if (!( $aEntry->getActionInstance() instanceof jsonActions )) {
                    $filterChain->execute();
                    return;
                }
            }
        }
        
        try {
           
            $filterChain->execute();
            
        } catch ( liApiAuthException $e ) {
            
            ApiLogger::log($e->getMessage());
            $r = $this->getResponse();
            $r->setStatusCode(ApiHttpStatus::UNAUTHORIZED);
        
            $r->setContent(json_encode([
                    'code'=> ApiHttpStatus::UNAUTHORIZED,
                    'message' => $e->getMessage()
                ], JSON_PRETTY_PRINT));
        
        } catch ( liApiNotImplementedException $e ) {
        
            ApiLogger::log($e->getMessage());
            $r = $this->getResponse();
            $r->setStatusCode(ApiHttpStatus::NOT_IMPLEMENTED);
        
            $r->setContent(json_encode([
                    'code'=> ApiHttpStatus::NOT_IMPLEMENTED,
                    'message' => $e->getMessage()
                ], JSON_PRETTY_PRINT));
        
        } catch ( liApiException $e ) {
        
            ApiLogger::log($e->getMessage());
            $r = $this->getResponse();
            $r->setStatusCode(ApiHttpStatus::SERVICE_UNAVAILABLE);
        
            $r->setContent(json_encode([
                    'code'=> ApiHttpStatus::SERVICE_UNAVAILABLE ,
                    'message' => $e->getMessage()
                ], JSON_PRETTY_PRINT));
        } catch ( Exception $e ) {
        
            ApiLogger::log($e->getMessage());
            $r = $this->getResponse();
            $r->setStatusCode(ApiHttpStatus::INTERNAL_SERVER_ERROR);
        
            $r->setContent(json_encode([
                    'code'=> ApiHttpStatus::INTERNAL_SERVER_ERROR ,
                    'message' => $e->getMessage()
                ], JSON_PRETTY_PRINT));
        }
    }

    private function getResponse()
    {
        $response = sfContext::getInstance()->getResponse();
        if ( null === $response ) {
            $response = new sfWebResponse(sfContext::getInstance()->getEventDispatcher());
            sfContext::getInstance()->setResponse($response);
        }
        $response->setHttpHeader('Content-type', 'application/json');
        return $response;
    }
}
