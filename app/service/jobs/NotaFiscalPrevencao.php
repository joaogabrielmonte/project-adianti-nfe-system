<?php

use Adianti\Database\TTransaction;
use Adianti\Log\TLoggerTXT;
use PHPMailer\PHPMailer\PHPMailer;

class NotaFiscalPrevencao
{
    public static function run()
    {
        ini_set('max_execution_time', 0); // Tempo ilimitado de execução
        $logger = new TLoggerTXT('log_nota_fiscal_job.txt');
        $logger->write("Iniciando execução do job de notas fiscais. (reserva)");

        try {
            // Abrir transação com o banco de dados
            TTransaction::open('protheus');
            $conn = TTransaction::get();

            // Buscar todas as notas com status "Pendente de envio"
            $sql = "SELECT * FROM SF1990 WHERE status = 'Pendente de envio'";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $notas = $stmt->fetchAll();
            TTransaction::close();

            // Se não houver notas pendentes, sair
            if (!$notas) {
                $logger->write("Nenhuma nota pendente encontrada.");
                return;
            }

            // Processar cada nota
            foreach ($notas as $nota) {
                $chave = $nota['F1_CHVNFE'];
                if (empty($chave) || !self::validarChaveAcesso($chave)) {
                    $logger->write("Chave de acesso inválida ou vazia para a nota: $chave. Pulando para a próxima.");
                    continue;
                }

                $logger->write("Processando nota com chave de acesso: $chave");

                $notaFiscal = new NotaFiscalModel2Url();
                $xmlNotaFiscal = $notaFiscal->obterXMLNotaFiscal($chave);

                // Verificar se o XML foi obtido com sucesso
                if (!$xmlNotaFiscal) {
                    $logger->write("Erro ao obter XML da nota fiscal: $chave");
                    continue;
                }

                // Obter o PDF da nota fiscal
                $base64PDF = $notaFiscal->obterPDFBase64($chave);
                if (!$base64PDF) {
                    $logger->write("Erro ao obter o PDF em Base64 da nota: $chave");
                    continue;
                }

                // Definir caminho onde o PDF será salvo
                $pdfPath = "app/output/danfe_{$chave}.pdf";
                $notaFiscal->salvarPDF($base64PDF, $pdfPath);

                // Verificar se o PDF foi salvo corretamente
                if (!file_exists($pdfPath)) {
                    $logger->write("Erro ao salvar o PDF da nota: $chave");
                    continue;
                }

                // Enviar o e-mail com a nota fiscal
                $email_destinatario = preferenciaControleNotas::getPreferenceValue('email_destinatario');
                self::enviarEmail($pdfPath, $chave, $email_destinatario);

                // Logar o email enviado
                $logger->write("DANFE enviado para: $email_destinatario");
            }

        } catch (Exception $e) {
            $logger->write('Erro ao executar o job de envio de notas: ' . $e->getMessage());
        }
    }

    // Função para validar a chave de acesso
    private static function validarChaveAcesso($chave)
    {
        return preg_match('/^[0-9]{44}$/', $chave);
    }

    // Função para enviar o e-mail com o PDF da nota fiscal
    public static function enviarEmail($pdfPath, $chaveacesso, $email_destinatario)
    {
        try {
            // Recuperar as preferências de e-mail do sistema
            $preferences = SystemPreference::getAllPreferences();
            $email_origem = $preferences['mail_from'] ?? '';
            $smtp_host = $preferences['smtp_host'] ?? '';
            $smtp_porta = $preferences['smtp_port'] ?? '';
            $smtp_usuario = $preferences['smtp_user'] ?? '';
            $smtp_senha = $preferences['smtp_pass'] ?? '';
            $email_suporte = $preferences['mail_support'] ?? '';

            if (empty($email_destinatario)) {
                throw new Exception('E-mail do destinatário não configurado nas preferências.');
            }

            // Configuração do PHPMailer
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $smtp_host;
            $mail->SMTPAuth = true;
            $mail->Username = $smtp_usuario;
            $mail->Password = $smtp_senha;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $smtp_porta;
            $mail->setFrom($email_origem, 'Sistema de Envio de DANFE');
            $mail->addAddress($email_destinatario);
            $mail->addReplyTo($email_suporte, 'Suporte');
            $mail->Subject = 'DANFE - Envio Automático';
            $mail->Body = "<p>Olá,</p><p>Segue em anexo o DANFE referente à chave de acesso: {$chaveacesso}.</p>";
            $mail->isHTML(true);
            $mail->addAttachment($pdfPath);  // Anexando o PDF
            $mail->send();

            // Atualizar o status da nota
            self::atualizarStatusNota($chaveacesso, $email_destinatario);

        } catch (Exception $e) {
            throw new Exception('Erro ao enviar o e-mail: ' . $e->getMessage());
        }
    }

    // Função para atualizar o status da nota fiscal no banco de dados
    public static function atualizarStatusNota($chaveacesso, $email_destinatario)
    {
        try {
            TTransaction::open('protheus');
            $conn = TTransaction::get();

            // Atualizar o status da nota para "Enviado" e registrar o e-mail do destinatário
            $sql = "UPDATE SF1990 SET status = 'Enviado', email_enviado = ? WHERE F1_CHVNFE = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$email_destinatario, $chaveacesso]);

            TTransaction::close();
        } catch (Exception $e) {
            throw new Exception('Erro ao atualizar o status da nota: ' . $e->getMessage());
        }
    }
}
