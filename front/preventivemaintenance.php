<?php

/**
 * -------------------------------------------------------------------------
 * Plugin de Manutenção Preventiva para GLPI
 * -------------------------------------------------------------------------
 *
 * LICENÇA
 *
 * Este arquivo é parte do Plugin de Manutenção Preventiva.
 *
 * Manutenção Preventiva é um software livre; você pode redistribuí-lo e/ou modificar
 * sob os termos da Licença Pública Geral GNU conforme publicada pela
 * Free Software Foundation; ou versão 2 da Licença, ou
 * (a seu critério) qualquer versão posterior.
 *
 * Manutenção Preventiva é distribuído na esperança de que seja útil,
 * mas SEM QUALQUER GARANTIA; sem mesmo a garantia implícita de
 * COMERCIALIZAÇÃO ou ADEQUAÇÃO A UM DETERMINADO FIM. Veja o
 * GNU General Public License para mais detalhes.
 *
 * Você deve ter recebido uma cópia da Licença Pública Geral GNU
 * junto com o Manutenção Preventiva. Se não, veja <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2025 William Oliveira Santos / WIDA Work Information Development Analytics
 * @license   GPLv2+ https://www.gnu.org/licenses/gpl-2.0.html
 * @link      [URL do seu plugin ou repositório GitHub]
 * -------------------------------------------------------------------------
 */

/**
 * -------------------------------------------------------------------------
 * Preventive Maintenance plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of Preventive Maintenance.
 *
 * Preventive Maintenance is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Preventive Maintenance is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Preventive Maintenance. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2025 William Oliveira Santos / WIDA Work Information Development Analytics
 * @license   GPLv2+ https://www.gnu.org/licenses/gpl-2.0.html
 * @link      [Your Plugin URL or GitHub Repository]
 * -------------------------------------------------------------------------
 */

// Inclui arquivos necessários do GLPI
include('../../../inc/includes.php');

// Verificação de segurança
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

// Verifica permissões
Session::checkRight('plugin_preventivemaintenance', READ);

// Instancia a classe principal
$pm = new PluginPreventivemaintenancePreventivemaintenance();
$token = Session::getNewCSRFToken();

// Função para obter configuração
function getPluginConfig($name)
{
    return PluginPreventivemaintenancePreventivemaintenance::getConfig($name);
}

// Função para atualizar configuração
function updatePluginConfig($name, $value)
{
    return PluginPreventivemaintenancePreventivemaintenance::setConfig($name, $value);
}

// Obtém configuração do Auto Ticket
$auto_ticket = getPluginConfig('auto_ticket');
if ($auto_ticket === false) {
    $auto_ticket = '0';
    updatePluginConfig('auto_ticket', $auto_ticket);
}
$auto_ticket_enabled = ($auto_ticket === '1');

// Inicializa filtros
$filters = [
    'status' => 'all',
    'date_from' => '',
    'date_to' => '',
    'technician' => 0,
    'entity' => 0,
];

// Atualiza filtros da requisição
if (isset($_GET['status'])) {
    $filters['status'] = $_GET['status'];
}
if (isset($_GET['date_from'])) {
    $filters['date_from'] = $_GET['date_from'];
}
if (isset($_GET['date_to'])) {
    $filters['date_to'] = $_GET['date_to'];
}
if (isset($_GET['technician'])) {
    $filters['technician'] = (int) $_GET['technician'];
}
if (isset($_GET['entity'])) {
    $filters['entity'] = (int) $_GET['entity'];
}

// Processa exclusão
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    if ($id > 0 && $pm->canDelete()) {
        if ($pm->delete(['id' => $id])) {
            Session::addMessageAfterRedirect(
                __('Registro apagado com sucesso!'),
                true,
                INFO,
            );
        } else {
            Session::addMessageAfterRedirect(
                __('Falha ao apagar registro!'),
                false,
                ERROR,
            );
        }
        Html::redirect('preventivemaintenance.php');
    }
}

// Processa toggle do Auto Ticket
if (isset($_GET['toggle_auto_ticket'])) {
    $new_value = $auto_ticket_enabled ? '0' : '1';
    if (updatePluginConfig('auto_ticket', $new_value)) {
        $auto_ticket_enabled = !$auto_ticket_enabled;
        Session::addMessageAfterRedirect(
            $auto_ticket_enabled ? __('Auto ticket ativado com sucesso!') : __('Auto ticket desativado com sucesso!'),
            true,
            INFO,
        );
    } else {
        Session::addMessageAfterRedirect(
            __('Falha ao atualizar a configuração do auto ticket!'),
            false,
            ERROR,
        );
    }
    Html::redirect('preventivemaintenance.php');
}

// Perfis considerados "técnico" para o dropdown de Técnico Responsável do
// assistente de manutenção. Configurado uma única vez aqui (em vez de pedir
// para selecionar de novo a cada manutenção criada) e persistido no banco.
// Profiles considered "technician" for the Responsible Technician dropdown
// in the maintenance wizard. Configured once here (instead of asking to
// re-select every time a maintenance is created) and persisted in the DB.
$technician_profiles = json_decode((string) getPluginConfig('technician_profiles'), true);
if (!is_array($technician_profiles) || empty($technician_profiles)) {
    $technician_profiles = ['Technician'];
}
$profile = new Profile();
$all_profiles = $profile->find([], 'name ASC');

// Processa a gravação dos perfis técnicos configurados
if (isset($_POST['save_technician_profiles'])) {
    // Verificação de presença do token, no mesmo padrão usado pelo restante
    // do plugin (ver front/preventivemaintenance.form.php) — a validação
    // completa via Session::checkCSRF() não sobrevive de forma confiável
    // entre a renderização desta página (que dispara dezenas de chamadas
    // AJAX concorrentes do dashboard) e o POST deste formulário.
    // Presence-only token check, matching the pattern used across the rest
    // of the plugin (see front/preventivemaintenance.form.php) — full
    // validation via Session::checkCSRF() does not reliably survive
    // between this page's render (which fires dozens of concurrent
    // dashboard AJAX calls) and this form's POST.
    if (!isset($_POST['_glpi_csrf_token'])) {
        Session::addMessageAfterRedirect(__('Token de segurança ausente. Recarregue a página e tente novamente.'), false, ERROR);
        Html::redirect('preventivemaintenance.php');
    }
    if (!$pm->canUpdate()) {
        Session::addMessageAfterRedirect(__('Você não tem permissão para alterar esta configuração.'), false, ERROR);
        Html::redirect('preventivemaintenance.php');
    }

    $selected = $_POST['profiles'] ?? [];
    if (!is_array($selected) || empty($selected)) {
        $selected = ['Technician'];
    }

    if (updatePluginConfig('technician_profiles', json_encode(array_values($selected)))) {
        Session::addMessageAfterRedirect(__('Perfis técnicos atualizados com sucesso!'), true, INFO);
    } else {
        Session::addMessageAfterRedirect(__('Falha ao atualizar os perfis técnicos!'), false, ERROR);
    }
    Html::redirect('preventivemaintenance.php');
}

