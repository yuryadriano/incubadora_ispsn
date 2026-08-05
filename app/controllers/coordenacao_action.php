<?php
// app/controllers/coordenacao_action.php
require_once __DIR__ . '/../../config/auth.php';
obrigarPerfil(['admin', 'superadmin']);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'salvar_coordenacao':
        $curso = trim($_POST['curso'] ?? '');
        $coordenador_nome = trim($_POST['coordenador_nome'] ?? '');
        $coordenador_email = trim($_POST['coordenador_email'] ?? '');

        if (empty($curso) || empty($coordenador_email)) {
            $_SESSION['flash_erro'] = "Preencha todos os campos obrigatórios para definir a coordenação.";
            header("Location: /incubadora_ispsn/app/views/admin/coordenacoes.php");
            exit;
        }

        // Verificar se utilizador existe ou criar notificação
        $user = encontrarUsuarioPorEmail($coordenador_email);
        if ($user) {
            enviarNotificacao(
                $user['id'], 
                "Atribuição de Coordenação", 
                "Foste atribuído como Coordenador do $curso na plataforma da Incubadora ISPSN.",
                "sucesso"
            );
        }

        $_SESSION['flash_ok'] = "Coordenação do $curso configurada com sucesso para o docente $coordenador_nome ($coordenador_email).";
        header("Location: /incubadora_ispsn/app/views/admin/coordenacoes.php?curso=" . urlencode($curso));
        exit;

    default:
        header("Location: /incubadora_ispsn/app/views/admin/coordenacoes.php");
        exit;
}
