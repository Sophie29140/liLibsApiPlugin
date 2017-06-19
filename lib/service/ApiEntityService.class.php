<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of ApiEntityService
 *
 * @author Baptiste SIMON <baptiste.simon@libre-informatique.fr>
 */
abstract class ApiEntityService extends EvenementService implements ApiEntityServiceInterface
{


    /**
     * @var array
     * any field and sub-field have to be represented as "field" and "field.sub-field"
     * any collections is accessed as if it was a single property, the engine does the rest
     * left side: the API representation for datas
     * right side: array containing: 'type' => the type of data expected, 'value' => the path to data in Doctrine_Records
     * type: the type can be 'single', 'collection', null or 'sub-record' (with value null)
     * value: can be null if null is expected
     * for data coming from sub-collection records, the type needs to be set as 'collection.single' for example...
     */
    protected static $HIDDEN_FIELD_MAPPING = [];

    /**
     * @var array
     * any field and sub-field have to be represented as "field" and "field.sub-field"
     * any collections is accessed as if it was a single property, the engine does the rest
     * left side: the API representation for datas
     * right side: array containing: 'type' => the type of data expected, 'value' => the path to data in Doctrine_Records
     * type: the type can be 'single', 'collection' (is useless standalone), null or 'sub-record' (with value null)
     * for data coming from sub-collection records, the type needs to be set as 'collection.single' for example...
     */
    protected static $FIELD_MAPPING = [];



    /**
     *
     * @param Doctrine_Collection|Doctrine_Record $mixed
     *
     * */
    public function getFormattedEntities($mixed)
    {
        $r = [];

        // Doctrine_Record
        if ($mixed instanceof Doctrine_Record)
            $r = $this->getFormattedEntity($mixed);

        // Doctrine_Collection
        if ($mixed instanceof Doctrine_Collection)
            foreach ($mixed as $record)
                $r[] = $this->getFormattedEntity($record);

        return $r;
    }

    public function getFormattedEntity(Doctrine_Record $record)
    {
        if ($record === NULL)
            return [];
        $accessor = new liApiPropertyAccessor;

        $entity = $accessor->toAPI($record, $this->getFieldsEquivalents());

        return $this->postFormatEntity($entity, $record);
    }

    /**
     * Post-process the formatted-as-expected-by-the-API results
     *
     * @param array $entity the pre-formatted entities
     * @return array post-formatted entities
     *
     */
    protected function postFormatEntity(array $entity, Doctrine_Record $record)
    {
        return $entity;
    }

    /**
     * 
     * @param array $query
     * @return Doctrine_Query  prepared for direct execution
     */
    public function buildQuery(array $query)
    {
        if (!isset($query['criteria']))
            $query['criteria'] = [];
        if (!isset($query['sorting']))
            $query['sorting'] = [];
        if (!isset($query['limit']))
            $query['limit'] = 100;
        if (!isset($query['page']))
            $query['page'] = 1;

        $q = $this->buildInitialQuery();
        
        $model = explode(' ', $q->getDqlPart('from')[0])[0];
        $pager = new sfDoctrinePager($model, $query['limit']);

        $this->buildQueryCondition($q, $query['criteria'])
            ->buildQuerySorting($q, $query['sorting']);
            
        $pager->setQuery($q);
        $this->buildQueryPagination($pager, $query['page'])
            ->patchPager($pager);

        $pager->init();
        return $pager->getQuery();
    }
    
    /**
     * Patch a pager
     * This is a dummy function intended to be extended by children
     * If you want to add things to current Doctrine_Query or to the pager, overload this
     *
     * @param sfDoctrinePager $pager  the pager to patch
     * @return $this
     **/
    protected function patchPager(sfDoctrinePager $pager)
    {
        return $this;
    }

    protected function buildQuerySorting(Doctrine_Query $q, array $sorting = [])
    {
        $orderBy = '';
        foreach ( $sorting as $field => $direction ) {
            if (!in_array($field, $this->getFieldsEquivalents()))
                continue;
            $orderBy .= array_search($field, $this->getFieldsEquivalents()) . ' ' . $direction . ' ';
        }

        if ( $orderBy ) {
            $q->orderBy($orderBy);
        }
        
        return $this;
    }