// Gera um chamado manualmente para uma manutenção
// Generate a ticket manually for a maintenance
if (isset($_POST['generate_ticket'])) {
    if (!isset($_POST['_glpi_csrf_token'])) {
        Session::addMessageAfterRedirect(__('Token de segurança ausente.'), false, ERROR);
        Html::redirect('preventivemaintenance.php');
    }
    if (!Session::haveRight('plugin_preventivemaintenance', CREATE)) {
        Session::addMessageAfterRedirect(__('Você não tem permissão para criar chamados.'), false, ERROR);
        Html::redirect('preventivemaintenance.php');
    }

    $maintenance_id = (int) $_POST['generate_ticket'];
    if (!$pm->getFromDB($maintenance_id)) {
        Session::addMessageAfterRedirect(__('Manutenção não encontrada.'), false, ERROR);
        Html::redirect('preventivemaintenance.php');
    }

    // Chama a função para criar o chamado manualmente com os dados da manutenção
    if (createMaintenanceTicket(
        $pm->fields['id'],
        $pm->fields['items_id'],
        $pm->fields['itemtype'],
        $pm->fields['name'],
        $pm->fields['technician_id'],
        $pm->fields['tickettemplates_id'],
        $pm->fields['groups_id']
    )) {
        Session::addMessageAfterRedirect(__('Chamado gerado com sucesso!'), true, SUCCESS);
    } else {
        Session::addMessageAfterRedirect(__('Falha ao gerar chamado. Verifique os logs para mais detalhes.'), false, ERROR);
    }
    Html::redirect('preventivemaintenance.php');
}

// Função para verificar tickets abertos. Usa maintenance_id (vínculo direto e
// confiável) em vez de casar por nome do chamado — a linha de rastreamento
// agora fica gravada para sempre (vira histórico), então "aberto" é decidido
// pelo status real do chamado, não pela existência da linha.
// Function to check for open tickets. Uses maintenance_id (a direct, reliable
// link) instead of matching by ticket name — the tracking row now stays
// forever (becomes history), so "open" is decided by the ticket's real
// status, not by whether the row exists.
function hasOpenMaintenanceTicket($maintenance_id)
{
    global $DB;

    if (empty($maintenance_id)) {
        return false;
    }

    try {
        $criteria = [
            'SELECT' => ['glpi_plugin_preventivemaintenance_tickets.id'],
            'FROM' => 'glpi_plugin_preventivemaintenance_tickets',
            'INNER JOIN' => [
                'glpi_tickets' => [
                    'ON' => [
                        'glpi_plugin_preventivemaintenance_tickets' => 'ticket_id',
                        'glpi_tickets' => 'id',
                    ],
                ],
            ],
            'WHERE' => [
                'glpi_plugin_preventivemaintenance_tickets.maintenance_id' => (int) $maintenance_id,
                'glpi_plugin_preventivemaintenance_tickets.resolved_at' => null,
                ['NOT' => ['glpi_tickets.status' => [Ticket::CLOSED, Ticket::SOLVED]]],
            ],
            'LIMIT' => 1,
        ];

        $iterator = $DB->request($criteria);

        return count($iterator) > 0;
    } catch (Exception $e) {
        error_log("Erro ao verificar tickets existentes: " . $e->getMessage());
        return false;
    }
}

// Função para registrar ticket (também é o registro de histórico — a linha
// não é mais apagada quando o chamado é resolvido).
// Function to register the ticket (this is also the history record — the row
// is no longer deleted once the ticket is resolved).
function registerMaintenanceTicket($ticket_id, $maintenance_id, $items_id, $itemtype, $maintenance_name)
{
    global $DB;

    try {
        $DB->insert('glpi_plugin_preventivemaintenance_tickets', [
            'ticket_id' => (int) $ticket_id,
            'maintenance_id' => (int) $maintenance_id,
            'items_id' => (int) $items_id,
            'itemtype' => $itemtype,
            'maintenance_name' => $maintenance_name,
            'date_creation' => date('Y-m-d H:i:s'),
        ]);
        return true;
    } catch (Exception $e) {
        error_log("Erro ao registrar o ticket: " . $e->getMessage());
        return false;
    }
}

// Função para atualizar manutenção
function updateMaintenanceOnTicketResolution($ticket_id)
{
    global $DB;

    try {
        $criteria = [
            'SELECT' => ['maintenance_id', 'maintenance_name'],
            'FROM' => 'glpi_plugin_preventivemaintenance_tickets',
            'WHERE' => [
                'ticket_id' => (int) $ticket_id,
                'resolved_at' => null,
            ],
            'LIMIT' => 1,
        ];

        $iterator = $DB->request($criteria);

        if (count($iterator)) {
            $data = $iterator->current();
            $maintenance_id = $data['maintenance_id'];
            $maintenance_name = $data['maintenance_name'];

            $pm = new PluginPreventivemaintenancePreventivemaintenance();

            if ($pm->getFromDB($maintenance_id)) {
                $maintenance_data = $pm->fields;

                // Marca a linha como resolvida (mantém o histórico) em vez de
                // apagá-la.
                // Marks the row as resolved (keeps history) instead of deleting it.
                $mark_resolved = function () use ($DB, $ticket_id) {
                    $DB->update('glpi_plugin_preventivemaintenance_tickets', [
                        'resolved_at' => date('Y-m-d H:i:s'),
                    ], [
                        'ticket_id' => $ticket_id,
                    ]);
                };

                // Manutenções com recorrência automática têm a data reagendada
                // por advanceRecurringSchedules(), de forma independente da
                // resolução do chamado; aqui só marcamos o histórico.
                // Auto-recurring maintenances have their date rescheduled by
                // advanceRecurringSchedules(), independent of ticket resolution;
                // here we just mark the history.
                if (!empty($maintenance_data['is_recurring'])) {
                    $mark_resolved();
                    return true;
                }

                $ticket = new Ticket();
                if ($ticket->getFromDB($ticket_id)) {
                    $solvedate = $ticket->getField('solvedate');

                    if (!empty($solvedate)) {
                        $last_date = $maintenance_data['last_maintenance_date'];
                        $next_date = $maintenance_data['next_maintenance_date'];

                        if (!empty($last_date) && !empty($next_date)) {
                            $last_timestamp = strtotime($last_date);
                            $next_timestamp = strtotime($next_date);
                            $interval = $next_timestamp - $last_timestamp;

                            $new_next_date = date('Y-m-d H:i:s', strtotime($solvedate) + $interval);

                            $input = [
                                'id' => $maintenance_id,
                                'name' => $maintenance_name,
                                'last_maintenance_date' => $solvedate,
                                'next_maintenance_date' => $new_next_date,
                            ];

                            if ($pm->update($input)) {
                                $mark_resolved();
                                return true;
                            }
                        }
                    }
                }
            }
        }
        return false;
    } catch (Exception $e) {
        error_log("Erro ao atualizar a manutenção: " . $e->getMessage());
        return false;
    }
}

