<?php

use Adianti\Control\TPage;

class ViewDANFSe extends TPage
{
    public function __construct()
    {
        parent::__construct();

        // Carregar o template HTML
        $html = file_get_contents('app/model/classes/template.html');

        // Substituir as variáveis pelos valores reais
        $html = str_replace('{emitente_razao_social}', 'Razão Social Emitente', $html);
        $html = str_replace('{emitente_cnpj}', '00.000.000/0001-00', $html);
        $html = str_replace('{emitente_endereco}', 'Rua Exemplo, 123', $html);

        $html = str_replace('{tomador_razao_social}', 'Razão Social Tomador', $html);
        $html = str_replace('{tomador_cnpj}', '00.000.000/0001-00', $html);
        $html = str_replace('{tomador_endereco}', 'Rua Tomador, 456', $html);

        $html = str_replace('{dps_numero}', '12345', $html);
        $html = str_replace('{dps_serie}', '001', $html);
        $html = str_replace('{dps_data_emissao}', '10/02/2025', $html);
        $html = str_replace('{valor_servico}', '500.00', $html);
        $html = str_replace('{descricao_servico}', 'Consultoria em TI', $html);

        // Exibir o conteúdo HTML diretamente na página
        echo $html;
    }
}
