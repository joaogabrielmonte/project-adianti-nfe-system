<?php

use Adianti\Database\TRecord;
use Adianti\Log\TLogger;
use Adianti\Log\TLoggerTXT;
use PHPMailer\PHPMailer\PHPMailer;

class NotaFiscalModel2Url extends TRecord
{
    // URLs serão obtidas das preferências do sistema
    private $urlProcuraNota;
    private $urlParaConverterEmDANFE;

    public function __construct()
    {
        // Obtém as URLs das preferências usando o método estático
        $this->urlProcuraNota = preferenciaControleNotas::getPreferenceValue('url_pesquisa_chave');
        $this->urlParaConverterEmDANFE = preferenciaControleNotas::getPreferenceValue('url_geracao_pdf');
    }

    public function enviarEmailComResultado($assunto, $mensagem, $pdfPath)
    {
        try {
            // Recupera as configurações de SMTP do banco de dados usando o método getPreferenceValue
            $smtpHost = preferenciaControleNotas::getPreferenceValue('smtp_host');
            $smtpUser = preferenciaControleNotas::getPreferenceValue('smtp_user');
            $smtpPass = preferenciaControleNotas::getPreferenceValue('smtp_pass');
            $smtpPort = preferenciaControleNotas::getPreferenceValue('smtp_port');
            $smtpAuth = preferenciaControleNotas::getPreferenceValue('smtp_auth'); // 1 para sim, 0 para não
            
            // Verifique se as configurações foram recuperadas corretamente
            if (!$smtpHost || !$smtpUser || !$smtpPass || !$smtpPort) {
                throw new Exception('Configurações SMTP não configuradas corretamente.');
            }
            
            // Obter o e-mail do remetente das preferências
            $emailEmitente = preferenciaControleNotas::getPreferenceValue('mail_from');
            
            // Verifique se o e-mail do remetente está configurado
            if (empty($emailEmitente) || !filter_var($emailEmitente, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('E-mail do remetente não configurado corretamente nas preferências.');
            }
    
            // Criação da instância do PHPMailer
            $mail = new PHPMailer(true);
            
            // Configuração do servidor SMTP
            $mail->isSMTP();
            $mail->Host = $smtpHost;
            $mail->SMTPAuth = $smtpAuth == '1';  // Verifica se o SMTP requer autenticação
            $mail->Username = $smtpUser;
            $mail->Password = $smtpPass;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;  // Usando TLS por padrão
            $mail->Port = $smtpPort;
            
            // Definir o remetente e o destinatário
            $mail->setFrom($emailEmitente, 'Nome do Remetente'); // Use o e-mail do remetente configurado
            $mail->addAddress($assunto); // Destinatário passado como argumento
    
            // Assunto e corpo do e-mail
            $mail->Subject = $assunto;
            $mail->Body    = $mensagem;
    
            // Anexar o PDF gerado
            $mail->addAttachment($pdfPath);
    
            // Enviar o e-mail
            if (!$mail->send()) {
                throw new Exception("Erro ao enviar o e-mail. Erro: {$mail->ErrorInfo}");
            }
    
        } catch (Exception $e) {
            // Caso ocorra erro, mostrar a mensagem de erro
            throw new Exception("Erro ao enviar o e-mail: " . $e->getMessage());
        }
    }


    // Função para realizar a requisição HTTP usando cURL
    public function fazerRequisicaoPOST($url, $dados = null)
    {
        try {
            $ch = curl_init();

            // Configurações do cURL
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            if ($dados) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($dados)); // Dados a serem enviados
            }

            $response = curl_exec($ch);

            // Verifica se houve erro na execução da requisição
            if (curl_errno($ch)) {
                throw new Exception('Erro na requisição: ' . curl_error($ch));
            }

            // Verifica o código de status HTTP
            $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpStatusCode != 200) {
                throw new Exception("Erro ao realizar requisição: " . $httpStatusCode);
            }

            curl_close($ch);

            return $response;
        } catch (Exception $e) {
            $logger = new TLoggerTXT('log.txt'); // Instancia o logger
            $logger->write($e->getMessage());    // Registra o erro no arquivo de log
            throw new Exception("Erro ao fazer requisição: " . $e->getMessage());
        }
    }

    // Função para obter o XML da Nota Fiscal
    public function obterXMLNotaFiscal($chaveacesso)
    {
        if (empty($this->urlProcuraNota)) {
            throw new Exception("URL para pesquisa da chave de acesso não configurada.");
        }

        $url = $this->urlProcuraNota . $chaveacesso;
        return $this->fazerRequisicaoPOST($url, ['chaveacesso' => $chaveacesso]);
    }

    // Função para obter o PDF em Base64 da Nota Fiscal
    public function obterPDFBase64($chaveacesso)
    {
        if (empty($this->urlParaConverterEmDANFE)) {
            throw new Exception("URL para conversão de XML para PDF não configurada.");
        }

        $url = $this->urlParaConverterEmDANFE . $chaveacesso;
        return $this->fazerRequisicaoPOST($url, ['chaveacesso' => $chaveacesso]);
    }

    // Função para salvar o PDF
    public function salvarPDF($base64PDF, $pdfPath)
    {
        $pdfData = base64_decode($base64PDF);
        file_put_contents($pdfPath, $pdfData);
    }
}

?>
