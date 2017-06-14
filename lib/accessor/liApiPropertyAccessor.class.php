<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of liApiPropertyAccessor
 *
 * @author Baptiste SIMON <baptiste.simon@libre-informatique.fr>
 */
class liApiPropertyAccessor
{
    /**
     * Updates a Doctrine_Record with data coming from an array of the API structure
     *
     * @param array            $entity  the source of data
     * @param Doctrine_Record  $record  the target
     * @param array            $equiv   the equivalence between DB & API fields
     * @return Doctrine_Record the updated Doctrine_Record
     */
    public function toRecord(array $entity, Doctrine_Record $record, array $equiv)
    {
        foreach ( $this->reverseEquiv($equiv) as $db => $api ) {
            if ( !isset($entity[$api['value']]) ) {
                continue;
            }

            $value = $this->getAPIValue($entity, $api['value']);
            $this->setRecordValue($record, preg_replace('/^!/', '', $db), preg_match('/^!/', $db) == 0 ? $value : !$value);

            //$this->setRecordValue($record, $db, $this->getAPIValue($entity, $api));
        }
        return $record;
    }

    /**
     * Converts a Doctrine_Record into an array representing the API expected structure
     *
     * @param Doctrine_Record $record  the source of data
     * @param array           $equiv   the equivalence between DB & API fields
     * @return array          an array representing the API expected structure populated
     */
    public function toAPI(Doctrine_Record $record, array $equiv)
    {
        // init
        $entity = [];

        // populate
        foreach ( $equiv as $api => $db ) {
            if ( is_array($db) ) {
                $type = explode('.', $db['type']);
                $lastType = array_pop($type);
                $bool = $db['value'] ? strpos($db['value'], '!') !== 0 : true;
                if ($db['value'] !== null) {
                    $db['value'] = preg_replace('/^!/', '', $db['value']);
                }

                switch ( $lastType ) {
                    case 'sub-record':
                        $this->setAPIValue($entity, $api, new ArrayObject, $type);
                        break;
                    case null:
                        $this->setAPIValue($entity, $api, null, $type);
                        break;
                    case 'single':
                        $this->setAPIValue($entity, $api, $this->getRecordValue($record, $db['value']), $type, $bool);
                        break;
                    case 'collection':
                        $this->setAPIValue($entity, $api, $db['value'] === NULL ? [] : $this->getRecordValue($record, $db['value']), $type, $bool);
                        break;
                    case 'value':
                        $this->setAPIValue($entity, $api, $db['value'] === NULL ? '' : $db['value']);
                        break;
                }
            }
        }

        return $entity;
    }

    // new
    protected function getAPIValue(array $entity, $api)
    {
        $api = is_array($api) ? $api : explode('.', $api);
        $key = array_shift($api);
        $r = [];

        // get out of here
        if ( !isset($entity[$key]) ) {
            return NULL;
        }
        if ( !is_array($entity[$key]) && count($api) == 0 ) {
            return $entity[$key];
        }

        if ( !is_array($entity[$key]) ) {
            $r = $this->getAPIValue($entity[$key], $api);
        }
        else {
            foreach ( $entity[$key] as $k => $v ) {
                $r[$k] = $this->getAPIValue($v, $api);
            }
        }

        return $r;
    }

    protected function setAPIValue(&$entity, $api, $value, $type = [], $bool = true)
    {
        // init
        $api = is_array($api) ? $api : explode('.', $api);
        $currentType = array_pop($type);

        // get out of here
        if ( !$api ) {
            $entity = $bool ? $value : !$value;
            return $this;
        }

        $key = array_shift($api);
        if ( !isset($entity[$key]) ) {
            $entity[$key] = [];
        }

        if ( $currentType == 'collection' ) {
            if ( !is_array($value) ) {
                throw new liApiConfigurationException('There is an error in the configuration of data mapping, with a collection that refers to a single property');
            }
            foreach ( $value as $k => $v ) {
                $this->setAPIValue($entity[$key][$k], $api, $value[$k], $type, $bool);
            }
        }
        else {
            $this->setAPIValue($entity[$key], $api, $value, $type, $bool);
        }

        return $this;
    }

    /**
     * Get back a value in a Doctrine_Record related to a description of a path
     *
     * @param mixed|Doctrine_Record $record where to find data in
     * @param array|string          $db     the access path
     **/
    protected function getRecordValue($record, $db)
    {
        // init
        $db = is_array($db) ? $db : explode('.', $db);

        // get out of here
        if ( !$db ) {
            return $this->isDoctrine($record) ? $record->toArray() : $record;
        }

        $key = array_shift($db);
        if ( $record instanceof Doctrine_Record ) {
            try {
                $record->get($key);
            } catch ( Doctrine_Record_Exception $e ) {
                return null;
            }
        }

        // Doctrine_Collection
        if ( $record->$key instanceof Doctrine_Collection ) {
            $r = [];
            foreach ( $record->$key as $i => $rec ) {
                $r[$i] = $this->getRecordValue($rec, $db);
            }
            return $r;
        }

        // Doctrine_Record
        return $this->getRecordValue($record->$key, $db);
    }

    /**
     * Set a value in a Doctrine_Record related to a description of a path
     *
     * @param Doctrine_Record $record where to be updated by data in $path
     * @param mixed $db             the access path
     * @param mixed $value
     **/
    // new
    protected function setRecordValue(Doctrine_Record &$record, $db, $value)
    {
        // init
        $db = is_array($db) ? $db : explode('.', $db);

        // get out of here
        if ( !$db ) {
            $record = $value;
            return $this;
        }

        $key = array_shift($db);

        // Doctrine_Collection
        if ( $record->$key instanceof Doctrine_Collection ) {
            foreach ( $value as $k => $v ) {
                $this->setRecordValue($record->{$key}[$k], $db, $v);
            }
            return $this;
        }

        // Doctrine_Record
        if ( $record->$key instanceof Doctrine_Record ) {
            return $this->setRecordValue($record->$key, $db, $value);
        }
        // Doctrine_Record::$property
        else {
            $record->$key = $value;
            return $this;
        }
    }

    private function reverseEquiv(array $equiv)
    {
        $r = [];
        foreach ( $equiv as $api => $db ) {
            $r[$db['value']] = ['value' => $api, 'type' => $db['type']];
        }
        return $r;
    }

    private function isArray($data)
    {
        return $data instanceof ArrayAccess || is_array($data);
    }
    private function isDoctrine($data)
    {
        return $data instanceof Doctrine_Record || $data instanceof Doctrine_Collection;
    }
    private function isCollection($data)
    {
        return $this->isArray($data) || $data instanceof Doctrine_Collection;
    }
    private function getType($mixed)
    {
        return is_object($mixed) ? get_class($mixed) : gettype($mixed);
    }
    private function raiseException($message, $line = 'unknown', $file = __FILE__)
    {
        throw new liApiException(str_replace(['%%line%%', '%%file%%'], [$line, $file], $message));
    }
}

