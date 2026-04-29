<?php
require_once __DIR__ . '/../config/auth.php';
obrigarLogin();

$perfil = $_SESSION['usuario_perfil'];

// Sobreposição para Simulação do Super Admin
if ($perfil === 'superadmin' && !empty($_SESSION['contexto_simulado'])) {
    $perfil = $_SESSION['contexto_simulado'];
}

switch ($perfil) {
    case 'superadmin':
        require_once __DIR__ . '/../app/views/dashboard/superadmin.php';
        break;

    case 'admin':
        require_once __DIR__ . '/../app/views/dashboard/admin.php';
        break;

    case 'funcionario':
        header("Location: /incubadora_ispsn/app/views/admin/gestao_espacos.php");
        exit;

    case 'mentor':
        require_once __DIR__ . '/../app/views/dashboard/mentor.php';
        break;

    default: // utilizador (estudante/docente)
        require_once __DIR__ . '/../app/views/dashboard/utilizador.php';
        break;
}
