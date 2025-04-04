<?php

use Adianti\Base\TStandardForm;
use Adianti\Control\TAction;
use Adianti\Database\TTransaction;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Util\TXMLBreadCrumb;
use Adianti\Wrapper\BootstrapFormBuilder;

/**
 * preferenciaControleNotas
 *
 * @version    8.0
 * @package    control
 * @subpackage admin
 */
class preferenciaControleNotas extends TStandardForm
{
    protected $form; // formulário

    /**
     * método construtor
     * Cria a página e o formulário de cadastro
     */
    function __construct()
    {
        parent::__construct();

        $this->setDatabase('permission'); // nome do banco de dados
        $this->setActiveRecord('notaspreferences'); // classe de preferências

        // cria o formulário
        $this->form = new BootstrapFormBuilder('form_preferences');
        $this->form->setFormTitle('Configurações do Robô de Notas');

        // cria os campos do formulário
        $url_pesquisa_chave = new TEntry('url_pesquisa_chave');
        $url_geracao_pdf = new TEntry('url_geracao_pdf');
        $url_geracao_pdf_principal = new TEntry('url_geracao_pdf_principal');
        $email_destinatario = new TEntry('email_destinatario');
        $email_suporte = new TEntry('email_suporte'); // opcional: e-mail para receber erros
        
        // placeholders para orientação
        $url_geracao_pdf_principal->placeholder = 'URL da API PRINCIPAL';
        $url_pesquisa_chave->placeholder = 'URL da API para pesquisa da chave de acesso para emitir xml';
        $url_geracao_pdf->placeholder = 'URL da API para pesquisa da chave de acesso para gerar pdf';
        
        $email_destinatario->placeholder = 'E-mail para envio das DANFEs';
        $email_suporte->placeholder = 'E-mail para receber notificações de erro';

        // // tamanhos dos campos
        $url_pesquisa_chave->setSize('70%');
        $url_geracao_pdf->setSize('70%');
        $url_geracao_pdf_principal->setSize('70%');
        $email_destinatario->setSize('70%');
        $email_suporte->setSize('70%');

        // adiciona os campos ao formulário
        $this->form->addFields([new TLabel('URL geradora do xml secundaria')], [$url_pesquisa_chave]);
        // adiciona os campos ao formulário
        $this->form->addFields([new TLabel('URL geradora da danfe')], [$url_geracao_pdf]);
        $this->form->addFields([new TLabel('URL Geração PDF Principal')], [$url_geracao_pdf_principal]);
        $this->form->addFields([new TLabel('E-mail Destinatário')], [$email_destinatario]);
        $this->form->addFields([new TLabel('E-mail Suporte')], [$email_suporte]);

        // botão salvar
        $btn = $this->form->addAction('Salvar', new TAction([$this, 'onSave']), 'far:save');
        $btn->class = 'btn btn-sm btn-primary';

        // Cria o container e adiciona o formulário e breadcrumb
        $container = new TVBox;
        $container->{'style'} = 'width: 100%;';
        $container->add(new TXMLBreadCrumb('menu.xml', __CLASS__));
        $container->add($this->form);
        parent::add($container);

        // Carregar as preferências já salvas no banco, passando um array vazio
        $this->onEdit([]);
    }

    /**
     * Carrega o formulário de preferências
     */
    function onEdit($param)
    {
        try {
            // Abre uma transação com o banco de dados
            TTransaction::open($this->database);

            // Busca as preferências diretamente na tabela 'notas_preferences'
            $preferences = notaspreferences::all(); // Carrega todas as preferências

            // Cria um array para armazenar os valores
            $data = [];
            foreach ($preferences as $preference) {
                $data[$preference->id] = $preference->value; // Mapear o ID e o valor
            }

            // Preenche o formulário com os dados encontrados no banco
            $this->form->setData((object)$data);

            // Fecha a transação
            TTransaction::close();
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    /**
     * Salva as configurações
     */
    function onSave()
    {
        try {
            // Abre uma transação com o banco de dados
            TTransaction::open($this->database);

            // Obtém os dados do formulário
            $data = $this->form->getData();
            $data_array = (array)$data;

            // Itera sobre as preferências e salva ou atualiza no banco
            foreach ($data_array as $property => $value) {
                $object = notaspreferences::find($property); // Usando a tabela 'notas_preferences'

                // Se a preferência não existir, cria uma nova
                if (!$object) {
                    $object = new notaspreferences;
                    $object->id = $property;
                }

                // Atualiza o valor da preferência
                $object->value = $value;
                $object->store(); // Salva no banco
            }

            // Atualiza os dados do formulário
            $this->form->setData($data);
            TTransaction::close();

            // Exibe uma mensagem de sucesso
            new TMessage('info', 'Configurações salvas com sucesso.');
        } catch (Exception $e) {
            // Caso ocorra erro, mantém os dados no formulário
            $object = $this->form->getData();
            $this->form->setData($object);
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public static function getPreferenceValue($key)
    {
        try {
            // Abre a transação
            TTransaction::open('permission');

            // Busca a preferência pelo ID
            $preference = notaspreferences::find($key);

            // Se não encontrar, retorna null
            if (!$preference) {
                return null;
            }

            return $preference->value;
        } catch (Exception $e) {
            TTransaction::rollback();
            throw new Exception('Erro ao obter valor da preferência: ' . $e->getMessage());
        }
    }
}
