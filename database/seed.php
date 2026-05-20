<?php
require_once __DIR__ . '/../config/config.php';

echo "Iniciando o seeding...\n";

// 1. Criar utilizador de teste para ser o autor dos projetos, caso nao exista
$res = $mysqli->query("SELECT id FROM usuarios WHERE email='admin_seeder@ispsn.org' LIMIT 1");
if ($res && $res->num_rows > 0) {
    $user_id = $res->fetch_assoc()['id'];
} else {
    $mysqli->query("INSERT INTO usuarios (nome, email, senha_hash, perfil, tipo_utilizador, activo) VALUES ('Admin Seeder', 'admin_seeder@ispsn.org', '123', 'admin', 'docente', 1)");
    $user_id = $mysqli->insert_id;
}

// 2. Criar Projetos Destaque
$projetos = [
    ['titulo' => 'EducaTech Angola', 'descricao' => 'Uma plataforma inovadora de ensino à distância para conectar estudantes do ensino médio a tutores universitários em tempo real.', 'area_tematica' => 'tecnologia', 'fase' => 'mvp', 'tipo' => 'tecnologica'],
    ['titulo' => 'AgroConnect', 'descricao' => 'Sistema integrado para gestão de colheitas e ligação direta entre pequenos agricultores locais e os grandes mercados da capital.', 'area_tematica' => 'ambiente', 'fase' => 'mercado', 'tipo' => 'tradicional'],
    ['titulo' => 'MedISPSN', 'descricao' => 'Aplicação mobile para marcação de consultas e triagem inteligente utilizando inteligência artificial, focada em clínicas de baixo custo.', 'area_tematica' => 'saude', 'fase' => 'ideacao', 'tipo' => 'social']
];

foreach($projetos as $p) {
    // Check if exists
    $stmt = $mysqli->prepare("SELECT id FROM projetos WHERE titulo=?");
    $stmt->bind_param('s', $p['titulo']);
    $stmt->execute();
    if($stmt->get_result()->num_rows === 0) {
        $ins = $mysqli->prepare("INSERT INTO projetos (criado_por, titulo, descricao, area_tematica, fase, tipo, estado, destaque_publico, pontos) VALUES (?, ?, ?, ?, ?, ?, 'incubado', 1, 150)");
        $ins->bind_param('isssss', $user_id, $p['titulo'], $p['descricao'], $p['area_tematica'], $p['fase'], $p['tipo']);
        $ins->execute();
        echo "Projeto criado: {$p['titulo']}\n";
    }
}

// 3. Notícias (publicacoes_website)
$noticias = [
    ['titulo' => 'ISPSN lança 3ª Edição da Incubadora Académica', 'resumo' => 'O evento de abertura reuniu mais de 500 estudantes e investidores num momento histórico para a inovação em Angola.', 'imagem' => 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=800&q=80', 'conteudo' => 'Conteúdo completo da notícia aqui...'],
    ['titulo' => 'Startup de Saúde ganha prémio internacional', 'resumo' => 'A MedISPSN foi reconhecida na feira de inovação em Lisboa pelo seu algoritmo inovador de triagem.', 'imagem' => 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?w=800&q=80', 'conteudo' => 'Conteúdo completo da notícia aqui...'],
    ['titulo' => 'Novos parceiros investem no ecossistema', 'resumo' => 'Grandes bancos e empresas de telecomunicações assinam memorando com a Incubadora do ISPSN.', 'imagem' => 'https://images.unsplash.com/photo-1556761175-4b46a572b786?w=800&q=80', 'conteudo' => 'Conteúdo completo da notícia aqui...'],
    ['titulo' => 'Workshop de Inteligência Artificial', 'resumo' => 'Inscreva-se na próxima sessão prática sobre como aplicar IA na gestão do seu pequeno negócio.', 'imagem' => 'https://images.unsplash.com/photo-1485827404703-89b55fcc595e?w=800&q=80', 'conteudo' => 'Conteúdo completo da notícia aqui...']
];

foreach($noticias as $n) {
    $stmt = $mysqli->prepare("SELECT id FROM publicacoes_website WHERE titulo=?");
    $stmt->bind_param('s', $n['titulo']);
    $stmt->execute();
    if($stmt->get_result()->num_rows === 0) {
        $ins = $mysqli->prepare("INSERT INTO publicacoes_website (titulo, resumo, conteudo, imagem, status) VALUES (?, ?, ?, ?, 'publicado')");
        $ins->bind_param('ssss', $n['titulo'], $n['resumo'], $n['conteudo'], $n['imagem']);
        $ins->execute();
        echo "Notícia criada: {$n['titulo']}\n";
    }
}

// 4. Galeria (galeria_website)
$imagens = [
    ['titulo' => 'Cerimónia de Abertura', 'descricao' => 'O auditório cheio de estudantes motivados para inovar.', 'imagem' => 'https://images.unsplash.com/photo-1505373877841-8d25f7d46678?w=800&q=80'],
    ['titulo' => 'Sessão de Mentoria', 'descricao' => 'Empreendedores recebendo feedback prático.', 'imagem' => 'https://images.unsplash.com/photo-1515162816999-a0c47dc192f7?w=800&q=80'],
    ['titulo' => 'Trabalho em Equipa', 'descricao' => 'Desenvolvimento de MVPs no nosso laboratório.', 'imagem' => 'https://images.unsplash.com/photo-1522071820081-009f0129c71c?w=800&q=80'],
    ['titulo' => 'Pitch Final', 'descricao' => 'Apresentação para investidores parceiros.', 'imagem' => 'https://images.unsplash.com/photo-1559223607-b4d0555ae227?w=800&q=80']
];

foreach($imagens as $i) {
    $stmt = $mysqli->prepare("SELECT id FROM galeria_website WHERE titulo=?");
    $stmt->bind_param('s', $i['titulo']);
    $stmt->execute();
    if($stmt->get_result()->num_rows === 0) {
        $ins = $mysqli->prepare("INSERT INTO galeria_website (titulo, descricao, imagem, ativo, ordem) VALUES (?, ?, ?, 1, 1)");
        $ins->bind_param('sss', $i['titulo'], $i['descricao'], $i['imagem']);
        $ins->execute();
        echo "Imagem de galeria inserida: {$i['titulo']}\n";
    }
}

echo "Seeding concluido!\n";
?>