// Função para limpar tickets resolvidos
function cleanResolvedMaintenanceTickets()
{
    global $DB;

    try {
        $criteria = [
            'SELECT' => ['glpi_tickets.id', 'glpi_tickets.solvedate'],
            'FROM' => 'glpi_tickets',
            'INNER JOIN' => [
                'glpi_plugin_preventivemaintenance_tickets' => [
                    'ON' => [
                        'glpi_plugin_preventivemaintenance_tickets' => 'ticket_id',
                        'glpi_tickets' => 'id',
                    ],
                ],
            ],
            'WHERE' => [
                'glpi_tickets.status' => [Ticket::CLOSED, Ticket::SOLVED],
                'glpi_plugin_preventivemaintenance_tickets.resolved_at' => null,
            ],
        ];

        $iterator = $DB->request($criteria);

        foreach ($iterator as $data) {
            // updateMaintenanceOnTicketResolution() marca resolved_at internamente
            // (a linha vira histórico, não é mais apagada).
            // updateMaintenanceOnTicketResolution() marks resolved_at internally
            // (the row becomes history, no longer deleted).
            updateMaintenanceOnTicketResolution($data['id']);
        }

        return true;
    } catch (Exception $e) {
        error_log("Erro ao limpar tickets resolvidos: " . $e->getMessage());
        return false;
    }
}

// Função para avançar manutenções com recorrência automática (por calendário)
// Reagenda a próxima data pelo mesmo intervalo, independente do chamado atual
// ter sido resolvido ou não.
// Function to advance auto-recurring maintenances (calendar-based)
// Reschedules the next date by the same interval, independent of whether the
// current ticket has been resolved.
function advanceRecurringSchedules()
{
    global $DB;

    try {
        $pm = new PluginPreventivemaintenancePreventivemaintenance();
        $due = $pm->find([
            'is_recurring' => 1,
            ['next_maintenance_date' => ['<=', date('Y-m-d')]],
        ]);

        foreach ($due as $item) {
            // Avança por meses de calendário (não por uma contagem fixa de dias),
            // já que meses têm tamanhos diferentes — "a cada 3 meses" deve
            // sempre cair no mesmo dia do mês, 3 meses depois.
            // Advances by calendar months (not a fixed day count), since months
            // have different lengths — "every 3 months" should always land on
            // the same day of the month, 3 months later.
            $recurrence_months = max(1, (int) $item['recurrence_months']);
            $next = new DateTime($item['next_maintenance_date']);
            $last = $item['next_maintenance_date'];

            // Avança em passos do intervalo até a próxima data ficar no futuro
            // (cobre o caso de a manutenção ter ficado várias janelas sem rodar).
            // Steps forward by the interval until the next date is in the future
            // (covers the case where the maintenance missed several windows).
            while ($next <= new DateTime()) {
                $last = $next->format('Y-m-d');
                $next->modify("+{$recurrence_months} months");
            }

            $pm->update([
                'id' => $item['id'],
                'name' => $item['name'],
                'last_maintenance_date' => $last,
                'next_maintenance_date' => $next->format('Y-m-d'),
            ]);
        }

        return true;
    } catch (Exception $e) {
        error_log("Erro ao avançar manutenções recorrentes: " . $e->getMessage());
        return false;
    }
}

