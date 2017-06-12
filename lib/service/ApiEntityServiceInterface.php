<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of ApiEntityServiceInterface
 *
 * @author Baptiste SIMON <baptiste.simon@libre-informatique.fr>
 */
interface ApiEntityServiceInterface
{
    /**
     * Takes a Doctrine_Collection or a Doctrine_Record and transforms
     * it into an array of records as expected by the API definitions
     * or a single record as expected by the API definitions
     * 
     * @param Doctrine_Collection|Doctrine_Record  $mixed
     * @return array
     */
    public function getFormattedEntities($mixed);
    
    /**
     * Takes a Doctrine_Record and transforms
     * it into a single record as expected by the API definitions
     * 
     * @param Doctrine_Record  $mixed
     * @return array
     */
    public function getFormattedEntity(Doctrine_Record $record);
    
    /**
     * Builds a DB query representing the actual API request
     * The Doctrine_Query needs to be designed with aliases similar to table names
     * 
     * @param array    $query  the given API query
     * @param integer  $limit  the maximum number of result expected
     * @param integer  $page   the current requested page (pagination)
     * @return Doctrine_Query
     */
    public function buildQuery(array $query);
    
    /**
     * Builds an initial query for current entity
     * It aims to be implemented by final services
     *
     * @return Doctrine_Query
     */
    public function buildInitialQuery();
    
    /**
     * Builds a 1 dimension associative array representing:
     *  on the left side, the API fields
     *  on the right side, the Doctrine fields
     *
     * @return array
     */
    public function getFieldsEquivalents();
    
    /**
     * Builds a 1 dimension associative array representing:
     *  on the left side, the API fields
     *  on the right side, the Doctrine fields
     * It represents reserved fields, that need not to be
     * publically exposed
     *
     * @return array
     */
    public function getHiddenFieldsEquivalents();
    
    /**
     * Builds an array matching the API operands with DQL operands
     *
     * @return array
     */
    public function getOperandsEquivalents();
}
