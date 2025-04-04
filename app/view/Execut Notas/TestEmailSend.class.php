<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Database\TCriteria;
use Adianti\Database\TFilter;
use Adianti\Database\TTransaction;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Wrapper\BootstrapFormBuilder;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class TestEmailSend extends TPage
{
    private $form;

    public function __construct()
    {
        parent::__construct();

        // Inicializa o formulário
        $this->form = new BootstrapFormBuilder('form_test_email');
        $this->form->setFormTitle('Teste de Envio de E-mail');

        // Campo para e-mail de destinatário
        $email_destinatario = new TEntry('email_destinatario');
        $email_destinatario->setSize('70%');
        $email_destinatario->placeholder = 'Digite o e-mail do destinatário';

        $this->form->addFields([new TLabel('E-mail Destinatário')], [$email_destinatario]);

        // Botão de envio
        $btn_enviar = $this->form->addAction('Enviar E-mail', new TAction([$this, 'onSend']), 'far:paper-plane');
        $btn_enviar->class = 'btn btn-sm btn-success';

        $container = new TVBox;
        $container->{'style'} = 'width: 100%';
        $container->add($this->form);

        parent::add($container);
    }

    public function onSend($param)
    {
        try {
            // Inicia a transação
            TTransaction::open('permission');

            // Valida se o e-mail foi preenchido
            $email_destinatario = $param['email_destinatario'];
            if (empty($email_destinatario)) {
                throw new Exception('Por favor, insira um e-mail de destinatário.');
            }

            // Caminho de teste para o PDF (simulação de um arquivo DANFE)
            $pdfPath = 'app/output/teste_danfe.pdf';

            // Certifique-se de que o arquivo PDF existe
            if (!file_exists($pdfPath)) {
                file_put_contents($pdfPath, 'Este é um DANFE de teste gerado automaticamente.');
            }

            // Recupera as configurações de preferências
            $preferences = SystemPreference::getAllPreferences();

            // Recupera e-mail de origem e outras configurações de SMTP
            $email_origem = $preferences['mail_from'] ?? '';
            $smtp_host = $preferences['smtp_host'] ?? '';
            $smtp_porta = $preferences['smtp_port'] ?? '';
            $smtp_usuario = $preferences['smtp_user'] ?? '';
            $smtp_senha = $preferences['smtp_pass'] ?? '';
            $email_suporte = $preferences['mail_support'] ?? '';

            // Instancia o PHPMailer
            $mail = new PHPMailer(true);

            // Configuração do servidor SMTP
            $mail->isSMTP();
            $mail->Host = $smtp_host;
            $mail->SMTPAuth = true;
            $mail->Username = $smtp_usuario;
            $mail->Password = $smtp_senha;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $smtp_porta;

            // Definir remetente e destinatário
            $mail->setFrom($email_origem, 'Sistema de Envio de DANFE');
            $mail->addAddress($email_destinatario);
            $mail->addReplyTo($email_suporte, 'Suporte');

            // Assunto e corpo do e-mail
            $assunto = 'Teste de Envio de DANFE';
            $mensagem = '<p>Olá,</p><p>Segue em anexo um DANFE de teste enviado pelo sistema.</p>';

            $mail->Subject = $assunto;
            $mail->Body    = $mensagem;
            $mail->isHTML(true);

            // Adicionar o PDF em anexo
            $mail->addAttachment($pdfPath);

            // Enviar o e-mail
            $mail->send();

            // Mensagem de sucesso
            new TMessage('info', 'E-mail enviado com sucesso para ' . $email_destinatario);

            // Fecha a transação
            TTransaction::close();
        } catch (Exception $e) {
            // Mensagem de erro
            new TMessage('error', 'Erro ao enviar o e-mail: ' . $e->getMessage());

            // Se ocorrer um erro, faz o rollback da transação
            TTransaction::rollback();
        }
    }
}