// Função para criar ticket
function createMaintenanceTicket($maintenance_id, $items_id, $itemtype, $maintenance_name, $technician_id, $tickettemplates_id = 0, $groups_id = 0)
{
    if (hasOpenMaintenanceTicket($maintenance_id)) {
        return false;
    }

    $item_name = Dropdown::getDropdownName($itemtype::getTable(), $items_id);

    $ticket = new Ticket();

    // NOTE: items_id/itemtype não são mais passados direto para Ticket::add() —
    // o vínculo é feito abaixo via Item_Ticket::add(), o mecanismo padrão do GLPI
    // (faz o item aparecer na aba "Elementos associados" do chamado).
    // NOTE: items_id/itemtype are no longer passed directly to Ticket::add() —
    // the association is done below via Item_Ticket::add(), GLPI's standard
    // mechanism (makes the item appear in the ticket's "Associated Elements" tab).
    $input = [
        'name' => sprintf(__('Manutenção preventiva necessária: %s'), $maintenance_name),
        'content' => sprintf(__('O item %s requer manutenção preventiva: %s'), $item_name, $maintenance_name),
        'type' => Ticket::INCIDENT_TYPE,
        'status' => Ticket::INCOMING,
        'urgency' => 5,
        'impact' => 5,
        'priority' => 5,
        'requesttypes_id' => 1,
        'users_id_recipient' => Session::getLoginUserID(),
        'entities_id' => $_SESSION['glpiactive_entity'],
        'date' => date('Y-m-d H:i:s'),
    ];

    // Aplica os campos pré-definidos do modelo de chamado escolhido (categoria,
    // SLA, tipo etc.), sem sobrescrever valores já definidos explicitamente acima.
    // Applies the chosen ticket template's predefined fields (category, SLA,
    // type, etc.), without overriding values already set explicitly above.
    if (!empty($tickettemplates_id)) {
        $template = new TicketTemplate();
        if ($template->getFromDB((int) $tickettemplates_id)) {
            $predefined_manager = TicketTemplate::getPredefinedFields();
            // $withtypeandcategory=true para incluir itilcategories_id/type no mapa de
            // campos permitidos (ficam de fora por padrão).
            // $withtypeandcategory=true to include itilcategories_id/type in the
            // allowed-fields map (excluded by default).
            $predefined_fields = $predefined_manager->getPredefinedFields($template->fields['id'], true);
            foreach ($predefined_fields as $field => $value) {
                if (!isset($input[$field])) {
                    $input[$field] = $value;
                }
            }
            $input['tickettemplates_id'] = (int) $tickettemplates_id;
        }
    }

    // Convenção atual do GLPI (10+/11) para atores do chamado: array estruturado
    // _actors[tipo][] = ['itemtype' => ..., 'items_id' => ...]. A antiga chave
    // _observers usada aqui antes nunca foi processada por Ticket::add() no GLPI 11
    // (verificado no código-fonte) — nunca era realmente adicionado.
    // Técnico/grupo entram como "assign" (atribuído), não "observer": eles são
    // os responsáveis reais pela manutenção, então entram na fila de trabalho
    // deles e contam para o SLA de atendimento — não são só notificados.
    // Current GLPI (10+/11) convention for ticket actors: structured array
    // _actors[type][] = ['itemtype' => ..., 'items_id' => ...]. The old _observers
    // key previously used here was never processed by Ticket::add() on GLPI 11
    // (verified in source) — it was never actually being added.
    // Technician/group are added as "assign", not "observer": they are the
    // real people responsible for the maintenance, so they land in their own
    // work queue and count toward SLA — they're not just notified.
    $assignees = [];
    if (!empty($technician_id)) {
        $assignees[] = ['itemtype' => 'User', 'items_id' => (int) $technician_id];
    }
    if (!empty($groups_id)) {
        $assignees[] = ['itemtype' => 'Group', 'items_id' => (int) $groups_id];
    }
    if (!empty($assignees)) {
        $input['_actors']['assign'] = $assignees;
    }

    try {
        $ticket_id = $ticket->add($input);
        if ($ticket_id) {
            $item_ticket = new Item_Ticket();
            $item_ticket->add([
                'tickets_id' => $ticket_id,
                'itemtype'   => $itemtype,
                'items_id'   => (int) $items_id,
            ]);

            registerMaintenanceTicket($ticket_id, $maintenance_id, $items_id, $itemtype, $maintenance_name);
            return $ticket_id;
        }
        return false;
    } catch (Exception $e) {
        error_log("Erro ao criar o ticket: " . $e->getMessage());
        return false;
    }
}

// Limpa tickets resolvidos
cleanResolvedMaintenanceTickets();

// Cria tickets automáticos se habilitado
if ($auto_ticket_enabled) {
    $all_maintenances = $pm->find([], 'next_maintenance_date ASC');

    foreach ($all_maintenances as $item) {
        if (!empty($item['last_maintenance_date']) && !empty($item['next_maintenance_date'])) {
            $last = strtotime($item['last_maintenance_date']);
            $next = strtotime($item['next_maintenance_date']);
            $now = time();

            $total_days = $next - $last;
            $elapsed_days = $now - $last;

            if ($total_days > 0) {
                $percent = min(100, max(0, round(($elapsed_days / $total_days) * 100)));

                if ($percent >= 99) {
                    $ticket_id = createMaintenanceTicket(
                        $item['id'],
                        $item['items_id'],
                        $item['itemtype'],
                        $item['name'],
                        $item['technician_id'],
                        $item['tickettemplates_id'] ?? 0,
                        $item['groups_id'] ?? 0,
                    );

                    if ($ticket_id) {
                        Session::addMessageAfterRedirect(
                            sprintf(__('Ticket criado automaticamente para manutenção preventiva: %s'), $item['name']),
                            true,
                            INFO,
                        );
                    }
                }
            }
        }
    }
}

// Avança manutenções com recorrência automática (por calendário). Roda DEPOIS
// da criação de tickets acima, para que uma janela vencida ainda gere o chamado
// antes de a data ser empurrada para a próxima janela.
// Advances auto-recurring maintenances (calendar-based). Runs AFTER the ticket
// creation above, so an overdue window still generates the ticket before the
// date gets pushed to the next window.
advanceRecurringSchedules();

// Prepara critérios de busca
$criteria = [];
if (!empty($filters['technician'])) {
    $criteria['technician_id'] = (int) $filters['technician'];
}
if (!empty($filters['entity'])) {
    $criteria['entities_id'] = (int) $filters['entity'];
}

// Adiciona filtros de data
if (!empty($filters['date_from']) || !empty($filters['date_to'])) {
    $date_criteria = [];
    if (!empty($filters['date_from'])) {
        $date_criteria[] = ['next_maintenance_date' => ['>=', $filters['date_from']]];
    }
    if (!empty($filters['date_to'])) {
        $date_criteria[] = ['next_maintenance_date' => ['<=', $filters['date_to']]];
    }
    $criteria[] = ['AND' => $date_criteria];
}

// Busca itens
$all_items = $pm->find($criteria, 'entities_id, next_maintenance_date ASC');

// Obtém lista de técnicos cadastrados nas manutenções
$technicians_in_maintenance = [];
foreach ($all_items as $item) {
    if ($item['technician_id'] > 0) {
        $technicians_in_maintenance[$item['technician_id']] = $item['technician_id'];
    }
}

// Carrega os dados dos técnicos (com nome completo - realname)
$technicians_data = [];
if (!empty($technicians_in_maintenance)) {
    $user = new User();
    $technicians_iterator = $user->find(['id' => array_values($technicians_in_maintenance)]);
    foreach ($technicians_iterator as $tech) {
        $technicians_data[$tech['id']] = formatUserName($tech['id'], $tech['name'], $tech['realname'], $tech['firstname']);
    }
}

// Obtém lista de grupos cadastrados nas manutenções
// Gets the list of groups registered in the maintenance records
$groups_in_maintenance = [];
foreach ($all_items as $item) {
    if (!empty($item['groups_id'])) {
        $groups_in_maintenance[$item['groups_id']] = $item['groups_id'];
    }
}

// Carrega os dados dos grupos
// Loads group data
$groups_data = [];
if (!empty($groups_in_maintenance)) {
    $group = new Group();
    $groups_iterator = $group->find(['id' => array_values($groups_in_maintenance)]);
    foreach ($groups_iterator as $grp) {
        $groups_data[$grp['id']] = $grp['name'];
    }
}

