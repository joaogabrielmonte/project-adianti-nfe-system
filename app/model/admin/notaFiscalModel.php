<?php

use Adianti\Database\TRecord;
use Adianti\Log\TLoggerTXT;
use PHPMailer\PHPMailer\PHPMailer;

class NotaFiscalModel extends TRecord
{
    private $urlParaObterPDF;

    public function __construct()
    {
        $this->urlParaObterPDF = preferenciaControleNotas::getPreferenceValue('url_geracao_pdf_principal');

        if (empty($this->urlParaObterPDF)) {
            throw new Exception("URL para obter o PDF não configurada nas preferências.");
        }
    }

    public function fazerRequisicaoGET($url, $dados = null)
    {
        try {
            $ch = curl_init();

            if ($dados) {
                $url = $url . '?' . http_build_query($dados);
            }

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPGET, 1);

            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                throw new Exception('Erro na requisição: ' . curl_error($ch));
            }

            $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpStatusCode != 200) {
                throw new Exception("Erro ao realizar requisição: " . $httpStatusCode);
            }

            curl_close($ch);

            return $response;
        } catch (Exception $e) {
            $logger = new TLoggerTXT('log.txt');
            $logger->write($e->getMessage());
            throw new Exception("Erro ao fazer requisição: " . $e->getMessage());
        }
    }

    public function obterPDFBase64($chaveacesso)
    {
        if (empty($this->urlParaObterPDF)) {
            throw new Exception("URL para obter o PDF não configurada.");
        }

        $url = $this->urlParaObterPDF . $chaveacesso;
        $response = $this->fazerRequisicaoGET($url, ['chaveacesso' => $chaveacesso]);

        $responseData = json_decode($response, true);

        if (isset($responseData['pdf'])) {
            return $responseData['pdf'];
        } else {
            throw new Exception("PDF não encontrado na resposta da API.");
        }
    }

    public function salvarPDF($base64PDF, $pdfPath)
    {
        $pdfData = base64_decode($base64PDF);
        file_put_contents($pdfPath, $pdfData);
    }

    public function enviarEmailComResultado($assunto, $mensagem, $pdfPath)
    {
        try {
            $smtpHost = preferenciaControleNotas::getPreferenceValue('smtp_host');
            $smtpUser = preferenciaControleNotas::getPreferenceValue('smtp_user');
            $smtpPass = preferenciaControleNotas::getPreferenceValue('smtp_pass');
            $smtpPort = preferenciaControleNotas::getPreferenceValue('smtp_port');
            $smtpAuth = preferenciaControleNotas::getPreferenceValue('smtp_auth');

            if (!$smtpHost || !$smtpUser || !$smtpPass || !$smtpPort) {
                throw new Exception('Configurações SMTP não configuradas corretamente.');
            }

            $emailEmitente = preferenciaControleNotas::getPreferenceValue('mail_from');
            if (empty($emailEmitente) || !filter_var($emailEmitente, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('E-mail do remetente não configurado corretamente nas preferências.');
            }

            $mail = new PHPMailer(true);

            $mail->isSMTP();
            $mail->Host = $smtpHost;
            $mail->SMTPAuth = $smtpAuth == '1';
            $mail->Username = $smtpUser;
            $mail->Password = $smtpPass;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $smtpPort;

            $mail->setFrom($emailEmitente, 'Nome do Remetente');
            $mail->addAddress($assunto);

            $mail->Subject = $assunto;
            $mail->Body = $mensagem;

            $mail->addAttachment($pdfPath);

            if (!$mail->send()) {
                throw new Exception("Erro ao enviar o e-mail. Erro: {$mail->ErrorInfo}");
            }
        } catch (Exception $e) {
            throw new Exception("Erro ao enviar o e-mail: " . $e->getMessage());
        }
    }

    public function registrarErro($chaveAcesso, $erro)
    {
        try {
            $logger = new TLoggerTXT('log_erros.txt');
            $mensagem = "Erro na nota fiscal com chave de acesso {$chaveAcesso}: {$erro}";
            $logger->write($mensagem);
        } catch (Exception $e) {
            $logger = new TLoggerTXT('log.txt');
            $logger->write("Erro ao registrar erro: " . $e->getMessage());
        }
    }

    public function atualizarStatusNotaFiscal($chaveAcesso, $status, $emailDestinatario)
    {
        try {
            $sql = "UPDATE dbo.CKOCOL SET status = :status WHERE CKO_CHVDOC = :chaveAcesso";
            $stmt = \Adianti\Database\TTransaction::get()->prepare($sql);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':chaveAcesso', $chaveAcesso);
            $stmt->execute();

            if ($status == 'Enviado') {
                $this->enviarEmailComResultado("Confirmação de envio", "A nota fiscal foi enviada com sucesso.", "/caminho/para/salvar/pdfs/nota_{$chaveAcesso}.pdf");
            }
        } catch (Exception $e) {
            $this->registrarErro($chaveAcesso, "Falha ao atualizar o status da nota fiscal: " . $e->getMessage());
            $this->enviarAlertaErro($emailDestinatario, $chaveAcesso);
        }
    }

    // Método para processar múltiplas notas fiscais e pular as que geram erro
    public function processarNotasFiscais($notas)
    {
        foreach ($notas as $nota) {
            $chaveAcesso = $nota['CKO_CHVDOC'];

            try {
                // Obter o PDF
                $pdfBase64 = $this->obterPDFBase64($chaveAcesso);

                // Salvar o PDF
                $pdfPath = "/caminho/para/salvar/pdfs/nota_{$chaveAcesso}.pdf";
                $this->salvarPDF($pdfBase64, $pdfPath);

                // Atualizar status da nota fiscal
                $this->atualizarStatusNotaFiscal($chaveAcesso, 'Enviado', $nota['email']);

            } catch (Exception $e) {
                // Registrar erro e continuar com a próxima nota
                $this->registrarErro($chaveAcesso, $e->getMessage());
                continue; // Pula para a próxima nota em caso de erro
            }

            sleep(2); // Pausa entre requisições
        }
    }


     // Função para enviar alerta de erro por e-mail
     public function enviarAlertaErro($email, $chaveAcesso)
     {
         try {
             $subject = "Erro no processamento da Nota Fiscal";
             $body = "Houve um erro ao processar a nota fiscal com chave de acesso {$chaveAcesso}. Por favor, verifique.";
 
             $mail = new PHPMailer(true);
             $mail->setFrom('no-reply@seudominio.com', 'Sistema de Notas Fiscais');
             $mail->addAddress($email); // Destinatário de erro
             $mail->Subject = $subject;
             $mail->Body = $body;
             $mail->send();
         } catch (Exception $e) {
             $this->registrarErro($chaveAcesso, "Falha ao enviar alerta de erro: " . $e->getMessage());
         }
     }
 
}