    protected function buildQueryPagination(sfDoctrinePager $pager, $page = 1)
    {
        $pager->setPage($page);
        return $this;
    }

    protected function buildQueryCondition(Doctrine_Query $q, array $criterias = [])
    {
        $fields = array_merge($this->getFieldsEquivalents(), $this->getHiddenFieldsEquivalents());
        $operands = $this->getOperandsEquivalents();

        foreach ( $criterias as $criteria => $search ) {
            if ( isset($fields[$criteria]) && (isset($search['value']) || isset($search['type'])) ) {
                $field = $q->getRootAlias() . '.' . $fields[$criteria]['value'].' ';
                $compare = $operands[$search['type']];
                $dql = '';
                $args = [];
                
                $args = [$search['value']];
                $dql = '?';
                
                if ( is_array($compare) ) {
                    $args = $compare[1]($search['value']);
                    if ( is_array($args) ) {
                        $dql = [];
                        foreach ( $args as $arg ) {
                            $dql[] = '?';
                        }
                        $dql = implode(',', $dql);
                    }
                }
                
                $q->andWhere($field . ' ' . $compare[0] . ' ' . $dql, $args);
            }
        }
        
        return $this;
    }

    public function countResults(array $query)
    {
        return $this->buildQuery($query)->count();
    }

    public function getOperandsEquivalents()
    {
        return [
            'contain' => ['ILIKE', function($s) {
                    return "%$s%";
                }],
            'not contain' => ['NOT ILIKE', function($s) {
                    return "%$s%";
                }],
            'equal' => '=',
            'not equal' => '!=',
            'start with' => ['ILIKE', function($s) {
                    return "$s%";
                }],
            'end with' => ['ILIKE', function($s) {
                    return "%$s";
                }],
            'empty' => ['=', function($s) {
                    return '';
                }],
            'not empty' => ['!=', function($s) {
                    return '';
                }],
            'in' => ['IN', function($s) {
                    return implode(',', $s);
                }],
            'not in' => ['NOT IN', function($s) {
                    return implode(',', $s);
                }],
            'greater' => '>',
            'greater or equal' => '>=',
            'lesser' => '<',
            'lesser or equal' => '<=',
        ];
    }

    private function getDoctrineFlatData($data)
    {
        if (!$data instanceof Doctrine_Collection && !$data instanceof Doctrine_Record)
            throw new liApiException('Doctrine_Collection or Doctrine_Record expected, ' . get_class($data) . ' given on line '.__LINE__.' of '.__FILE__.'.');

        $fct = function(Doctrine_Record $rec) {

            $arr = [];
            foreach ( $rec->getTable()->getColumns() as $colname => $coldef )
            {
                if ( !is_object($rec->$colname) )
                {
                    $arr[$colname] = $rec->$colname;
                }
            }
            return $arr;
        };

        $res = [];
        if ( $data instanceof Doctrine_Collection ) {
            foreach ( $data as $rec ) {
                $res[] = $fct($rec);
            }
        }
        else {
            $res = $fct($data);
        }

        return $res;
    }

    public function getFieldsEquivalents()
    {
        return static::$FIELD_MAPPING;
    }

    public function getHiddenFieldsEquivalents()
    {
        return static::$HIDDEN_FIELD_MAPPING;
    }
      /**
     *
     * @return array    
     */
    public function findAll(array $query)
    {
        $q = $this->buildQuery($query);
        $record = $q->execute();

        return $this->getFormattedEntities($record);
    }

    /**
     *
     * @param int $record_id
     * @return array | null
     */
    public function findOneById($record_id)
    {
        $recDotrineRec = $this->buildQuery([
            'criteria' => [
                'id' => [
                    'value' => $record_id,
                    'type'  => 'equal',
                ],
            ]
        ])
        ->fetchOne();
        if (false === $recDotrineRec)
        {
            return null;
        }

        return $this->getFormattedEntity($recDotrineRec);
    }

}