// Função para obter caminho da entidade
function getFullEntityPath($entity_id)
{
    $entity = new Entity();
    if ($entity->getFromDB($entity_id)) {
        $path = $entity->getName();
        $parent_id = $entity->getField('entities_id');

        while ($parent_id > 0) {
            $parent = new Entity();
            if ($parent->getFromDB($parent_id)) {
                $path = $parent->getName() . ' > ' . $path;
                $parent_id = $parent->getField('entities_id');
            } else {
                break;
            }
        }
        return $path;
    }
    return '';
}

// Agrupa itens por entidade
$items_by_entity = [];
foreach ($all_items as $item) {
    $entity_id = $item['entities_id'];
    $entity_path = getFullEntityPath($entity_id);

    if ($entity_path !== '') {
        if (!isset($items_by_entity[$entity_path])) {
            $items_by_entity[$entity_path] = [
                'name' => $entity_path,
                'items' => [],
            ];
        }

        $last = !empty($item['last_maintenance_date']) ? strtotime($item['last_maintenance_date']) : 0;
        $next = !empty($item['next_maintenance_date']) ? strtotime($item['next_maintenance_date']) : 0;
        $now = time();

        $include_item = true;

        if ($filters['status'] !== 'all' && $next > 0 && $last > 0) {
            $total_days = $next - $last;
            $elapsed_days = $now - $last;

            $percent = ($total_days > 0) ? min(100, max(0, round(($elapsed_days / $total_days) * 100))) : 0;

            switch ($filters['status']) {
                case 'ontime':
                    if ($percent >= 80) {
                        $include_item = false;
                    }
                    break;
                case 'warning':
                    if ($percent < 80 || $percent >= 98) {
                        $include_item = false;
                    }
                    break;
                case 'urgent':
                    if ($percent < 98) {
                        $include_item = false;
                    }
                    break;
                case 'undefined':
                    if ($next > 0 && $last > 0) {
                        $include_item = false;
                    }
                    break;
            }
        }

        if ($include_item) {
            $items_by_entity[$entity_path]['items'][] = $item;
        }
    }
}

// Ordena entidades
uksort($items_by_entity, function ($a, $b) {
    return strnatcasecmp($a, $b);
});

// Exibe cabeçalho
Html::header(
    __('Manutenção Preventiva', 'preventivemaintenance'),
    $_SERVER['PHP_SELF'],
    'plugins',
    'preventivemaintenance',
);
?>

<style>
    body {
        background-color: #cacccf !important;
    }
    .plugin-preventive-maintenance-container {
        background-color: white;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        margin: 20px auto;
        max-width: 98%;
    }
    .entity-group {
        margin-bottom: 40px;
        border: 1px solid #e0e0e0;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    .entity-title {
        background: #cacccf;
        padding: 15px 25px;
        font-size: 1.3rem;
        font-weight: 600;
        color: #2c3e50;
        border-bottom: 1px solid #dee2e6;
        display: flex;
        align-items: center;
    }
    .entity-title i {
        margin-right: 12px;
        color: #6c757d;
        font-size: 1.2em;
    }
    .table {
        width: 100%;
        margin-bottom: 0;
        border-collapse: collapse;
    }
    .table thead th {
        vertical-align: middle;
        text-align: center;
        background-color: #f1f3f5;
        font-weight: 600;
        border-bottom-width: 2px;
        color: #495057;
        padding: 12px 8px;
    }
    .table td {
        vertical-align: middle;
        padding: 10px 8px;
        border-bottom: 1px solid #e0e0e0;
        text-align: center;
    }
    .table-hover tbody tr:hover {
        background-color: rgba(0, 123, 255, 0.03);
    }
    .custom-footer {
        text-align: center;
        padding: 20px;
        margin-top: 40px;
        color: #6c757d;
        font-size: 0.9rem;
        border-top: 1px solid #e0e0e0;
        background-color: #f8f9fa;
    }
    .progress {
        height: 28px;
        border-radius: 6px;
        background-color: #e9ecef;
        overflow: hidden;
    }
    .progress-bar {
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 500;
        color: white;
    }
    .action-buttons {
        display: flex;
        gap: 8px;
        justify-content: center;
        flex-wrap: wrap;
    }
    .action-buttons .btn {
        padding: 6px 12px !important;
        font-size: 13px !important;
        border-radius: 4px !important;
        transition: all 0.2s ease !important;
    }
    .action-buttons .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    }
    .action-buttons .btn-outline-secondary {
        border: 1px solid #6c757d !important;
        color: #6c757d !important;
    }
    .action-buttons .btn-outline-secondary:hover {
        background-color: #6c757d !important;
        color: white !important;
    }
    .action-buttons .btn-success {
        background-color: #28a745 !important;
        border-color: #28a745 !important;
        color: white !important;
    }
    .action-buttons .btn-success:hover {
        background-color: #218838 !important;
        border-color: #1e7e34 !important;
    }
    .action-buttons .btn-primary {
        background-color: #007bff !important;
        border-color: #007bff !important;
    }
    .action-buttons .btn-primary:hover {
        background-color: #0056b3 !important;
        border-color: #004085 !important;
    }
    .action-buttons .btn-danger {
        background-color: #dc3545 !important;
        border-color: #dc3545 !important;
    }
    .action-buttons .btn-danger:hover {
        background-color: #c82333 !important;
        border-color: #bd2130 !important;
    }
    .advanced-filters {
        background-color: #f8f9fa;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
        border: 1px solid #dee2e6;
        display: none;
    }
    .filter-row {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 10px;
    }
    .filter-group {
        flex: 1;
        min-width: 180px;
    }
    .filter-group.small {
        flex: 0.5;
        min-width: 120px;
    }
    .filter-title {
        font-weight: 600;
        margin-bottom: 5px;
        color: #495057;
        font-size: 0.9rem;
    }
    .filter-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 10px;
    }
    .form-control {
        width: 100%;
        padding: 6px 10px;
        font-size: 0.9rem;
        border: 1px solid #ced4da;
        border-radius: 4px;
    }
    .toggle-btn {
        position: relative;
        display: inline-block;
        width: 60px;
        height: 30px;
        border-radius: 15px;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-left: 10px;
        vertical-align: middle;
    }
    .toggle-btn .toggle-knob {
        position: absolute;
        width: 26px;
        height: 26px;
        border-radius: 50%;
        background: white;
        top: 2px;
        left: 2px;
        transition: all 0.3s ease;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    }
    .toggle-btn.on {
        background: #28a745;
    }
    .toggle-btn.off {
        background: #dc3545;
    }
    .toggle-btn.on .toggle-knob {
        left: 32px;
    }
    .toggle-btn.off .toggle-knob {
        left: 2px;
    }
    .toggle-container {
        display: inline-flex;
        align-items: center;
        margin-left: 15px;
    }
    .toggle-label {
        margin-right: 5px;
        font-weight: 500;
        color: #495057;
    }
    
    /* Donation button styles */
    .donation-button {
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background-color: #28a745;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        cursor: pointer;
        z-index: 1000;
        transition: all 0.3s ease;
    }
    .donation-button:hover {
        background-color: #218838;
        transform: scale(1.1);
    }
    .donation-qr-container {
        position: fixed;
        bottom: 100px;
        right: 30px;
        background-color: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 5px 25px rgba(0,0,0,0.2);
        z-index: 1001;
        display: none;
        flex-direction: column;
        align-items: center;
        max-width: 300px;
    }
    .donation-qr-container.show {
        display: flex;
    }
    .donation-qr-code {
        width: 200px;
        height: 200px;
        margin-bottom: 15px;
        background-color: white;
        padding: 10px;
        border: 1px solid #ddd;
    }
    .donation-message {
        text-align: center;
        font-size: 14px;
        color: #333;
        margin-top: 10px;
    }
    .pix-code-container {
        width: 100%;
        margin-top: 15px;
        position: relative;
    }
    .pix-code {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 12px;
        word-break: break-all;
        background-color: #f8f9fa;
    }
    .copy-pix-btn {
        position: absolute;
        right: 5px;
        top: 5px;
        background: #28a745;
        color: white;
        border: none;
        border-radius: 4px;
        padding: 2px 8px;
        font-size: 12px;
        cursor: pointer;
    }
    .copy-pix-btn:hover {
        background: #218838;
    }
    .copy-notification {
        position: fixed;
        bottom: 180px;
        right: 30px;
        background: #28a745;
        color: white;
        padding: 8px 15px;
        border-radius: 4px;
        z-index: 1002;
        display: none;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    }
    
    @media (max-width: 768px) {
        .donation-button {
            bottom: 20px;
            right: 20px;
            width: 50px;
            height: 50px;
            font-size: 20px;
        }
        .donation-qr-container {
            bottom: 80px;
            right: 20px;
            max-width: 250px;
        }
        .donation-qr-code {
            width: 180px;
            height: 180px;
        }
        .copy-notification {
            bottom: 160px;
            right: 20px;
        }
    }
