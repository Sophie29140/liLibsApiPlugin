<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of ApiTranslationService
 *
 * @author Baptiste SIMON <baptiste.simon@libre-informatique.fr>
 */
class ApiTranslationService
{
    public function reformat(&$originals)
    {
        // dates in ISO format
        $done = false;
        foreach ( $originals as $id => $original ) {
            if ( !is_string($original) ) {
                continue;
            }
            if (!( preg_match('/\d\d\d\d-\d\d-\d\d \d\d:\d\d:\d\d/', $original) == 1 && strtotime($original) !== false )) {
                continue;
            }
            
            $time = strtotime($original);
            $originals[$id] = str_replace('-', 'T', date('Ymd-HisP', $time));
            $done = true;
        }
        if ( $done ) {
            return $this;
        }
        
        // translations
        $done = false;
        foreach ( $originals as $id => $original ) {
            unset(
                $originals[$id]['id'],
                $originals[$id]['lang']
            );
            $done = true;
        }
        if ( $done ) {
            return $this;
        }
        
        return $this;
    }
}
