<?php
use Adianti\Control\TPage;
use Adianti\Control\TRequest;
use Adianti\Registry\TSession;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Wrapper\BootstrapFormBuilder;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TButton;
use Adianti\Widget\Wrapper\TDBCombo;
use Adianti\Database\TTransaction;
use Adianti\Widget\Util\TImage;
use Adianti\Widget\Container\TTable;

class NfseService extends TPage
{
    public function __construct()
    {
        parent::__construct();
    }

    public static function getNfseData($chave)
    {
        try {
            $apiUrl = 'https://sua-api.com/consultar-nfse';
            $params = [ 'chave' => $chave ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            $data = json_decode($response, true);
            if (isset($data['code']) && $data['code'] == 200) {
                return $data['data'][0];
            } else {
                throw new Exception("Erro ao buscar NFS-e: " . $data['code_message']);
            }
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            return null;
        }
    }
}
