<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * local_contentfreshness.php
 *
 * @package   local_contentfreshness
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;


$string['action'] = 'Ação';
$string['age'] = 'Idade do conteúdo';
$string['aifailed'] = 'A revisão por IA não pôde ser concluída: {$a}';
$string['allage'] = 'Qualquer idade';
$string['allsections'] = 'Todas as seções';
$string['allseverities'] = 'Todas as severidades';
$string['alltypes'] = 'Todos os tipos';
$string['assigntype'] = 'Instruções da tarefa';
$string['auditcomplete'] = 'Auditoria concluída. Textos inalterados reutilizaram a análise de IA em cache.';
$string['bookchaptertype'] = 'Capítulo de livro';
$string['booktype'] = 'Livro';
$string['cached'] = 'Cache';
$string['candidatecap'] = 'A auditoria encontrou mais candidatos semânticos que o limite configurado. {$a} candidato(s) ficaram apenas para revisão humana, sem chamada de IA.';
$string['classification'] = 'Classificação';
$string['contentfreshness:audit'] = 'Auditar atualidade do conteúdo do curso';
$string['edit'] = 'Editar';
$string['evergreen'] = 'Conteúdo perene';
$string['explicit_date'] = 'Data explícita';
$string['external_link'] = 'Link externo';
$string['filter'] = 'Aplicar filtros';
$string['forumtype'] = 'Descrição do fórum';
$string['high'] = 'Alta';
$string['intro'] = 'Encontra textos do curso que merecem revisão humana porque contêm referências temporais, versões de software, datas ou links externos. O relatório não afirma automaticamente que o conteúdo é falso ou está desatualizado.';
$string['invalidairesponse'] = 'O bridge de IA retornou uma resposta que não pôde ser interpretada com segurança.';
$string['item'] = 'Item';
$string['labeltype'] = 'Texto e mídia';
$string['likely_time_sensitive'] = 'Provavelmente sensível ao tempo — requer revisão';
$string['link_http_error'] = 'Erro HTTP no link externo';
$string['link_not_checked'] = 'Link externo não verificado';
$string['link_redirect'] = 'Link externo redireciona';
$string['link_server_error'] = 'Erro do servidor no link externo';
$string['link_unreachable'] = 'Não foi possível verificar o link externo';
$string['linkcap'] = 'A auditoria encontrou mais links externos que o limite configurado. Os links restantes foram listados sem realizar requisição.';
$string['linktimeout'] = 'Timeout da verificação de links';
$string['linktimeout_desc'] = 'Timeout, em segundos, para cada requisição HEAD segura. Valores abaixo de 1 são tratados como 1 segundo.';
$string['low'] = 'Baixa';
$string['maxaicandidates'] = 'Máximo de candidatos enviados à IA';
$string['maxaicandidates_desc'] = 'Número máximo de trechos detectados pelas heurísticas locais enviados ao local_ai_bridge em uma auditoria manual.';
$string['maxurlchecks'] = 'Máximo de verificações de links por auditoria';
$string['maxurlchecks_desc'] = 'Número máximo de links HTTP/HTTPS externos verificados em uma auditoria manual. As verificações usam as proteções de segurança do cURL do Moodle e nunca ignoram o security helper.';
$string['medium'] = 'Média';
$string['modified'] = 'Última modificação no Moodle';
$string['navtitle'] = 'Atualidade do conteúdo';
$string['needs_human_review'] = 'Requer revisão humana';
$string['noresults'] = 'Nenhum sinal de atualidade corresponde aos filtros atuais.';
$string['old_year'] = 'Referência a ano antigo';
$string['olderthan1'] = 'Mais antigo que 1 ano';
$string['olderthan2'] = 'Mais antigo que 2 anos';
$string['olderthan3'] = 'Mais antigo que 3 anos';
$string['olderthan5'] = 'Mais antigo que 5 anos';
$string['pagetype'] = 'Página';
$string['pendingai'] = 'Revisão semântica pendente';
$string['pluginname'] = 'Atualidade do conteúdo';
$string['possibly_outdated'] = 'Possivelmente desatualizado — requer revisão';
$string['privacy:metadata'] = 'O plugin Atualidade do conteúdo não armazena dados pessoais. Ele mantém cache da análise de conteúdo criado pelo professor e não inspeciona submissões de alunos.';
$string['quiztype'] = 'Descrição do questionário';
$string['reason'] = 'Razão';
$string['reason_explicit_date'] = 'O texto contém uma data explícita que pode exigir revisão de contexto.';
$string['reason_external_link'] = 'Links externos podem mudar independentemente do conteúdo do Moodle e devem ser revisados periodicamente.';
$string['reason_link_http_error'] = 'A requisição HEAD segura retornou HTTP {$a}. Revise o destino antes de alterar o conteúdo; alguns sites restringem requisições HEAD.';
$string['reason_link_not_checked'] = 'A URL foi detectada, mas ainda não foi requisitada. Execute a auditoria para verificá-la com segurança.';
$string['reason_link_not_checked_limit'] = 'A URL foi detectada, mas não foi requisitada porque o limite configurado para a auditoria foi atingido.';
$string['reason_link_redirect'] = 'O link externo redireciona. Verifique se o destino final ainda é a referência desejada.';
$string['reason_link_server_error'] = 'A requisição HEAD segura retornou HTTP {$a}. O problema pode ser temporário, portanto é necessária revisão humana.';
$string['reason_link_unreachable'] = 'Não foi possível verificar a URL com segurança. Nenhuma conclusão sobre o conteúdo de destino foi feita.';
$string['reason_old_year'] = 'O texto contém referência a um ano antigo. O ano, isoladamente, não prova que a informação esteja desatualizada.';
$string['reason_software_version'] = 'O texto menciona uma versão de software ou plataforma que pode mudar ao longo do tempo.';
$string['reason_temporal_expression'] = 'A redação depende do momento em que o material é lido e, por isso, merece revisão.';
$string['requiresreview'] = 'Requer revisão';
$string['resetfilters'] = 'Limpar';
$string['risktype'] = 'Tipo de risco';
$string['runaudit'] = 'Executar auditoria de atualidade';
$string['section'] = 'Seção';
$string['sectiontype'] = 'Resumo da seção';
$string['severity'] = 'Severidade';
$string['snippet'] = 'Trecho';
$string['software_version'] = 'Versão de software';
$string['summary'] = '{$a->items} item(ns) do curso inspecionado(s); {$a->candidates} sinal(is) de atualidade exibido(s).';
$string['temporal_expression'] = 'Expressão temporal';
$string['type'] = 'Tipo';
$string['unknownmodified'] = 'Desconhecida';