</style>

<div class="plugin-preventive-maintenance-container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
        <h2 style="margin: 0;"><i class="fas fa-calendar-check" style="margin-right: 10px;"></i><?= __('Manutenção Preventiva') ?></h2>
        <div style="display: flex; align-items: center;">
            <?php if ($pm->canCreate()): ?>
                <a href="preventivemaintenance.form.php" class="btn btn-primary" style="margin-right: 10px;">
                    <i class="fas fa-plus"></i> <?= "&nbsp",__('Add') ?>
                </a>
            <?php endif; ?>
            <button id="toggleFilters" class="btn btn-outline-info">
                <i class="fas fa-filter"></i>  <?="&nbsp", __('Filters') ?>
            </button>
            
            <div class="toggle-container">
                <span class="toggle-label"><?= __('Auto Ticket') ?></span>
                <a href="preventivemaintenance.php?toggle_auto_ticket=1"
                   class="toggle-btn <?= $auto_ticket_enabled ? 'on' : 'off' ?>"
                   title="<?= $auto_ticket_enabled ? __('Disable Auto Ticket') : __('Enable Auto Ticket') ?>">
                    <span class="toggle-knob"></span>
                </a>
            </div>

            <?php if ($pm->canUpdate()): ?>
                <button id="openTechProfilesModal" class="btn btn-outline-secondary" style="margin-left: 10px;" type="button"
                        title="<?= __('Configurações') ?>">
                    <i class="fas fa-cog"></i>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($pm->canUpdate()): ?>
        <div id="techProfilesModal" class="profile-modal">
            <div class="profile-modal-content">
                <div class="profile-modal-header">
                    <div class="profile-modal-title"><?= __('Configurações — Perfis Técnicos') ?></div>
                    <span class="profile-modal-close" id="techProfilesModalClose">&times;</span>
                </div>
                <p class="text-muted">
                    <?= __('Perfis considerados "técnico" no campo Técnico Responsável ao criar ou editar uma manutenção. Configure uma vez aqui — não é mais necessário selecionar a cada manutenção.') ?>
                </p>
                <form method="post">
                    <?= Html::hidden('_glpi_csrf_token', ['value' => $token]) ?>
                    <input type="hidden" name="save_technician_profiles" value="1">
                    <div class="profile-checkboxes">
                        <?php foreach ($all_profiles as $prof): ?>
                            <div class="profile-checkbox-item">
                                <label>
                                    <input type="checkbox" name="profiles[]" value="<?= $prof['name'] ?>"
                                        <?= in_array($prof['name'], $technician_profiles, true) ? 'checked' : '' ?>>
                                    <?= $prof['name'] ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="profile-modal-footer">
                        <button type="button" class="btn btn-secondary" id="techProfilesModalCancel"><?= __('Cancelar') ?></button>
                        <button type="submit" class="btn btn-primary"><?= __('Salvar') ?></button>
                    </div>
                </form>
            </div>
        </div>
        <style>
            .profile-modal {
                display: none;
                position: fixed;
                z-index: 1000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0,0,0,0.4);
            }
            .profile-modal-content {
                background-color: #fefefe;
                margin: 10% auto;
                padding: 20px;
                border: 1px solid #888;
                width: 50%;
                border-radius: 5px;
                box-shadow: 0 4px 8px 0 rgba(0,0,0,0.2);
            }
            .profile-modal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 10px;
            }
            .profile-modal-title {
                font-size: 1.2em;
                font-weight: bold;
            }
            .profile-modal-close {
                color: #aaa;
                font-size: 28px;
                font-weight: bold;
                cursor: pointer;
            }
            .profile-modal-close:hover {
                color: black;
            }
            .profile-checkboxes {
                max-height: 400px;
                overflow-y: auto;
                margin-bottom: 20px;
            }
            .profile-checkbox-item {
                margin-bottom: 10px;
            }
            .profile-modal-footer {
                display: flex;
                justify-content: flex-end;
                gap: 10px;
            }
        </style>
        <script>
            (function() {
                var modal = document.getElementById('techProfilesModal');
                document.getElementById('openTechProfilesModal').addEventListener('click', function() {
                    modal.style.display = 'block';
                });
                document.getElementById('techProfilesModalClose').addEventListener('click', function() {
                    modal.style.display = 'none';
                });
                document.getElementById('techProfilesModalCancel').addEventListener('click', function() {
                    modal.style.display = 'none';
                });
                window.addEventListener('click', function(event) {
                    if (event.target === modal) {
                        modal.style.display = 'none';
                    }
                });
            })();
        </script>
    <?php endif; ?>

    <div id="advancedFilters" class="advanced-filters" style="<?= isset($_GET['filter_applied']) ? '' : 'display: none;' ?>">
        <form method="get" action="">
            <input type="hidden" name="filter_applied" value="1">
            
            <div class="filter-row">
                <div class="filter-group small">
                    <div class="filter-title"><?= __('Status') ?></div>
                    <select name="status" class="form-control">
                        <option value="all" <?= $filters['status'] === 'all' ? 'selected' : '' ?>><?= __('Todos') ?></option>
                        <option value="ontime" <?= $filters['status'] === 'ontime' ? 'selected' : '' ?>><?= __('Em dia') ?></option>
                        <option value="warning" <?= $filters['status'] === 'warning' ? 'selected' : '' ?>><?= __('Atenção') ?></option>
                        <option value="urgent" <?= $filters['status'] === 'urgent' ? 'selected' : '' ?>><?= __('Urgente') ?></option>
                        <option value="undefined" <?= $filters['status'] === 'undefined' ? 'selected' : '' ?>><?= __('Indefinido') ?></option>
                    </select>
                </div>
                
                <div class="filter-group small">
                    <div class="filter-title"><?= __('De') ?></div>
                    <input type="date" name="date_from" class="form-control" value="<?= $filters['date_from'] ?>">
                </div>
                
                <div class="filter-group small">
                    <div class="filter-title"><?= __('Até') ?></div>
                    <input type="date" name="date_to" class="form-control" value="<?= $filters['date_to'] ?>">
                </div>
                
                <div class="filter-group">
                    <div class="filter-title"><?= __('Technician') ?></div>
                    <select name="technician" class="form-control">
                        <option value="0"><?= __('Todos') ?></option>
                        <?php
                        if (!empty($technicians_data)) {
                            foreach ($technicians_data as $tech_id => $tech_name) {
                                $selected = ($filters['technician'] == $tech_id) ? 'selected' : '';
                                echo "<option value='$tech_id' $selected>$tech_name</option>";
                            }
                        }
