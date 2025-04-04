<?php

use Adianti\Database\TCriteria;
use Adianti\Database\TFilter;
use Adianti\Database\TRepository;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class SystemUserExampleService
{
    public static function run($assunto, $mensagem, $pdfPath)
    {
        try {
            // Recupera as configurações de SMTP do banco de dados
            $smtpHost = self::getPreferenceValue('smtp_host');
            $smtpUser = self::getPreferenceValue('smtp_user');
            $smtpPass = self::getPreferenceValue('smtp_pass');
            $smtpPort = self::getPreferenceValue('smtp_port');
            $smtpAuth = self::getPreferenceValue('smtp_auth');
            $emailEmitente = self::getPreferenceValue('mail_from');

            // Verifica se todas as configurações necessárias foram recuperadas
            if (!$smtpHost || !$smtpUser || !$smtpPass || !$smtpPort) {
                throw new Exception('Configurações SMTP não configuradas corretamente.');
            }

            // Verifica se o e-mail do remetente está configurado corretamente
            if (empty($emailEmitente) || !filter_var($emailEmitente, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('E-mail do remetente não configurado corretamente nas preferências.');
            }

            // Criação da instância do PHPMailer
            $mail = new PHPMailer(true);

            // Configuração do servidor SMTP
            $mail->isSMTP();
            $mail->Host = $smtpHost;
            $mail->SMTPAuth = $smtpAuth == '1';  // Se a autenticação SMTP está habilitada
            $mail->Username = $smtpUser;
            $mail->Password = $smtpPass;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $smtpPort;

            // Ativar depuração do SMTP para verificar a comunicação
            $mail->SMTPDebug = 2;  // 2 para debug completo (detalhado)
            $mail->Debugoutput = 'html'; // Para exibir as mensagens de depuração em HTML

            // Definir o remetente
            $mail->setFrom($emailEmitente, 'Nome do Remetente');
            $mail->addAddress('joaogabrielmonteg41@gmail.com'); // Destinatário
            $mail->addReplyTo($emailEmitente); // Respostas para o mesmo e-mail

            // Definir o assunto e o corpo da mensagem
            $mail->Subject = $assunto;
            $mail->Body = $mensagem;

            // Definir a mensagem de teste
            $assunto = 'Teste de Envio de E-mail';
            $mensagem = '
            <h1>Este é um e-mail de teste</h1>
            <p>Olá,</p>
            <p>Este é um e-mail de teste enviado pelo Adianti Framework com PHPMailer.</p>
            <p>Se você está recebendo esta mensagem, o sistema de envio de e-mails está funcionando corretamente.</p>
            <p>Obrigado!</p>
        ';

            // Adicionar anexo
            if ($pdfPath && file_exists($pdfPath)) {
                $mail->addAttachment($pdfPath); // Adiciona o PDF como anexo
            }

            // Envia o e-mail
            if (!$mail->send()) {
                // Log detalhado de erro
                self::logError('Erro ao enviar o e-mail: ' . $mail->ErrorInfo);
                throw new Exception("Erro ao enviar o e-mail. Erro: {$mail->ErrorInfo}");
            }

            echo 'E-mail enviado com sucesso!';
        } catch (Exception $e) {
            // Log detalhado da exceção
            self::logError('Falha ao enviar o e-mail: ' . $e->getMessage());
            throw new Exception("Erro ao enviar o e-mail: " . $e->getMessage());
        }
    }


    // Função para recuperar as preferências diretamente do banco de dados
    public static function getPreferenceValue($preferenceName)
    {
        try {
            // Realiza a consulta no banco de dados para recuperar o valor da preferência
            $criteria = new TCriteria;
            $criteria->add(new TFilter('name', '=', $preferenceName));

            // Corrigido para usar o TRepository corretamente
            $repository = new TRepository('SystemPreference');
            $preference = $repository->load($criteria);

            if ($preference) {
                return $preference[0]->value; // Retorna o valor da preferência
            }

            return null; // Se a preferência não for encontrada, retorna null
        } catch (Exception $e) {
            // Em caso de erro, loga a exceção
            self::logError('Erro ao recuperar a preferência ' . $preferenceName . ': ' . $e->getMessage());
            return null;
        }
    }

    // Função para logar os erros em um arquivo
    public static function logError($message)
    {
        // Define o caminho do arquivo de log
        $logFile = 'path/to/your/logfile.log';  // Substitua pelo caminho do arquivo de log

        // Adiciona o timestamp e a mensagem de erro ao arquivo de log
        file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND);
    }
}
