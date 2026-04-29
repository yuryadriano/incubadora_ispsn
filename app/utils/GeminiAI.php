<?php
namespace App\Utils;

class GeminiAI {
    // Substitua pela sua chave da Google AI Studio (https://aistudio.google.com/)
    private static $apiKey = ""; 
    private static $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent";

    public static function analisarProjeto($titulo, $descricao, $problema, $solucao) {
        if (empty(self::$apiKey)) {
            return self::gerarFeedbackSimulado($titulo);
        }

        $prompt = "Aje como um consultor sénior de inovação e startups. Analise este projeto da Incubadora ISPSN:
        TÍTULO: $titulo
        DESCRIÇÃO: $descricao
        PROBLEMA: $problema
        SOLUÇÃO: $solucao
        
        Forneça um feedback estruturado em HTML simples (h4, p, ul, li) com:
        1. Análise de Viabilidade
        2. Pontos Fortes
        3. Possíveis Riscos
        4. Sugestão Prática de Próximo Passo.
        Seja encorajador mas crítico.";

        $data = [
            "contents" => [
                ["parts" => [["text" => $prompt]]]
            ]
        ];

        $ch = curl_init(self::$apiUrl . "?key=" . self::$apiKey);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) return "Erro na ligação à IA: " . $err;

        $result = json_decode($response, true);
        return $result['candidates'][0]['content']['parts'][0]['text'] ?? "A IA não conseguiu gerar uma resposta no momento.";
    }

    private static function gerarFeedbackSimulado($titulo) {
        // Feedback realista para demonstração sem API Key
        return "
        <div class='alert alert-info py-3'>
            <i class='fa fa-robot me-2'></i> <strong>Modo Simulação Ativo:</strong> Configure a sua API Key no arquivo <code>GeminiAI.php</code> para receber análises reais.
        </div>
        <h4><i class='fa fa-chart-line me-2 text-primary'></i>Análise Preliminar: $titulo</h4>
        <p>Com base na sua submissão, aqui estão as primeiras impressões do nosso motor de inteligência:</p>
        <ul>
            <li><strong>Viabilidade:</strong> O projeto apresenta um conceito sólido com clara aplicação no mercado angolano.</li>
            <li><strong>Ponto Forte:</strong> A solução aborda um problema real que afeta uma grande base de utilizadores.</li>
            <li><strong>Risco:</strong> A barreira de entrada tecnológica pode ser um desafio se não houver uma equipa técnica dedicada.</li>
            <li><strong>Próximo Passo:</strong> Recomendamos a criação de uma <em>Landing Page</em> simples para validar o interesse real dos utilizadores antes de investir no desenvolvimento completo.</li>
        </ul>
        <p class='small text-muted'>Análise gerada em: " . date('d/m/Y H:i') . "</p>
        ";
    }
}