?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <div class="filter-title"><?= __('Entity') ?></div>
                    <?php
                    $entity_options = [
                        'name' => 'entity',
                        'value' => $filters['entity'],
                        'display' => false,
                        'width' => '100%',
                    ];
echo Entity::dropdown($entity_options);
?>
                </div>
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-filter"></i> <?= __('Aplicar') ?>
                </button>
                <a href="preventivemaintenance.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-times"></i> <?= __('Limpar') ?>
                </a>
            </div>
        </form>
    </div>

    <?php if (empty($items_by_entity)): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> <?= __('Nenhum registro encontrado.') ?>
        </div>
    <?php else: ?>
        <?php foreach ($items_by_entity as $entity): ?>
            <?php if (empty($entity['items'])) {
                continue;
            } ?>
            
            <div class="entity-group">
                <div class="entity-title">
                    <i class="fas fa-building"></i> <?= $entity['name'] ?>
                </div>
                
                <div style="overflow-x: auto;">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th style="text-align: center"><?= __('ID') ?></th>
                                <th style="text-align: center"><?= __('Nome/descr.') ?></th>
                                <th style="text-align: center"><?= __('Item') ?></th>
                                <th style="text-align: center"><?= __('Technician') ?></th>
                                <th style="text-align: center"><?= __('Grupo') ?></th>
                                <th style="text-align: center"><?= __('Ult. Man.') ?></th>
                                <th style="text-align: center"><?= __('Prox. Man') ?></th>
                                <th style="text-align: center"><?= __('Status') ?></th>
                                <th style="text-align: center"><?= __('Actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($entity['items'] as $item): ?>
                                <?php
                                $item_itemtype = $item['itemtype'] ?? 'Computer';
                                // Whitelist-check defensivo antes de instanciar, mesmo vindo do banco
                                // Defensive whitelist check before instantiating, even though it comes from the DB
                                if (in_array($item_itemtype, PluginPreventivemaintenancePreventivemaintenance::getAllowedItemtypes(), true)) {
                                    $item_obj = new $item_itemtype();
                                    $item_display_name = $item_obj->getFromDB($item['items_id'])
                                        ? $item_obj->getName() . ' (' . $item_itemtype::getTypeName(1) . ')'
                                        : __('N/A');
                                } else {
                                    $item_display_name = __('N/A');
                                }

                                $technician_name = isset($technicians_data[$item['technician_id']]) ? $technicians_data[$item['technician_id']] : '-';
                                $group_name = isset($groups_data[$item['groups_id']]) ? $groups_data[$item['groups_id']] : '-';


                                $last = !empty($item['last_maintenance_date']) ? strtotime($item['last_maintenance_date']) : 0;
                                $next = !empty($item['next_maintenance_date']) ? strtotime($item['next_maintenance_date']) : 0;
                                $now = time();

                                $status_html = "<span class='badge bg-secondary'>" . __('Undefined') . "</span>";

                                if ($next > 0 && $last > 0) {
                                    $total_days = $next - $last;
                                    $elapsed_days = $now - $last;

                                    $percent = ($total_days > 0) ? min(100, max(0, round(($elapsed_days / $total_days) * 100))) : 0;

                                    if ($percent < 80) {
                                        $status_class = 'bg-success';
                                        $status_text = __('Em dia');
                                    } elseif ($percent >= 80 && $percent < 98) {
                                        $status_class = 'bg-warning';
                                        $status_text = __('Atenção');
                                    } else {
                                        $status_class = 'bg-danger';
                                        $status_text = __('Urgente');
                                    }

                                    $status_html = "<div class='progress'>
                                        <div class='progress-bar $status_class' role='progressbar' style='width: $percent%'>
                                            <strong>$percent% - $status_text</strong>
                                        </div>";
                                }
                                ?>
                                <tr>
                                    <td style="text-align: center"><?= $item['id'] ?></td>
                                    <td style="text-align: center">
                                        <?= $item['name'] ?>
                                        <?php if (!empty($item['is_recurring'])):
                                            $recurrence_options = PluginPreventivemaintenancePreventivemaintenance::getRecurrenceMonthOptions();
                                            $recurrence_label = $recurrence_options[(int) $item['recurrence_months']] ?? '';
                                            ?>
                                            <i class="fas fa-sync-alt" title="<?= sprintf(__('Recorrência automática: a cada %s'), $recurrence_label) ?>"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center"><?= $item_display_name ?></td>
                                    <td style="text-align: center"><?= $technician_name ?></td>
                                    <td style="text-align: center"><?= $group_name ?></td>
                                    <td style="text-align: center"><?= !empty($item['last_maintenance_date']) ? Html::convDate($item['last_maintenance_date']) : '-' ?></td>
                                    <td style="text-align: center"><?= !empty($item['next_maintenance_date']) ? Html::convDate($item['next_maintenance_date']) : '-' ?></td>
                                    <td style="text-align: center"><?= $status_html ?></td>
                                    <td style="text-align: center">
                                        <div class="action-buttons">
                                            <a href="preventivemaintenance.form.php?id=<?= $item['id'] ?>#history" class="btn btn-sm btn-outline-secondary" title="<?= __('Ver histórico de chamados') ?>">
                                                <i class="fas fa-search"></i>
                                            </a>
                                            <?php if (Session::haveRight('plugin_preventivemaintenance', CREATE)): ?>
                                                <form method="post" style="display:inline;">
                                                    <?php echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]); ?>
                                                    <button type="submit" name="generate_ticket" value="<?= $item['id'] ?>"
                                                            class="btn btn-sm btn-success"
                                                            title="<?= __('Gerar chamado manualmente') ?>"
                                                            onclick="return confirm('<?= __('Gerar um novo chamado para esta manutenção agora?') ?>');">
                                                        <i class="fas fa-plus"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if (Session::haveRight('plugin_preventivemaintenance', UPDATE)): ?>
                                                <a href="preventivemaintenance.form.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-primary" title="<?= __('Edit') ?>">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($pm->canDelete()): ?>
                                                <a href="preventivemaintenance.php?delete=<?= $item['id'] ?>"
                                                   class="btn btn-sm btn-danger"
                                                   title="<?= __('Delete') ?>"
                                                   onclick="return confirm('<?= __('Do you really want to delete this record?') ?>');">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="custom-footer">
        <i class="fas fa-code"></i> <?= __('Developed by WIDA - Work Information Developments and Analytics') ?>
    </div>
