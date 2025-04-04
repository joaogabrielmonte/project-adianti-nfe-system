<?php

use Adianti\Database\TTransaction;
use PHPMailer\PHPMailer\PHPMailer;
use Adianti\Log\TLoggerTXT;

class NotaFiscalJob
{
    public static function run()
    {
        ini_set('max_execution_time', 0); // Tempo ilimitado de execução
        $logger = new TLoggerTXT('log_nota_fiscal_job.txt');
        $logger->write("Iniciando execução do job de notas fiscais (Principal).");

        try {
            TTransaction::open('protheus');
            $conn = TTransaction::get();
            $logger->write("Transação aberta.");

            // Buscar somente notas com status 'Pendente de envio'
            $sql = "SELECT * FROM SF1990 WHERE status = 'Pendente de envio'";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $notas = $stmt->fetchAll();
            $logger->write("Notas pendentes recuperadas: " . count($notas));

            if (!$notas) {
                $logger->write("Nenhuma nota pendente encontrada.");
                return;
            }

            foreach ($notas as $nota) {
                $chave = $nota['F1_CHVNFE'];
                if (!self::validarChaveAcesso($chave)) {
                    $logger->write("Chave de acesso inválida: $chave. Pulando para a próxima.");
                    continue;
                }

                $notaFiscal = new NotaFiscalModel;
                $base64PDF = $notaFiscal->obterPDFBase64($chave);

                if (!$base64PDF) {
                    $logger->write("Erro ao obter o PDF da nota: $chave. Pulando para a próxima.");
                    continue;
                }

                $pdfPath = "app/output/danfe_{$chave}.pdf";
                $notaFiscal->salvarPDF($base64PDF, $pdfPath);
                $logger->write("PDF salvo para a nota: $chave");

                if (!file_exists($pdfPath)) {
                    $logger->write("Erro ao salvar o PDF da nota: $chave. Pulando para a próxima.");
                    continue;
                }

                self::enviarEmail($pdfPath, $chave, $logger);
                sleep(2); // Pausa entre requisições para evitar sobrecarga
            }

            TTransaction::close();
            $logger->write("Transação fechada.");
        } catch (Exception $e) {
            $logger->write('Erro ao executar o job principal: ' . $e->getMessage());
            $logger->write("Falha no sistema principal para a nota: $chave. Acionando sistema reserva...");
            NotaFiscalPrevencao::run();
        }
    }


    private static function validarChaveAcesso($chave)
    {
        return preg_match('/^[0-9]{44}$/', $chave);
    }

    public static function enviarEmail($pdfPath, $chave, $logger)
    {
        try {
            $preferences = SystemPreference::getAllPreferences();
            $email_destinatario = preferenciaControleNotas::getPreferenceValue('email_destinatario');

            if (empty($email_destinatario)) {
                $logger->write("E-mail do destinatário não configurado. Pulando para a próxima.");
                return;
            }

            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $preferences['smtp_host'] ?? '';
            $mail->SMTPAuth = true;
            $mail->Username = $preferences['smtp_user'] ?? '';
            $mail->Password = $preferences['smtp_pass'] ?? '';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $preferences['smtp_port'] ?? '';
            $mail->setFrom($preferences['mail_from'] ?? '', 'Sistema de Envio de DANFE');
            $mail->addAddress($email_destinatario);
            $mail->Subject = 'DANFE - Envio Automático';
            $mail->Body = "<p>Segue em anexo o DANFE referente à chave de acesso: {$chave}.</p>";
            $mail->isHTML(true);
            $mail->addAttachment($pdfPath);
            $mail->send();
            $logger->write("E-mail enviado para: $email_destinatario");

            self::atualizarStatusNota($chave, $email_destinatario, $logger);
        } catch (Exception $e) {
            $logger->write("Erro ao enviar e-mail: " . $e->getMessage());
        }
    }

    public static function atualizarStatusNota($chave, $email_destinatario, $logger)
    {
        try {
            TTransaction::open('protheus');
            $conn = TTransaction::get();
            $sql = "UPDATE SF1990 SET status = 'Enviado', email_enviado = ? WHERE F1_CHVNFE = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$email_destinatario, $chave]);
            $logger->write("Status atualizado para 'Enviado' para a nota: $chave");
            TTransaction::close();
        } catch (Exception $e) {
            $logger->write("Erro ao atualizar status da nota: " . $e->getMessage());
        }
    }
}

//comando para executar no cmd

//C:\xampp\php>php.exe c:\xampp\htdocs\EXECUT_Projeto_Final\EXECUT_Projeto_Final\template\cmd.php "class=NotaFiscalJob&method=run"