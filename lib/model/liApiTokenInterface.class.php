<?php

/**
 * liApiTokenInterface
 * 
 * @package    ##PACKAGE##
 * @subpackage ##SUBPACKAGE##
 * @author     ##NAME## <##EMAIL##>
 * @version    SVN: $Id: Builder.php 7490 2010-03-29 19:53:27Z jwage $
 */
interface liApiTokenInterface
{
    /**
     * Returns the application, whatever its type
     *
     * @return mixed  current application (e.g. OsApplication or OcApplication)
     */
    public function getApplication();
}