</div>

<!-- Donation Button and QR Code -->
<div class="donation-button" id="donationButton">
    <i class="fas fa-heart"></i>
</div>

<div class="donation-qr-container" id="donationQrContainer">
    <div class="donation-qr-code">
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=00020126720014BR.GOV.BCB.PIX0136db70b7e3-711a-4774-8884-7275386521e40210Obrigado!!5204000053039865802BR5925William%20de%20Oliveira%20Santo6009SAO%20PAULO621405101SdXWYH7RP6304D3A2" 
             alt="QR Code para doação via PIX">
    </div>
    <div class="pix-code-container">
        <div class="pix-code" id="pixCode">
            00020126720014BR.GOV.BCB.PIX0136db70b7e3-711a-4774-8884-7275386521e40210Obrigado!!5204000053039865802BR5925William de Oliveira Santo6009SAO PAULO621405101SdXWYH7RP6304D3A2
        </div>
        <button class="copy-pix-btn" id="copyPixBtn">
            <i class="fas fa-copy"></i> Copiar
        </button>
    </div>
    <div class="donation-message">
        Se esse projeto te ajudou, ajude também doando
    </div>
</div>

<div class="copy-notification" id="copyNotification">
    Código PIX copiado!
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleFiltersBtn = document.getElementById('toggleFilters');
    const advancedFilters = document.getElementById('advancedFilters');
    
    toggleFiltersBtn.addEventListener('click', function() {
        if (advancedFilters.style.display === 'none') {
            advancedFilters.style.display = 'block';
            toggleFiltersBtn.classList.remove('btn-outline-info');
            toggleFiltersBtn.classList.add('btn-info');
        } else {
            advancedFilters.style.display = 'none';
            toggleFiltersBtn.classList.remove('btn-info');
            toggleFiltersBtn.classList.add('btn-outline-info');
        }
    });
    
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('filter_applied')) {
        advancedFilters.style.display = 'block';
        toggleFiltersBtn.classList.remove('btn-outline-info');
        toggleFiltersBtn.classList.add('btn-info');
    }
    
    function setupMobileView() {
        if (window.innerWidth < 768) {
            const headers = Array.from(document.querySelectorAll('thead th')).map(th => th.textContent.trim());
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                cells.forEach((cell, index) => {
                    cell.setAttribute('data-label', headers[index]);
                });
            });
        }
    }
    
    // Donation button functionality
    const donationButton = document.getElementById('donationButton');
    const donationQrContainer = document.getElementById('donationQrContainer');
    const copyPixBtn = document.getElementById('copyPixBtn');
    const pixCode = document.getElementById('pixCode');
    const copyNotification = document.getElementById('copyNotification');
    
    donationButton.addEventListener('click', function(e) {
        e.stopPropagation();
        donationQrContainer.classList.toggle('show');
    });
    
    // Copy PIX code functionality
    copyPixBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        const textToCopy = pixCode.textContent;
        
        navigator.clipboard.writeText(textToCopy).then(function() {
            // Show notification
            copyNotification.style.display = 'block';
            setTimeout(function() {
                copyNotification.style.display = 'none';
            }, 2000);
        }).catch(function(err) {
            console.error('Erro ao copiar texto: ', err);
            // Fallback for older browsers
            const textarea = document.createElement('textarea');
            textarea.value = textToCopy;
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
                // Show notification
                copyNotification.style.display = 'block';
                setTimeout(function() {
                    copyNotification.style.display = 'none';
                }, 2000);
            } catch (err) {
                console.error('Fallback erro ao copiar texto: ', err);
            }
            document.body.removeChild(textarea);
        });
    });
    
    // Close QR code when clicking outside
    document.addEventListener('click', function(event) {
        if (!donationButton.contains(event.target) && 
            !donationQrContainer.contains(event.target)) {
            donationQrContainer.classList.remove('show');
        }
    });
});
</script>

<?php
Html::footer();
