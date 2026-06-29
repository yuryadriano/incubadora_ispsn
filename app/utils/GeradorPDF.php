<?php
namespace App\Utils;

use Dompdf\Dompdf;
use Dompdf\Options;

class GeradorPDF {
    public static function gerarTermoHTML($termo) {
        $dados = json_decode($termo['dados_json'], true);
        
        $proj = $dados['projeto'] ?? [];
        $aval = $dados['avaliacao'] ?? [];
        $mentor = $dados['mentor'] ?? 'A definir';
        $codigo = $termo['codigo_termo'];
        $estado = $termo['estado'];
        
        $hash = $termo['assinatura_hash'] ?? '';
        $assinado_em = $termo['assinado_em'] ?? '';
        
        // Obter tipo de contrato e duração
        $tipoContrato = $termo['tipo_contrato'] ?? $dados['tipo_contrato'] ?? 'incubacao';
        
        if ($tipoContrato === 'pre_incubacao') {
            $tituloDocumento = "CONTRATO DE ADESÃO AO PROGRAMA DE PRÉ-INCUBAÇÃO";
            $clausulaDuracao = "O presente contrato tem a duração de 3 (três) meses, contados a partir da data de assinatura, podendo ser prorrogado por igual período, mediante avaliação favorável do desempenho da equipa e validação do progresso pelo Orientador designado.";
            $clausulaApoio = "O Incubado terá direito a: (a) Apoio técnico e mentoria para validação da ideia de negócio; (b) Acesso aos recursos físicos e laboratórios da incubadora mediante agendamento; (c) Apoio na facilitação de contactos e preparação para o pitch de investimento.";
            $faseLabel = "Pré-Incubação (Validação)";
        } else {
            $tituloDocumento = "CONTRATO DE INCUBAÇÃO DE EMPRESA";
            $clausulaDuracao = "O ciclo de incubação terá a duração de 12 (doze) meses, contados a partir da data de assinatura, podendo ser prorrogado por até mais 6 (seis) meses, mediante avaliação favorável da INCUBADORA e cumprimento das metas de tração e mercado.";
            $clausulaApoio = "O Incubado terá direito a: (a) Espaço físico ou virtual de incubação e mentoria avançada; (b) Acesso aos recursos de rede, networking e eventos exclusivos da incubadora; (c) Apoio direto na facilitação de parcerias estratégicas e atração de investimento.";
            $faseLabel = "Incubação 🚀";
        }
        
        // Formatar notas
        $notasHTML = '';
        if (!empty($aval['notas'])) {
            $nomesNotas = [
                'inovacao' => '🔬 Inovação',
                'sustentabilidade' => '💰 Autossustentabilidade',
                'escalabilidade' => '📈 Escalabilidade',
                'impacto' => '🌍 Impacto Social',
                'viabilidade' => '⚙️ Viabilidade Técnica',
                'equipa' => '👥 Qualidade da Equipa',
                'mercado' => '📊 Viabilidade de Mercado',
                'proposta' => '📝 Qualidade da Proposta'
            ];
            foreach ($aval['notas'] as $k => $v) {
                $label = $nomesNotas[$k] ?? ucfirst($k);
                $notasHTML .= "<tr><td><strong>$label:</strong></td><td>$v / 10</td></tr>";
            }
        }

        $assinaturaStatus = '';
        if ($estado === 'assinado') {
            $assinaturaStatus = "
            <div class='signature-block'>
                <p style='color: green; font-weight: bold; font-size: 1.1rem; margin: 0 0 5px 0;'>✓ DOCUMENTO ASSINADO DIGITALMENTE</p>
                <p style='margin: 3px 0;'><strong>Assinado por:</strong> Reitor / Presidente (SuperAdmin)</p>
                <p style='margin: 3px 0;'><strong>Data/Hora:</strong> " . date('d/m/Y H:i:s', strtotime($assinado_em)) . "</p>
                <p style='font-family: monospace; font-size: 0.8rem; word-break: break-all; color: #555; margin: 10px 0 0 0; border-top: 1px solid #eee; padding-top: 8px;'><strong>Hash de Validação:</strong><br>$hash</p>
            </div>";
        } else {
            $assinaturaStatus = "
            <div class='signature-block' style='border-color: #f59e0b;'>
                <p style='color: #d97706; font-weight: bold; font-size: 1.1rem; margin: 0;'>⚠️ AGUARDANDO ASSINATURA DIGITAL</p>
                <p style='margin: 8px 0 0 0; color: #666;'>Este documento foi gerado pelo sistema e aguarda a confirmação por senha e assinatura digital do SuperAdmin.</p>
            </div>";
        }

        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>$tituloDocumento - $codigo</title>
            <style>
                body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #333; line-height: 1.5; padding: 10px; font-size: 13px; }
                .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #1e3a8a; padding-bottom: 10px; }
                .logo { font-size: 1.6rem; font-weight: bold; color: #1e3a8a; margin: 0; }
                .subtitle { font-size: 0.85rem; color: #666; margin: 5px 0 0 0; text-transform: uppercase; letter-spacing: 1px; }
                .title { text-align: center; font-size: 1.25rem; font-weight: bold; color: #111; margin: 15px 0 5px 0; }
                .codigo { text-align: center; font-weight: bold; font-size: 1rem; color: #1e3a8a; margin-bottom: 20px; }
                .section-title { font-size: 1rem; font-weight: bold; color: #1e3a8a; border-bottom: 1px solid #ddd; padding-bottom: 3px; margin-top: 20px; margin-bottom: 8px; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
                table td { padding: 4px 2px; vertical-align: top; }
                .w-30 { width: 35%; }
                .bold { font-weight: bold; }
                .clauses { text-align: justify; font-size: 0.85rem; }
                .clause-title { font-weight: bold; margin-top: 8px; color: #111; }
                .signature-block { border: 2px dashed #ddd; padding: 12px; text-align: center; margin-top: 30px; background-color: #fafafa; border-radius: 6px; }
                .footer { text-align: center; margin-top: 40px; font-size: 0.75rem; color: #888; border-top: 1px solid #eee; padding-top: 8px; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1 class='logo'>ISPSN</h1>
                <p class='subtitle'>Incubadora Académica do Instituto Superior Politécnico Sol Nascente</p>
            </div>
            
            <h2 class='title'>$tituloDocumento</h2>
            <div class='codigo'>Ref: $codigo</div>
            
            <div class='section-title'>1. IDENTIFICAÇÃO DO PROJECTO</div>
            <table>
                <tr>
                    <td class='w-30 bold'>Título da Startup/Projecto:</td>
                    <td>\"" . htmlspecialchars($proj['titulo']) . "\"</td>
                </tr>
                <tr>
                    <td class='bold'>Área Temática:</td>
                    <td>" . htmlspecialchars(ucfirst($proj['area'] ?? '')) . "</td>
                </tr>
                <tr>
                    <td class='bold'>Líder / Responsável:</td>
                    <td>" . htmlspecialchars($proj['autor']) . " (" . htmlspecialchars($proj['email']) . ")</td>
                </tr>
                <tr>
                    <td class='bold'>Mentor Designado:</td>
                    <td>" . htmlspecialchars($mentor) . "</td>
                </tr>
                <tr>
                    <td class='bold'>Programa / Fase Inicial:</td>
                    <td>$faseLabel</td>
                </tr>
            </table>

            <div class='section-title'>2. RESULTADO DA AVALIAÇÃO TÉCNICA</div>
            <table>
                <tr>
                    <td class='w-30 bold'>Pontuação Geral Obtida:</td>
                    <td class='bold' style='color: #1e3a8a;'>" . $aval['pontuacao'] . " / 10</td>
                </tr>
                $notasHTML
            </table>

            <div class='section-title'>3. CLAUSULADO DE COMPROMISSO E CONDIÇÕES</div>
            <div class='clauses'>
                <div class='clause-title'>Cláusula Primeira: Objecto</div>
                <p style='margin: 3px 0;'>O presente contrato estabelece as condições de apoio, acompanhamento e responsabilidades mútuas entre a INCUBADORA e os proponentes do projecto acima identificado, durante a sua permanência no programa.</p>
                
                <div class='clause-title'>Cláusula Segunda: Duração</div>
                <p style='margin: 3px 0;'>$clausulaDuracao</p>

                <div class='clause-title'>Cláusula Terceira: Apoio e Recursos Disponibilizados</div>
                <p style='margin: 3px 0;'>$clausulaApoio</p>
                
                <div class='clause-title'>Cláusula Quarta: Obrigações do Incubado</div>
                <p style='margin: 3px 0;'>O Incubado compromete-se a: (a) Cumprir activamente o pipeline de metas da sua fase operacional; (b) Submeter evidências claras e reais do progresso das metas; (c) Participar de forma assídua nas sessões de mentoria; (d) Fazer bom uso dos recursos físicos cedidos pela incubadora.</p>
                
                <div class='clause-title'>Cláusula Quinta: Confidencialidade</div>
                <p style='margin: 3px 0;'>Ambas as partes obrigam-se a guardar sigilo profissional sobre todas as informações, dados e metodologias técnicas ou comerciais de que tenham conhecimento no âmbito deste contrato.</p>
            </div>
            
            $assinaturaStatus

            <div class='footer'>
                <p style='margin: 2px 0;'>Instituto Superior Politécnico Sol Nascente — Huambo, Angola</p>
                <p style='margin: 2px 0;'>Documento gerado automaticamente pelo Sistema de Gestão de Incubadora ISPSN em " . date('d/m/Y H:i') . "</p>
            </div>
        </body>
        </html>";
        return $html;
    }

    public static function streamTermo($termo) {
        $html = self::gerarTermoHTML($termo);
        
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        $dompdf->stream("Termo_Incubacao_" . $termo['codigo_termo'] . ".pdf", ["Attachment" => false]);
    }

    public static function salvarTermoPDF($termo, $filePath) {
        $html = self::gerarTermoHTML($termo);
        
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        $dir = dirname($filePath);
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
        
        return file_put_contents($filePath, $dompdf->output()) !== false;
    }
}
