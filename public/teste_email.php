<?php
// public/teste_email.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/utils/Mailer.php';

use App\Utils\Mailer;

echo "<h1>Teste de Envio de E-mail</h1>";
echo "<hr>";

$errorInfo = "";
if (Mailer::send(MAIL_USER, "Teste de Funcionamento - Incubadora", "Se você recebeu este e-mail, as configurações de SMTP estão corretas!", $errorInfo)) {
    echo "<h2 style='color:green'>SUCESSO! O e-mail foi enviado.</h2>";
    echo "Verifique a sua caixa de entrada (incluindo SPAM).";
} else {
    echo "<h2 style='color:red'>ERRO AO ENVIAR.</h2>";
    echo "<div style='background: #fee; padding: 10px; border: 1px solid #fcc; color: #a00;'>";
    echo "<strong>Detalhe do erro:</strong><br>" . htmlspecialchars($errorInfo);
    echo "</div>";
    echo "<p>Causas prováveis:</p>";
    echo "<ul>
            <li>A senha pode estar incorreta.</li>
            <li>O servidor SMTP (Host) não está correto.</li>
            <li>A porta (587 ou 465) pode ser diferente.</li>
          </ul>";
}
