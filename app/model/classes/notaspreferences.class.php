<?php

use Adianti\Database\TRecord;
/**
 * notas_preferences
 * 
 * @version    1.0
 * @package    model
 */
class notaspreferences extends TRecord
{
    const TABLENAME = 'notas_preferences';  // Nome da tabela
    const PRIMARYKEY = 'id';  // Nome da chave primária
    const IDPOLICY = 'serial';  // Política de incremento do ID (serial/autoincremento)
}
