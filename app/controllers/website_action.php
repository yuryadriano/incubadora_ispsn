<?php
require_once __DIR__ . '/../../config/auth.php';
obrigarPerfil(['admin', 'superadmin']);

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$idAdmin = (int)$_SESSION['usuario_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
}

// ── ALTERNAR DESTAQUE DE STARTUP NA VITRINE PÚBLICA ──
if ($action === 'toggle_destaque_startup') {
    $idProjeto = (int)($_POST['id_projeto'] ?? 0);
    if ($idProjeto > 0) {
        $stmt = $mysqli->prepare("UPDATE projetos SET destaque_publico = 1 - COALESCE(destaque_publico, 0) WHERE id = ?");
        $stmt->bind_param('i', $idProjeto);
        $stmt->execute();
        $stmt->close();
        $_SESSION['flash_ok'] = "Vitrine de startups atualizada no Website!";
    }
    header("Location: /incubadora_ispsn/app/views/admin/website.php");
    exit;
}

// ── ELIMINAR STARTUP (DE TESTE OU INVÁLIDA) ──
if ($action === 'delete_startup') {
    $idProjeto = (int)($_POST['id_projeto'] ?? 0);
    if ($idProjeto > 0 && function_exists('eliminar_projeto_completo')) {
        eliminar_projeto_completo($mysqli, $idProjeto);
        $_SESSION['flash_ok'] = "Startup eliminada do sistema com sucesso!";
    }
    header("Location: /incubadora_ispsn/app/views/admin/website.php");
    exit;
}

// ── ATUALIZAR CONFIGURAÇÕES GLOBAIS ──────────
if ($action === 'update_website') {
    $configs = $_POST['config'] ?? [];
    foreach ($configs as $chave => $valor) {
        $stmt = $mysqli->prepare("UPDATE config_website SET valor = ? WHERE chave = ?");
        $stmt->bind_param('ss', $valor, $chave);
        $stmt->execute();
    }
    $_SESSION['flash_ok'] = "Configurações globais atualizadas!";
    header("Location: /incubadora_ispsn/app/views/admin/website.php"); exit;
}

