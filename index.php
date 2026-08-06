<?php
// Redireciona o tráfego da raiz para o website público
$uri = $_SERVER['REQUEST_URI'] ?? '/';
if (strpos($uri, '/incubadora_ispsn/') === 0) {
    header("Location: /incubadora_ispsn/public/website/");
} else {
    header("Location: /public/website/");
}
exit;
