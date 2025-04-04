<?php


use Adianti\Database\TRecord;

class NotaMonitorModel extends TRecord
{
    const TABLENAME  = 'notas_monitor';  // Nome da tabela no banco de dados
    const PRIMARYKEY = 'chave_acesso';   // Definido como chave primária
    const IDPOLICY   = 'serial';         // Definir a política como serial para auto-incremento (se necessário)

    public function __construct()
    {
        parent::__construct();

        // Definindo os atributos que representam os campos da tabela
        $this->addAttribute('chave_acesso');
        $this->addAttribute('numero');
        $this->addAttribute('modelo');
        $this->addAttribute('serie');
        $this->addAttribute('data_emissao');
        $this->addAttribute('data_envio');
        $this->addAttribute('valor_total');
        $this->addAttribute('emi_nome');
        $this->addAttribute('dest_nome');
        $this->addAttribute('email_destinatario');
        $this->addAttribute('status');
        $this->addAttribute('mensagem_erro');
    }
}