// ── GUARDAR/EDITAR PUBLICAÇÃO ────────────────
if ($action === 'save_pub') {
    $id        = (int)($_POST['id'] ?? 0);
    $titulo    = limpar($_POST['titulo']);
    $conteudo  = $_POST['conteudo']; 
    $resumo    = mb_substr(strip_tags($conteudo), 0, 150) . '...';
    $categoria = limpar($_POST['categoria']);
    $status    = limpar($_POST['status']);
    
    $urlImagem = '';
    if (!empty($_FILES['imagem']['name'])) {
        $erroUp = '';
        $ext = validar_upload($_FILES['imagem'], 'imagem', 5 * 1024 * 1024, $erroUp);
        if ($ext === false) {
            $_SESSION['flash_erro'] = "Imagem de capa inválida: $erroUp";
            header("Location: /incubadora_ispsn/app/views/admin/website.php"); exit;
        }
        $nomeImg = "pub_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
        $destino = __DIR__ . "/../../assets/img/blog/" . $nomeImg;
        if (!is_dir(dirname($destino))) mkdir(dirname($destino), 0755, true);
        if (move_uploaded_file($_FILES['imagem']['tmp_name'], $destino)) {
            $urlImagem = "/incubadora_ispsn/assets/img/blog/" . $nomeImg;
        }
    }

    $galeriaUrls = [];
    if (isset($_FILES['galeria']) && !empty($_FILES['galeria']['name'][0])) {
        foreach ($_FILES['galeria']['name'] as $k => $val) {
            if ($val === '') continue;
            $itemGaleria = [
                'name'     => $_FILES['galeria']['name'][$k] ?? '',
                'tmp_name' => $_FILES['galeria']['tmp_name'][$k] ?? '',
                'size'     => $_FILES['galeria']['size'][$k] ?? 0,
                'error'    => $_FILES['galeria']['error'][$k] ?? UPLOAD_ERR_NO_FILE,
            ];
            $erroUp = '';
            $ext = validar_upload($itemGaleria, 'imagem', 5 * 1024 * 1024, $erroUp);
            if ($ext === false) {
                $_SESSION['flash_erro'] = "Imagem da galeria inválida: $erroUp";
                header("Location: /incubadora_ispsn/app/views/admin/website.php"); exit;
            }
            $nomeImg = "pub_gal_" . time() . "_{$k}_" . bin2hex(random_bytes(4)) . "." . $ext;
            $destino = __DIR__ . "/../../assets/img/blog/" . $nomeImg;
            if (!is_dir(dirname($destino))) mkdir(dirname($destino), 0755, true);
            if (move_uploaded_file($itemGaleria['tmp_name'], $destino)) {
                $galeriaUrls[] = "/incubadora_ispsn/assets/img/blog/" . $nomeImg;
            }
        }
    }
    $strGaleria = !empty($galeriaUrls) ? implode(',', $galeriaUrls) : null;

    if ($id) {
        $sql = "UPDATE publicacoes_website SET titulo=?, resumo=?, conteudo=?, categoria=?, status=?";
        $params = [$titulo, $resumo, $conteudo, $categoria, $status];
        $types = "sssss";
        
        if ($urlImagem) { $sql .= ", imagem=?"; $params[] = $urlImagem; $types .= "s"; }
        if ($strGaleria) { $sql .= ", galeria=?"; $params[] = $strGaleria; $types .= "s"; }
        
        $sql .= " WHERE id=?";
        $params[] = $id; $types .= "i";
        
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param($types, ...$params);
    } else {
        $stmt = $mysqli->prepare("INSERT INTO publicacoes_website (titulo, resumo, conteudo, imagem, galeria, categoria, status, criado_por) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param('sssssssi', $titulo, $resumo, $conteudo, $urlImagem, $strGaleria, $categoria, $status, $idAdmin);
    }
    
    if ($stmt->execute()) $_SESSION['flash_ok'] = "Publicação guardada!";
    else $_SESSION['flash_erro'] = "Erro ao guardar.";
    
    header("Location: /incubadora_ispsn/app/views/admin/website.php"); exit;
}

// ── GUARDAR NA GALERIA ───────────────────────
if ($action === 'save_galeria') {
    $titulo    = limpar($_POST['titulo']);
    $descricao = limpar($_POST['descricao']);
    
    if (!empty($_FILES['imagem']['name'])) {
        $erroUp = '';
        $ext = validar_upload($_FILES['imagem'], 'imagem', 5 * 1024 * 1024, $erroUp);
        if ($ext === false) {
            $_SESSION['flash_erro'] = "Imagem inválida: $erroUp";
            header("Location: /incubadora_ispsn/app/views/admin/website.php"); exit;
        }
        $nomeImg = "gal_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
        $destino = __DIR__ . "/../../assets/img/galeria/" . $nomeImg;
        if (!is_dir(dirname($destino))) mkdir(dirname($destino), 0755, true);
        
        if (move_uploaded_file($_FILES['imagem']['tmp_name'], $destino)) {
            $url = "/incubadora_ispsn/assets/img/galeria/" . $nomeImg;
            $stmt = $mysqli->prepare("INSERT INTO galeria_website (titulo, descricao, imagem) VALUES (?,?,?)");
            $stmt->bind_param('sss', $titulo, $descricao, $url);
            $stmt->execute();
            $_SESSION['flash_ok'] = "Imagem adicionada à galeria!";
        }
    }
    header("Location: /incubadora_ispsn/app/views/admin/website.php"); exit;
}

// ── ELIMINAR ────────────────────────────────
if ($action === 'delete_pub') {
    $id = (int)($_GET['id'] ?? 0);
    $mysqli->query("DELETE FROM publicacoes_website WHERE id=$id");
    $_SESSION['flash_ok'] = "Removido.";
    header("Location: /incubadora_ispsn/app/views/admin/website.php"); exit;
}

if ($action === 'delete_galeria') {
    $id = (int)($_GET['id'] ?? 0);
    $mysqli->query("DELETE FROM galeria_website WHERE id=$id");
    $_SESSION['flash_ok'] = "Removido da galeria.";
    header("Location: /incubadora_ispsn/app/views/admin/website.php"); exit;
}
