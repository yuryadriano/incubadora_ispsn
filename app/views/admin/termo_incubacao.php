<?php
// app/views/admin/termo_incubacao.php
require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../utils/GeradorPDF.php';

obrigarLogin();

$idTermo = (int)($_GET['id'] ?? 0);

if ($idTermo <= 0) {
    die("Termo não especificado.");
}

// Buscar o termo
$stmt = $mysqli->prepare("
    SELECT t.*, p.titulo as proj_titulo, p.criado_por
    FROM termos_incubacao t
    JOIN projetos p ON p.id = t.id_projeto
    WHERE t.id = ?
");
$stmt->bind_param('i', $idTermo);
$stmt->execute();
$termo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$termo) {
    die("Termo não encontrado.");
}

// Se o utilizador quer exportar para PDF
if (isset($_GET['pdf']) && $_GET['pdf'] == 1) {
    \App\Utils\GeradorPDF::streamTermo($termo);
    exit;
}

$perfil = $_SESSION['usuario_perfil'] ?? 'utilizador';
$idUsuario = (int)$_SESSION['usuario_id'];

// Layout imports
$tituloPagina = "Termo de Incubação - " . $termo['codigo_termo'];
$paginaActiva = "gestao_metas";

require_once __DIR__ . '/../partials/_layout.php';
?>

<div class="container-fluid px-4 py-4">
    <!-- Breadcrumb e Voltar -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">📄 Termo de Incubação</h1>
            <p class="text-muted mb-0">Ref: <?php echo htmlspecialchars($termo['codigo_termo']); ?></p>
        </div>
        <div class="d-flex gap-2">
            <a href="/incubadora_ispsn/app/views/admin/termo_incubacao.php?id=<?php echo $idTermo; ?>&pdf=1" target="_blank" class="btn btn-danger">
                <i class="fas fa-file-pdf me-2"></i>Exportar PDF
            </a>
            <a href="javascript:history.back()" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Voltar
            </a>
        </div>
    </div>

    <!-- Alertas Flash -->
    <?php if (isset($_SESSION['flash_ok'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['flash_ok']; unset($_SESSION['flash_ok']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['flash_erro'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['flash_erro']; unset($_SESSION['flash_erro']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['flash_aviso'])): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['flash_aviso']; unset($_SESSION['flash_aviso']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Document Preview -->
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-body bg-light p-4" style="min-height: 800px; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;">
                    <div class="bg-white p-5 border shadow-sm rounded mx-auto" style="max-width: 800px; color: #333;">
                        <!-- O mesmo HTML gerado pelo GeradorPDF para consistência visual -->
                        <?php
                            $html = \App\Utils\GeradorPDF::gerarTermoHTML($termo);
                            // Extrair o conteúdo do body para não duplicar tags html/head
                            if (preg_match('/<body>(.*?)<\/body>/is', $html, $matches)) {
                                echo $matches[1];
                            } else {
                                echo $html;
                            }
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar Acções -->
        <div class="col-lg-4">
            <!-- Informações Gerais -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 bg-primary text-white">
                    <h6 class="m-0 font-weight-bold">Status do Termo</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="text-muted d-block small">Estado Atual</label>
                        <?php if ($termo['estado'] === 'assinado'): ?>
                            <span class="badge bg-success p-2 fs-6"><i class="fas fa-check-circle me-1"></i> Assinado</span>
                        <?php elseif ($termo['estado'] === 'pendente_assinatura'): ?>
                            <span class="badge bg-warning text-dark p-2 fs-6"><i class="fas fa-clock me-1"></i> Pendente Assinatura</span>
                        <?php elseif ($termo['estado'] === 'revogado'): ?>
                            <span class="badge bg-danger p-2 fs-6"><i class="fas fa-ban me-1"></i> Revogado</span>
                        <?php else: ?>
                            <span class="badge bg-secondary p-2 fs-6"><?php echo ucfirst($termo['estado']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label class="text-muted d-block small">Criado Em</label>
                        <strong><?php echo date('d/m/Y H:i', strtotime($termo['criado_em'])); ?></strong>
                    </div>

                    <?php if ($termo['estado'] === 'assinado'): ?>
                        <div class="mb-3">
                            <label class="text-muted d-block small">Assinado Em</label>
                            <strong><?php echo date('d/m/Y H:i', strtotime($termo['assinado_em'])); ?></strong>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Formulário de Assinatura se for SuperAdmin e estiver pendente -->
            <?php if ($termo['estado'] === 'pendente_assinatura' && $perfil === 'superadmin'): ?>
                <div class="card shadow border-left-warning mb-4">
                    <div class="card-header py-3 bg-warning text-dark">
                        <h6 class="m-0 font-weight-bold"><i class="fas fa-signature me-1"></i> Assinatura Digital do Reitor/Presidente</h6>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted">A assinatura deste termo mudará o estado do projecto para <strong>Incubado</strong> e inicializará as metas da fase de Ideação.</p>
                        
                        <form action="/incubadora_ispsn/app/controllers/incubacao_action.php" method="POST" class="needs-validation" novalidate>
                            <input type="hidden" name="action" value="assinar_termo">
                            <input type="hidden" name="id_termo" value="<?php echo $idTermo; ?>">
                            <input type="hidden" name="redirect" value="/incubadora_ispsn/app/views/admin/termo_incubacao.php?id=<?php echo $idTermo; ?>">

                            <div class="mb-3">
                                <label for="senha_confirmacao" class="form-label font-weight-bold">Confirme a sua senha de acesso:</label>
                                <input type="password" class="form-control" id="senha_confirmacao" name="senha_confirmacao" placeholder="Senha do Administrador" required>
                                <div class="invalid-feedback">A senha é obrigatória para assinar digitalmente.</div>
                            </div>

                            <button type="submit" class="btn btn-success w-100 py-2">
                                <i class="fas fa-signature me-1"></i> Assinar Digitalmente
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Botão de Revogação se estiver assinado e for SuperAdmin -->
            <?php if ($termo['estado'] === 'assinado' && $perfil === 'superadmin'): ?>
                <div class="card shadow border-left-danger mb-4">
                    <div class="card-header py-3 bg-danger text-white">
                        <h6 class="m-0 font-weight-bold"><i class="fas fa-exclamation-triangle me-1"></i> Revogar Termo</h6>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted">A revogação invalida o termo e altera o histórico de incubação.</p>
                        <form action="/incubadora_ispsn/app/controllers/incubacao_action.php" method="POST" onsubmit="return confirm('Tem a certeza que deseja revogar este termo? Esta acção é irreversível.')">
                            <input type="hidden" name="action" value="revogar_termo">
                            <input type="hidden" name="id_termo" value="<?php echo $idTermo; ?>">
                            <input type="hidden" name="redirect" value="/incubadora_ispsn/app/views/admin/termo_incubacao.php?id=<?php echo $idTermo; ?>">

                            <div class="mb-3">
                                <label for="motivo_revogacao" class="form-label">Motivo da Revogação:</label>
                                <textarea class="form-control" id="motivo_revogacao" name="motivo_revogacao" rows="3" required placeholder="Especifique o motivo da rescisão/revogação do termo..."></textarea>
                            </div>

                            <button type="submit" class="btn btn-danger w-100">
                                <i class="fas fa-ban me-1"></i> Revogar Termo
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../partials/_layout_end.php';
?>
