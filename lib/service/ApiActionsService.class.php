<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of ApiActionsService
 *
 * @author Baptiste SIMON <baptiste.simon@libre-informatique.fr>
 */
class ApiActionsService
{
    protected $response;
    
    public function setResponse(sfResponse $response)
    {
      $this->response = $response;
      
      return $this;
    }
    
    public function populateAccessControlHeaders()
    {
        $this->response->setHttpHeader('Access-Control-Allow-Origin', '*'); // TODO, precise the authorized sources
        $this->response->setHttpHeader('Access-Control-Allow-Methods', 'POST, GET, PUT, OPTIONS, DELETE');
        $this->response->setHttpHeader('Access-Control-Allow-Headers', 'authorization, x-requested-with, content-type');
        
        return $this;
    }
    
    public function populateCacheControlHeader($lifetime = 3600, $directive = 'private')
    {
        $this->response->setHttpHeader('Cache-Control', $directive.', max-age='.$lifetime);
        $this->response->setHttpHeader('Access-Control-Max-Age', $lifetime);
        
        return $this;
    }
}
