<?php
/**
 * SystemPreference
 *
 * @version    8.0
 * @package    model
 * @subpackage admin
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    https://adiantiframework.com.br/license-template
 */class SystemPreference extends TRecord
{
    const TABLENAME  = 'system_preference';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'max'; // {max, serial}
    
    /**
     * Constructor method
     */
    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('value');
    }
    
    /**
     * Retorna uma preferência
     * @param $id Id da preferência
     */
    public static function getPreference($id)
    {
        TTransaction::open('permission'); // Inicia a transação
        $preference = new SystemPreference($id);
        TTransaction::close(); // Fecha a transação
        return $preference->value;
    }
    
    /**
     * Altera uma preferência
     * @param $id  Id da preferência
     * @param $value Valor da preferência
     */
    public static function setPreference($id, $value)
    {
        TTransaction::open('permission'); // Inicia a transação
        $preference = SystemPreference::find($id);
        if ($preference)
        {
            $preference->value = $value;
            $preference->store();
        }
        TTransaction::close(); // Fecha a transação
    }
    
    /**
     * Retorna um array com todas preferências
     */
    public static function getAllPreferences()
    {
        TTransaction::open('permission'); // Inicia a transação
        $rep = new TRepository('SystemPreference');
        $objects = $rep->load(new TCriteria);
        $dataset = array();
        
        if ($objects)
        {
            foreach ($objects as $object)
            {
                $property = $object->id;
                $value    = $object->value;
                $dataset[$property] = $value;
            }
        }
        TTransaction::close(); // Fecha a transação
        return $dataset;
    }
}
