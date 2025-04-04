<?php

use Adianti\Database\TRecord;

/**
 * CkoCol Active Record
 * @author  <your-name-here>
 */
class CKOCOL extends TRecord // Tabela CKOCOL
{
    const TABLENAME = 'CKOCOL';
    const PRIMARYKEY= 'R_E_C_N_O_';
    const IDPOLICY =  'max'; // {max, serial}
    
    /**
     * Constructor method
     */
    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('CKO_FILIAL');
        parent::addAttribute('CKO_ARQUIV');
        parent::addAttribute('CKO_STATUS');
        parent::addAttribute('CKO_DESSTA');
        parent::addAttribute('CKO_DT_GER');
        parent::addAttribute('CKO_HR_GER');
        parent::addAttribute('CKO_DT_RET');
        parent::addAttribute('CKO_HR_RET');
        parent::addAttribute('CKO_IDERP');
        parent::addAttribute('CKO_TP_MOV');
        parent::addAttribute('CKO_FLAG');
        parent::addAttribute('CKO_CODEDI');
        parent::addAttribute('CKO_DT_IMP');
        parent::addAttribute('CKO_HR_IMP');
        parent::addAttribute('CKO_CODERR');
        parent::addAttribute('CKO_FILPRO');
        parent::addAttribute('CKO_EMPPRO');
        parent::addAttribute('CKO_CNPJIM');
        parent::addAttribute('CKO_ARQXML');
        parent::addAttribute('CKO_NOMFOR');
        parent::addAttribute('CKO_DOC');
        parent::addAttribute('CKO_SERIE');
        parent::addAttribute('CKO_STRAN');
        parent::addAttribute('CKO_CHVDOC');
        parent::addAttribute('CKO_ORIGEM');
        parent::addAttribute('CKO_SERELT');
        parent::addAttribute('CKO_NFELET');
        parent::addAttribute('CKO_RECIBO');
        parent::addAttribute('CKO_XABAST');
        parent::addAttribute('CKO_XLOG');
        parent::addAttribute('CKO_XERRO');
        parent::addAttribute('CKO_XMLENV');
        parent::addAttribute('CKO_XMLRET');
        parent::addAttribute('CKO_MSGERR');
        parent::addAttribute('CKO_ERRTRA');
        parent::addAttribute('D_E_L_E_T_');
        parent::addAttribute('R_E_C_N_O_');
        parent::addAttribute('R_E_C_D_E_L_');
        parent::addAttribute('status');
        parent::addAttribute('email_enviado');
    }
}
