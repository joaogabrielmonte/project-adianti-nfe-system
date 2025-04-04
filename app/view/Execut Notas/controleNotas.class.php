<?php

use Adianti\Control\TPage;
use Adianti\Control\TAction;
use Adianti\Database\TTransaction;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TButton;
use Adianti\Widget\Form\TDate;
use Adianti\Widget\Form\TForm;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Wrapper\BootstrapFormBuilder;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class controleNotas extends TPage
{
    public function __construct()
    {
        parent::__construct();

        // Criando o formulário
        $this->form = new BootstrapFormBuilder('form_chaveacesso');
        $this->form->setFormTitle('Consulta de Nota Fiscal');

        // Campo para inserir a chave de acesso
        $chaveacesso = new TEntry('chaveacesso');
        $chaveacesso->setSize('100%');

        // Campos para as datas de início e fim com calendário
        $dataInicio = new TDate('data_inicio');
        $dataInicio->setMask('dd/mm/yyyy');
        $dataInicio->setSize('50%');

        $dataFim = new TDate('data_fim');
        $dataFim->setMask('dd/mm/yyyy');
        $dataFim->setSize('50%');

        // Botão de pesquisa
        $botaoPesquisar = new TButton('btnPesquisar');
        $botaoPesquisar->setLabel('Pesquisar');
        $botaoPesquisar->setAction(new TAction([$this, 'onPesquisar']), 'Pesquisar');
        $botaoPesquisar->setImage('fa:search');

        // Adicionando os campos no formulário
        $this->form->addFields([new TLabel('Chave de Acesso')], [$chaveacesso]);
        $this->form->addFields([new TLabel('Data Início')], [$dataInicio]);
        $this->form->addFields([new TLabel('Data Fim')], [$dataFim]);
        $this->form->addFields([], [$botaoPesquisar]);

        // Definindo os campos do formulário
        $this->form->setFields([$chaveacesso, $dataInicio, $dataFim, $botaoPesquisar]);

        // Adicionando o formulário na tela
        parent::add($this->form);
    }

    public function onPesquisar($param)
    {
        try {
            // Obtendo os parâmetros de pesquisa
            $chaveacesso = $param['chaveacesso'] ?? null;
            $dataInicio = !empty($param['data_inicio']) ? DateTime::createFromFormat('d/m/Y', $param['data_inicio']) : null;
            $dataFim = !empty($param['data_fim']) ? DateTime::createFromFormat('d/m/Y', $param['data_fim']) : null;

            // Verificando se as datas são válidas e convertendo para o formato Ymd
            if ($dataInicio && $dataInicio instanceof DateTime) {
                $dataInicio = $dataInicio->format('Ymd');
            } else {
                $dataInicio = null;
            }

            if ($dataFim && $dataFim instanceof DateTime) {
                $dataFim = $dataFim->format('Ymd');
            } else {
                $dataFim = null;
            }

            // Caso não tenha chave de acesso e nem intervalo de datas, exibimos um erro
            if (empty($chaveacesso) && empty($dataInicio) && empty($dataFim)) {
                new TMessage('error', 'Informe a chave de acesso ou o intervalo de datas.');
                return;
            }

            TTransaction::open('protheus');
            $conn = TTransaction::get();

            // Construindo a query dinamicamente
            $sql = "SELECT * FROM CKOCOL WHERE 1=1";
            $params = [];

            // Filtro pela chave de acesso
            if ($chaveacesso) {
                $sql .= " AND CKO_CHVDOC = ?";
                $params[] = $chaveacesso;
            }

            // Filtro pelas datas
            if ($dataInicio && $dataFim) {
                $sql .= " AND CKO_DT_IMP BETWEEN ? AND ?";
                $params[] = $dataInicio;
                $params[] = $dataFim;
            } elseif ($dataInicio) {
                $sql .= " AND CKO_DT_IMP >= ?";
                $params[] = $dataInicio;
            } elseif ($dataFim) {
                $sql .= " AND CKO_DT_IMP <= ?";
                $params[] = $dataFim;
            }

            // Executando a consulta
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);

            // Obtendo os resultados
            $notas = $stmt->fetchAll();
            TTransaction::close();

            // Verificando se encontrou alguma nota
            if (!$notas) {
                new TMessage('error', 'Nenhuma nota encontrada.');
                return;
            }

            // Processando as notas
            foreach ($notas as $nota) {
                $chave = $nota['CKO_CHVDOC'];
                $notaFiscal = new NotaFiscalModel;

                // Obtendo o PDF da nota fiscal em Base64
                $base64PDF = $notaFiscal->obterPDFBase64($chave);
                if (!$base64PDF) {
                    new TMessage('error', "Erro ao obter o PDF em Base64 da nota: $chave");
                    continue;
                }

                // Salvando o PDF gerado
                $pdfPath = "app/output/danfe_{$chave}.pdf";
                $notaFiscal->salvarPDF($base64PDF, $pdfPath);

                // Verificando se o PDF foi salvo corretamente
                if (!file_exists($pdfPath)) {
                    new TMessage('error', "Erro ao salvar o PDF da nota: $chave");
                    continue;
                }

                // Obtendo o e-mail do destinatário
                $emailDestinatario = $this->obterEmailDestinatario();

                // Enviando o PDF por e-mail
                if ($emailDestinatario) {
                    $this->enviarEmail($pdfPath, $emailDestinatario, $chave);
                } else {
                    new TMessage('error', 'E-mail do destinatário não configurado nas preferências.');
                }
            }

            // Mensagem de sucesso
            new TMessage('info', 'Os PDFs foram gerados e enviados com sucesso!');
        } catch (Exception $e) {
            // Capturando erros
            new TMessage('error', 'Erro: ' . $e->getMessage());
        }
    }

    // Função para obter o e-mail do destinatário
    private function obterEmailDestinatario()
    {
        try {
            TTransaction::open('protheus');
            $conn = TTransaction::get();
            $sql = "SELECT value FROM notas_preferences WHERE id = 'email_destinatario'";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch();
            TTransaction::close();

            if ($result && !empty($result['value'])) {
                return $result['value']; // Retorna o e-mail
            } else {
                throw new Exception("E-mail do destinatário não encontrado.");
            }
        } catch (Exception $e) {
            TTransaction::rollback();
            throw new Exception("Erro ao obter o e-mail do destinatário: " . $e->getMessage());
        }
    }

    public function enviarEmail($pdfPath, $emailDestinatario, $chaveacesso)
    {
        try {
            // Preferências de e-mail
            $preferences = SystemPreference::getAllPreferences();
            $email_origem = $preferences['mail_from'] ?? '';
            $smtp_host = $preferences['smtp_host'] ?? '';
            $smtp_porta = $preferences['smtp_port'] ?? '';
            $smtp_usuario = $preferences['smtp_user'] ?? '';
            $smtp_senha = $preferences['smtp_pass'] ?? '';
            $email_suporte = $preferences['mail_support'] ?? '';

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
            $mail->addAddress($emailDestinatario);
            $mail->addReplyTo($email_suporte, 'Suporte');
            $mail->Subject = 'DANFE - Envio Automático';
            $mail->Body = "<p>Olá,</p><p>Segue em anexo o DANFE referente à chave de acesso: {$chaveacesso}.</p>";
            $mail->isHTML(true);
            $mail->addAttachment($pdfPath);
            $mail->send();
        } catch (Exception $e) {
            throw new Exception('Erro ao enviar o e-mail: ' . $e->getMessage());
        }
    }
}
