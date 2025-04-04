<?php

use Adianti\Widget\Dialog\TMessage;
use PHPMailer\PHPMailer\PHPMailer;

class NotaFiscalModel
{
    // Outros métodos do modelo 

    public function enviarNotaFiscal($pdfPath, $emailDestinatario)
    {
        try {
            // Recupera as configurações de SMTP do SystemPreference
            $smtp_host = self::getPreferenceValue('smtp_host');
            $smtp_user = self::getPreferenceValue('smtp_user');
            $smtp_pass = self::getPreferenceValue('smtp_pass');
            $smtp_port = self::getPreferenceValue('smtp_port');
            $mail_from = self::getPreferenceValue('mail_from');

            // Criação do objeto PHPMailer
            $mail = new PHPMailer();
            $mail->isSMTP();
            $mail->Host = $smtp_host;
            $mail->SMTPAuth = true;
            $mail->Username = $smtp_user;
            $mail->Password = $smtp_pass;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $smtp_port;

            // Configurações do e-mail
            $mail->setFrom($mail_from, 'Sistema de Notas');
            $mail->addAddress($emailDestinatario);
            $mail->Subject = 'Nota Fiscal - DANFE';
            $mail->Body = 'Segue em anexo o DANFE da Nota Fiscal.';
            $mail->addAttachment($pdfPath);

            // Envia o e-mail
            if ($mail->send()) {
                new TMessage('info', 'E-mail enviado com sucesso para ' . $emailDestinatario);
            } else {
                throw new Exception('Falha ao enviar e-mail.');
            }
        } catch (Exception $e) {
            new TMessage('error', 'Erro ao enviar e-mail: ' . $e->getMessage());
        }
    }
}
