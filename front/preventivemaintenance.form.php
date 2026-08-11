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
 
 
 

// Inclui arquivos necessários do GLPI e verifica permissões
// Includes required GLPI files and checks permissions
include('../../../inc/includes.php');
Session::checkRight('plugin_preventivemaintenance', CREATE);

$is_edit = isset($_GET['id']);
$pm = new PluginPreventivemaintenancePreventivemaintenance();
$item_data = [];
$selected_entity_id = 0;
$ticket_history = [];

// Se estiver editando, carrega os dados existentes
// If editing, loads existing data
if ($is_edit) {
    $id = (int)$_GET['id'];
    if (!$pm->getFromDB($id)) {
        Session::addMessageAfterRedirect(__('Registro não encontrado'), false, ERROR);
        Html::redirect('preventivemaintenance.php');
    }
    $item_data = $pm->fields;
    $selected_entity_id = $item_data['entities_id'];

    // Histórico de chamados já criados para esta manutenção (abertos e resolvidos)
    // History of tickets already created for this maintenance (open and resolved)
    global $DB;
    $history_iterator = $DB->request([
        'SELECT' => [
            'glpi_tickets.id',
            'glpi_tickets.name',
            'glpi_tickets.status',
            'glpi_plugin_preventivemaintenance_tickets.date_creation',
            'glpi_plugin_preventivemaintenance_tickets.resolved_at',
        ],
        'FROM' => 'glpi_plugin_preventivemaintenance_tickets',
        'INNER JOIN' => [
            'glpi_tickets' => [
                'ON' => [
                    'glpi_plugin_preventivemaintenance_tickets' => 'ticket_id',
                    'glpi_tickets' => 'id'
                ]
            ]
        ],
        'WHERE' => ['glpi_plugin_preventivemaintenance_tickets.maintenance_id' => $id],
        'ORDER' => 'glpi_plugin_preventivemaintenance_tickets.date_creation DESC',
    ]);
    foreach ($history_iterator as $row) {
        $ticket_history[] = $row;
    }
}

// Busca todos os perfis disponíveis para seleção
// Finds all available profiles for selection
$profile = new Profile();
$all_profiles = $profile->find([], 'name ASC');

// Busca os perfis técnicos selecionados (armazenados na sessão ou usa o padrão 'Technician')
// Finds selected technician profiles (stored in session or uses default 'Technician')
$selected_profiles = $_SESSION['plugin_preventivemaintenance_selected_profiles'] ?? ['Technician'];

// Busca os técnicos responsáveis (usuários com perfil de técnico selecionado)
// Finds responsible technicians (users with selected technician profile)
$technicians = [];
$user = new User();
$profile_user = new Profile_User();

foreach ($selected_profiles as $profile_name) {
    $technician_profile = $profile->find(['name' => $profile_name]);
    if (!empty($technician_profile)) {
        $technician_profile_id = key($technician_profile);
        $profile_users = $profile_user->find(['profiles_id' => $technician_profile_id]);
        
        foreach ($profile_users as $pu) {
            $user->getFromDB($pu['users_id']);
            if ($user->fields['is_active'] && !isset($technicians[$user->getID()])) {
                $technicians[$user->getID()] = $user->getName();
            }
        }
    }
}

// Busca os grupos e modelos de chamado disponíveis para seleção.
// Usa <select> simples em vez de Group::dropdown()/TicketTemplate::dropdown()
// porque este assistente carrega jQuery/jQuery UI de um CDN à parte (necessário
// para o datepicker customizado abaixo), o que quebra o select2 do GLPI usado
// por esses dropdowns nesta página específica.
// Finds available groups and ticket templates for selection.
// Uses plain <select> instead of Group::dropdown()/TicketTemplate::dropdown()
// because this wizard loads jQuery/jQuery UI from a separate CDN (needed for
// the custom datepicker below), which breaks GLPI's select2 used by those
// dropdowns on this specific page.
$group = new Group();
$all_groups = $group->find([], 'name ASC');

$ticket_template = new TicketTemplate();
$all_ticket_templates = $ticket_template->find([], 'name ASC');

// Processamento do formulário quando enviado
// Form processing when submitted
if (isset($_POST['add'])) {
    try {
        // Verificação de segurança CSRF
        // CSRF security check
        if (!isset($_POST['_glpi_csrf_token'])) {
            throw new Exception(__('Token de segurança ausente. Recarregue a página e tente novamente.'));
        }

        // Validação da entidade
        // Entity validation
        
        $selected_entity_id = (int)$_POST['entities_id'];
        error_log("[ENTIDADE] Valor recebido: " . $selected_entity_id);
        
        if ($selected_entity_id < 0) {
            throw new Exception(__('Selecione uma entidade válida.'));
        }

        $entity = new Entity();
        if (!$entity->getFromDB($selected_entity_id)) {
            throw new Exception(__('A entidade selecionada não existe no sistema.'));
        }

        // Validação do tipo de item (contra a whitelist, antes de instanciar)
        // Item type validation (against the whitelist, before instantiating)
        if (!isset($_POST['itemtype']) || !in_array($_POST['itemtype'], PluginPreventivemaintenancePreventivemaintenance::getAllowedItemtypes(), true)) {
            throw new Exception(__('Selecione um tipo de item válido.'));
        }
        $itemtype = $_POST['itemtype'];

        // Validação do item
        // Item validation
        if (!isset($_POST['items_id']) || empty($_POST['items_id'])) {
            throw new Exception(__('Selecione um item válido.'));
        }

        $items_id = (int)$_POST['items_id'];
        $item = new $itemtype();
        if (!$item->getFromDB($items_id)) {
            throw new Exception(__('Item selecionado não encontrado.'));
        }

        // Verifica se o item pertence à entidade
        // Checks if item belongs to entity
        if ($item->fields['entities_id'] != $selected_entity_id) {
            throw new Exception(__('O item selecionado não pertence à entidade escolhida.'));
        }

        // Verifica se já existe manutenção para este item (mesmo tipo + mesmo id)
        // Checks if maintenance already exists for this item (same type + same id)
        $existing = $pm->find([
            'items_id' => $items_id,
            'itemtype' => $itemtype
        ]);

        if ($is_edit) {
            unset($existing[$id]);
        }

        if (count($existing) > 0) {
            throw new Exception(sprintf(
                __('Já existe uma manutenção cadastrada para o item %s (ID: %d)'),
                $item->getName(),
                $items_id
            ));
        }

        // Resolve o valor de recorrência: "custom" usa o campo de quantidade
        // personalizada de meses em vez de um preset fixo.
        // Resolves the recurrence value: "custom" uses the custom month-count
        // field instead of a fixed preset.
        $recurrence_months = ($_POST['recurrence_months'] ?? '') === 'custom'
            ? (int)($_POST['recurrence_months_custom'] ?? 0)
            : (int)($_POST['recurrence_months'] ?? 3);

        // Prepara os dados para gravação
        // Prepares data for saving
        $input = [
            'name' => $_POST['name'] ?? '',
            'entities_id' => $selected_entity_id,
            'is_recursive' => 0,
            'technician_id' => (int)$_POST['technician_id'],
            'groups_id' => (int)($_POST['groups_id'] ?? 0),
            'items_id' => $items_id,
            'itemtype' => $itemtype,
            'last_maintenance_date' => $_POST['last_maintenance_date'] ?? null,
            'next_maintenance_date' => $_POST['next_maintenance_date'],
            'maintenance_interval' => 30,
            'is_recurring' => isset($_POST['is_recurring']) ? 1 : 0,
            'recurrence_months' => $recurrence_months,
            'tickettemplates_id' => (int)($_POST['tickettemplates_id'] ?? 0),
        ];

        // Cálculo do intervalo de manutenção
        // Maintenance interval calculation
        if (!empty($_POST['last_maintenance_date']) && !empty($_POST['next_maintenance_date'])) {
            $last = new DateTime($_POST['last_maintenance_date']);
            $next = new DateTime($_POST['next_maintenance_date']);
            $interval = $last->diff($next)->days;
            $input['maintenance_interval'] = $interval;
        }

        // GRAVAÇÃO VIA CommonDBTM (reaproveita a validação de prepareInputForAdd/Update)
        // SAVE VIA CommonDBTM (reuses prepareInputForAdd/Update validation)
        if ($is_edit) {
            $input['id'] = $id;

            if (!$pm->update($input)) {
                // prepareInputForUpdate() já adiciona a mensagem de erro específica
                // prepareInputForUpdate() already added the specific error message
                Html::back();
            }

            Session::addMessageAfterRedirect(__('Manutenção atualizada com sucesso!'), true, INFO);
        } else {
            if (!$pm->add($input)) {
                // prepareInputForAdd() já adiciona a mensagem de erro específica
                // prepareInputForAdd() already added the specific error message
                Html::back();
            }

            Session::addMessageAfterRedirect(__('Manutenção criada com sucesso!'), true, INFO);
        }

        Html::redirect('preventivemaintenance.php');
        
    } catch (Exception $e) {
        error_log("[ERRO] Processamento: " . $e->getMessage());
        Session::addMessageAfterRedirect($e->getMessage(), false, ERROR);
        Html::back();
    }
}

// Processa a seleção de perfis técnicos se enviado
// Processes technician profiles selection if submitted
if (isset($_POST['save_selected_profiles'])) {
    $_SESSION['plugin_preventivemaintenance_selected_profiles'] = $_POST['profiles'] ?? ['Technician'];
    Html::back();
}

// Configuração do formulário
// Form configuration
$entity = new Entity();
// Busca apenas as entidades ativas da sessão do usuário
// Finds only active entities from user session
$entities = $entity->find(['id' => $_SESSION['glpiactiveentities']], 'completename ASC');

// Tipos de item permitidos e seus rótulos
// Allowed item types and their labels
$allowed_itemtypes = PluginPreventivemaintenancePreventivemaintenance::getAllowedItemtypes();
$itemtype_labels = [];
foreach ($allowed_itemtypes as $type) {
    $itemtype_labels[$type] = $type::getTypeName(1);
}

// Carrega os itens de todos os tipos permitidos, marcados com seu itemtype.
// Asset Definitions personalizadas nem sempre têm o campo is_deleted (depende
// da capacidade "Deletable" configurada), então o filtro é aplicado só quando
// a coluna existe.
// Loads items of every allowed type, tagged with their itemtype. Custom Asset
// Definitions don't always have an is_deleted field (depends on whether the
// "Deletable" capacity is configured), so the filter only applies when the
// column exists.
global $DB;
$all_items = [];
foreach ($allowed_itemtypes as $type) {
    $type_obj = new $type();
    $condition = $DB->fieldExists($type_obj->getTable(), 'is_deleted') ? ['is_deleted' => 0] : [];
    foreach ($type_obj->find($condition, "name ASC") as $row) {
        $all_items[] = [
            'id'           => $row['id'],
            'name'         => $row['name'],
            'entities_id'  => $row['entities_id'],
            'itemtype'     => $type,
        ];
    }
}

// Itens já vinculados a outra manutenção (chave itemtype|items_id, para não
// confundir, por exemplo, um Computer #5 com um Monitor #5)
// Items already linked to another maintenance record (itemtype|items_id key,
// so e.g. Computer #5 is not confused with Monitor #5)
$existing_maintenances = $pm->find([]);
$blocked_items = [];
foreach ($existing_maintenances as $maintenance) {
    if ($is_edit && $maintenance['id'] == $item_data['id']) continue;
    $blocked_items[] = $maintenance['itemtype'] . '|' . $maintenance['items_id'];
}

$token = Session::getNewCSRFToken();

// Exibe o cabeçalho do GLPI
// Displays GLPI header
Html::header(
    __('Manutenção Preventiva', 'preventivemaintenance'),
    $_SERVER['PHP_SELF'],
    'plugins',
    'preventivemaintenance'
);
?>

<!-- Estilos CSS para a interface -->
<!-- CSS styles for interface -->
<style>
    body {
        background-color: #cacccf !important;
    }
    
    #step2 {
        display: none;
    }
    .form-section {
        margin-bottom: 15px;
    }
    .form-section label {
        display: block;
        margin-bottom: 5px;
        font-weight: 500;
    }
    .form-control, .form-select {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ced4da;
        border-radius: 4px;
    }
    /* Estilo específico para o dropdown de técnico */
    /* Specific style for technician dropdown */
    select[name='technician_id'] {
        width: 100% !important;
    }
    .required {
        color: #dc3545;
    }
    .btn {
        padding: 8px 16px;
        border-radius: 4px;
    }
    .entity-info {
        background-color: #f8f9fa;
        padding: 10px;
        border-radius: 4px;
        margin-bottom: 20px;
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
    /* Estilos para o datepicker com intervalo */
    /* Styles for datepicker with interval */
    .ui-datepicker {
        width: 350px !important;
        padding: 10px;
        background: white;
        border: 1px solid #ddd;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        font-family: Arial, sans-serif;
    }
    .ui-datepicker-header {
        background: #f8f9fa;
        border-radius: 6px 6px 0 0;
        padding: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .ui-datepicker-title {
        font-weight: bold;
        display: flex;
        gap: 10px;
    }
    .ui-datepicker-month, .ui-datepicker-year {
        padding: 3px 5px;
        border-radius: 3px;
        border: 1px solid #ced4da;
    }
    .ui-datepicker-prev, .ui-datepicker-next {
        position: relative;
        top: auto;
        left: auto;
        right: auto;
        cursor: pointer;
        padding: 3px 8px;
        border-radius: 3px;
        background: #f0f0f0;
    }
    .ui-datepicker-prev:hover, .ui-datepicker-next:hover {
        background: #e0e0e0;
    }
    .ui-datepicker-calendar {
        width: 100%;
        margin-top: 10px;
    }
    .ui-datepicker-interval {
        padding: 10px;
        background: #f5f5f5;
        border-bottom: 1px solid #ddd;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
        margin: -10px -10px 10px -10px;
        border-radius: 8px 8px 0 0;
    }
    .ui-datepicker-interval select {
        padding: 6px;
        border: 1px solid #ced4da;
        border-radius: 4px;
        background: white;
        flex-grow: 1;
    }
    .ui-datepicker-interval button {
        padding: 6px 12px;
        background: #4CAF50;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        flex-grow: 1;
    }
    .ui-datepicker-interval button:hover {
        background: #45a049;
    }
    
    /* Estilos para o modal de seleção de perfis */
    /* Styles for profile selection modal */
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
        margin-bottom: 20px;
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
    .select-profile-btn {
        margin-bottom: 15px;
    }
</style>

<!-- HTML principal do formulário -->
<!-- Main form HTML -->
<div class='plugin-preventive-maintenance-container'>
    <div class='d-flex justify-content-between align-items-center mb-4'>
        <h2>
            <i class='fas fa-calendar-check me-2'></i>
            <?php echo $is_edit ? __('Editar Manutenção') : __('Nova Manutenção'); ?>
        </h2>
        <a href='preventivemaintenance.php' class='btn btn-outline-primary'>
            <i class='fas fa-arrow-left me-2'></i><?php echo __('Voltar'); ?>
        </a>
    </div>

    <div class='card'>
        <div class='card-body'>
            <form method='post' id='preventive_maintenance_form'>
                <?php echo Html::hidden('_glpi_csrf_token', ['value' => $token]); ?>
                <input type='hidden' name='add' value='1'>
                
                <!-- STEP 1 - Somente seleção da entidade -->
                <!-- STEP 1 - Only entity selection -->
                <div id='step1'>
                    <!-- Botão para selecionar perfis técnicos -->
                    <!-- Button to select technician profiles -->
                    <div class="select-profile-btn">
                        <button type="button" id="selectProfilesBtn" class="btn btn-info">
                            <i class="fas fa-user-cog me-2"></i><?php echo __('Selecionar Perfis Técnicos'); ?>
                        </button>
                        <small class="text-muted d-block mt-1"><?php echo __('Perfis selecionados: ') . implode(', ', $selected_profiles); ?></small>
                    </div>
                    
                    <div class='form-section'>
                        <label for='entities_id_select'><?php echo __('Entidade'); ?> <span class='required'>*</span></label>
                        <select name='entities_id_select' id='entities_id_select' class='form-select' required>
                            <option value=''><?php echo __('Selecione uma entidade'); ?></option>
                            <?php foreach ($entities as $ent) {
                                $selected = ($is_edit && $item_data['entities_id'] == $ent['id']) ? 'selected' : '';
                                echo "<option value='{$ent['id']}' $selected>{$ent['completename']}</option>";
                            } ?>
                        </select>
                    </div>
                    
                    <div class='d-flex justify-content-end mt-4'>
                        <button type='button' class='btn btn-primary' id='nextButton'>
                            <i class='fas fa-arrow-right me-2'></i><?php echo __('Próximo'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- STEP 2 - Demais campos -->
                <!-- STEP 2 - Other fields -->
                <div id='step2'>
                    <input type='hidden' name='entities_id' id='entities_id' value='<?php echo $is_edit ? $item_data['entities_id'] : ''; ?>'>
                    
                    <div class='entity-info'>
                        <strong><?php echo __('Entidade selecionada:'); ?></strong>
                        <span id='selected-entity-name'></span>
                        (ID: <span id='selected-entity-id'></span>)
                    </div>
                    
                    <div class='form-section'>
                        <label for='name'><?php echo __('Nome da Manutenção'); ?> <span class='required'>*</span></label>
                        <input type='text' name='name' id='name' class='form-control' 
                               value="<?php echo $is_edit ? htmlspecialchars($item_data['name']) : ''; ?>" required>
                    </div>
                    
                    <div class='form-section'>
                        <label for='technician_id'><?php echo __('Técnico Responsável'); ?></label>
                        <select name='technician_id' id='technician_id' class='form-select'>
                            <option value=''><?php echo __('Selecione um técnico responsável'); ?></option>
                            <?php
                            foreach ($technicians as $id => $name) {
                                $selected = ($is_edit && $item_data['technician_id'] == $id) ? 'selected' : '';
                                echo "<option value='{$id}' {$selected}>{$name}</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class='form-section'>
                        <label for='groups_id'><?php echo __('Grupo Responsável'); ?></label>
                        <select name='groups_id' id='groups_id' class='form-select'>
                            <option value='0'><?php echo __('Selecione um grupo responsável'); ?></option>
                            <?php
                            foreach ($all_groups as $grp) {
                                $selected = ($is_edit && ($item_data['groups_id'] ?? 0) == $grp['id']) ? 'selected' : '';
                                echo "<option value='{$grp['id']}' {$selected}>{$grp['name']}</option>";
                            }
                            ?>
                        </select>
                        <small class="text-muted d-block mt-1">
                            <?php echo __('Pode ser usado no lugar do técnico ou junto com ele — o grupo costuma ser mais estável ao longo do tempo.'); ?>
                        </small>
                    </div>

                    <div class='form-section'>
                        <label for='itemtype'><?php echo __('Tipo de Item'); ?> <span class='required'>*</span></label>
                        <select name='itemtype' id='itemtype' class='form-select' required>
                            <?php foreach ($itemtype_labels as $type => $label) {
                                $selected = ($is_edit && $item_data['itemtype'] == $type)
                                    || (!$is_edit && $type === 'Computer') ? 'selected' : '';
                                echo "<option value='{$type}' $selected>{$label}</option>";
                            } ?>
                        </select>
                    </div>

                    <div class='form-section'>
                        <label for='items_id'><?php echo __('Item'); ?> <span class='required'>*</span></label>
                        <select name='items_id' id='items_id' class='form-select' required>
                            <option value=''><?php echo __('Selecione um item'); ?></option>
                            <?php
                            if ($is_edit && in_array($item_data['itemtype'], $allowed_itemtypes, true)) {
                                $edit_item = new $item_data['itemtype']();
                                $edit_item->getFromDB($item_data['items_id']);
                                echo "<option value='{$item_data['items_id']}' selected>{$edit_item->getName()}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class='form-section'>
                        <label for='last_maintenance_date'><?php echo __('Última Manutenção'); ?></label>
                        <input type='text' id='last_maintenance_date' name='last_maintenance_date' 
                               value="<?php echo $is_edit ? $item_data['last_maintenance_date'] : ''; ?>" 
                               class='form-control'>
                    </div>
                    
                    <div class='form-section'>
                        <label for='next_maintenance_date'><?php echo __('Próxima Manutenção'); ?> <span class='required'>*</span></label>
                        <input type='text' id='next_maintenance_date' name='next_maintenance_date'
                               value="<?php echo $is_edit ? $item_data['next_maintenance_date'] : ''; ?>"
                               class='form-control interval-field' required>
                    </div>

                    <div class='form-section'>
                        <label>
                            <input type='checkbox' name='is_recurring' id='is_recurring' value='1'
                                   <?php echo ($is_edit && !empty($item_data['is_recurring'])) ? 'checked' : ''; ?>>
                            <?php echo __('Recorrência automática'); ?>
                        </label>
                        <small class="text-muted d-block mt-1">
                            <?php echo __('Se marcado, a próxima manutenção é sempre reagendada automaticamente, independente do chamado atual ter sido resolvido ou não.'); ?>
                        </small>
                        <div id='recurrence_months_wrapper' class='mt-2' style="<?php echo ($is_edit && !empty($item_data['is_recurring'])) ? '' : 'display:none;'; ?>">
                            <label for='recurrence_months'><?php echo __('A cada'); ?></label>
                            <?php
                            $recurrence_options = PluginPreventivemaintenancePreventivemaintenance::getRecurrenceMonthOptions();
                            $selected_recurrence = $is_edit ? (int)($item_data['recurrence_months'] ?? 3) : 3;
                            $is_custom_recurrence = $is_edit && !array_key_exists($selected_recurrence, $recurrence_options);
                            ?>
                            <select name='recurrence_months' id='recurrence_months' class='form-select'>
                                <?php
                                foreach ($recurrence_options as $months => $label) {
                                    $selected = (!$is_custom_recurrence && $months == $selected_recurrence) ? 'selected' : '';
                                    echo "<option value='{$months}' {$selected}>{$label}</option>";
                                }
                                $selected = $is_custom_recurrence ? 'selected' : '';
                                echo "<option value='custom' {$selected}>" . __('Personalizado') . "</option>";
                                ?>
                            </select>
                            <div id='recurrence_months_custom_wrapper' class='mt-2' style="<?php echo $is_custom_recurrence ? '' : 'display:none;'; ?>">
                                <label for='recurrence_months_custom'><?php echo __('Quantidade de meses'); ?></label>
                                <input type='number' name='recurrence_months_custom' id='recurrence_months_custom'
                                       class='form-control' min='1' max='120'
                                       value="<?php echo $is_custom_recurrence ? $selected_recurrence : ''; ?>">
                            </div>
                        </div>
                    </div>

                    <div class='form-section'>
                        <label for='tickettemplates_id'><?php echo __('Modelo de Chamado'); ?></label>
                        <select name='tickettemplates_id' id='tickettemplates_id' class='form-select'>
                            <option value='0'><?php echo __('Selecione um modelo de chamado'); ?></option>
                            <?php
                            foreach ($all_ticket_templates as $tt) {
                                $selected = ($is_edit && ($item_data['tickettemplates_id'] ?? 0) == $tt['id']) ? 'selected' : '';
                                echo "<option value='{$tt['id']}' {$selected}>{$tt['name']}</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class='d-flex justify-content-between mt-4'>
                        <button type='button' class='btn btn-secondary' id='backButton'>
                            <i class='fas fa-arrow-left me-2'></i><?php echo __('Voltar'); ?>
                        </button>
                        <button type='submit' class='btn btn-success'>
                            <i class='fas fa-save me-2'></i><?php echo $is_edit ? __('Atualizar') : __('Salvar'); ?>
                        </button>
                    </div>
                </div>
            </form>
            
            <!-- Modal para seleção de perfis técnicos -->
            <!-- Modal for technician profiles selection -->
            <div id="profileModal" class="profile-modal">
                <div class="profile-modal-content">
                    <div class="profile-modal-header">
                        <div class="profile-modal-title"><?php echo __('Selecionar Perfis Técnicos'); ?></div>
                        <span class="profile-modal-close">&times;</span>
                    </div>
                    <form method="post" id="profileSelectionForm">
                        <?php echo Html::hidden('_glpi_csrf_token', ['value' => $token]); ?>
                        <input type="hidden" name="save_selected_profiles" value="1">
                        
                        <div class="profile-checkboxes">
                            <?php foreach ($all_profiles as $prof): ?>
                                <div class="profile-checkbox-item">
                                    <label>
                                        <input type="checkbox" name="profiles[]" value="<?php echo $prof['name']; ?>"
                                            <?php echo in_array($prof['name'], $selected_profiles) ? 'checked' : ''; ?>>
                                        <?php echo $prof['name']; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="profile-modal-footer">
                            <button type="button" class="btn btn-secondary" id="cancelProfileSelection"><?php echo __('Cancelar'); ?></button>
                            <button type="submit" class="btn btn-primary"><?php echo __('Salvar Seleção'); ?></button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Inclusão de bibliotecas JavaScript -->
            <!-- JavaScript libraries inclusion -->
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-ui-i18n/1.12.1/jquery-ui-i18n.min.js"></script>

            <!-- Script JavaScript para funcionalidades do formulário -->
            <!-- JavaScript script for form functionalities -->
            <script>
            const itemsData = <?php echo json_encode(array_values($all_items)); ?>;
            const blockedItems = <?php echo json_encode($blocked_items); ?>;
            const currentEditItemtype = <?php echo json_encode($is_edit ? $item_data['itemtype'] : null); ?>;
            const currentEditItemsId = <?php echo json_encode($is_edit ? (int)$item_data['items_id'] : null); ?>;
            
            $(document).ready(function() {
                // Configuração de localização para português
                // Portuguese localization setup
                $.datepicker.regional['pt-BR'] = {
                    closeText: 'Fechar',
                    prevText: '&#x3C;Anterior',
                    nextText: 'Próximo&#x3E;',
                    currentText: 'Hoje',
                    monthNames: ['Janeiro','Fevereiro','Março','Abril','Maio','Junho',
                    'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'],
                    monthNamesShort: ['Jan','Fev','Mar','Abr','Mai','Jun',
                    'Jul','Ago','Set','Out','Nov','Dez'],
                    dayNames: ['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'],
                    dayNamesShort: ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'],
                    dayNamesMin: ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'],
                    weekHeader: 'Sm',
                    dateFormat: 'yy-mm-dd',
                    firstDay: 0,
                    isRTL: false,
                    showMonthAfterYear: false,
                    yearSuffix: ''};
                $.datepicker.setDefaults($.datepicker.regional['pt-BR']);

                // Inicializa o datepicker para a última manutenção
                // Initializes datepicker for last maintenance
                $("#last_maintenance_date").datepicker({
                    dateFormat: 'yy-mm-dd',
                    showAnim: 'fadeIn',
                    changeMonth: true,
                    changeYear: true,
                    showButtonPanel: true,
                    onSelect: function(dateText) {
                        $(this).val(dateText);
                    },
                    beforeShow: function(input, inst) {
                        setTimeout(function() {
                            var button = inst.dpDiv.find('.ui-datepicker-current');
                            button.unbind('click').click(function() {
                                var today = new Date();
                                var formattedDate = $.datepicker.formatDate('yy-mm-dd', today);
                                $(input).val(formattedDate);
                                inst.dpDiv.hide();
                            });
                        }, 1);
                    }
                });
                
                // Inicializa o datepicker para a próxima manutenção com intervalo
                // Initializes datepicker for next maintenance with interval
                $("#next_maintenance_date").datepicker({
                    dateFormat: 'yy-mm-dd',
                    showAnim: 'fadeIn',
                    changeMonth: true,
                    changeYear: true,
                    showButtonPanel: true,
                    onSelect: function(dateText) {
                        $(this).val(dateText);
                    },
                    beforeShow: function(input, inst) {
                        setTimeout(function() {
                            // Configura o botão "Hoje"
                            // Configures "Today" button
                            var button = inst.dpDiv.find('.ui-datepicker-current');
                            button.unbind('click').click(function() {
                                var today = new Date();
                                var formattedDate = $.datepicker.formatDate('yy-mm-dd', today);
                                $(input).val(formattedDate);
                                inst.dpDiv.hide();
                            });
                            
                            // Adiciona controles de intervalo
                            // Adds interval controls
                            var dpDiv = $(inst.dpDiv);
                            dpDiv.find('.ui-datepicker-interval').remove();
                            
                            var controls = $(
                                '<div class="ui-datepicker-interval">' +
                                '  <span><?php echo __("Intervalo"); ?>:</span>' +
                                '  <select class="interval-value">' +
                                '    <option value="1">1 mês</option>' +
                                '    <option value="2">2 meses</option>' +
                                '    <option value="3">3 meses</option>' +
                                '    <option value="4">4 meses</option>' +
                                '    <option value="5">5 meses</option>' +
                                '    <option value="6" selected>6 meses</option>' +
                                '    <option value="7">7 meses</option>' +
                                '    <option value="8">8 meses</option>' +
                                '    <option value="9">9 meses</option>' +
                                '    <option value="10">10 meses</option>' +
                                '    <option value="11">11 meses</option>' +
                                '    <option value="12">1 ano</option>' +
                                '  </select>' +
                                '  <button type="button" class="apply-interval"><?php echo __("Aplicar"); ?></button>' +
                                '</div>'
                            );
                            
                            dpDiv.prepend(controls);
                            
                            dpDiv.find('.apply-interval').click(function() {
                                var lastDate = $("#last_maintenance_date").val();
                                if (!lastDate) {
                                    alert('<?php echo __("Informe a data da última manutenção"); ?>');
                                    return;
                                }
                                
                                var months = parseInt(dpDiv.find('.interval-value').val());
                                var date = new Date(lastDate);
                                date.setMonth(date.getMonth() + months);
                                
                                // Ajusta para o final do mês se necessário
                                // Adjusts to end of month if needed
                                var originalDay = new Date(lastDate).getDate();
                                if (date.getDate() !== originalDay) {
                                    date.setDate(0);
                                }
                                
                                var formatted = $.datepicker.formatDate('yy-mm-dd', date);
                                $("#next_maintenance_date").val(formatted).datepicker('hide');
                            });
                        }, 1);
                    }
                });

                <?php if ($is_edit) { ?>
                    // Se estiver editando, configura os valores iniciais
                    // If editing, sets initial values
                    const entityId = <?php echo $item_data['entities_id']; ?>;
                    const entityName = $(`#entities_id_select option[value='${entityId}']`).text();
                    
                    $('#entities_id').val(entityId);
                    $('#selected-entity-name').text('Entidade: ' + entityName);
                    $('#selected-entity-id').text(entityId);
                    
                    $('#step1').hide();
                    $('#step2').show();
                <?php } ?>
                
                // Evento do botão Próximo
                // Next button event
                $('#nextButton').click(function() {
                    if (!$('#entities_id_select').val()) {
                        alert('<?php echo __("Selecione uma entidade"); ?>');
                        return;
                    }

                    const entityId = $('#entities_id_select').val();
                    const entityName = $('#entities_id_select option:selected').text();

                    $('#entities_id').val(entityId);
                    $('#selected-entity-name').text('Entidade: ' + entityName);
                    $('#selected-entity-id').text(entityId);
                    loadItems($('#itemtype').val(), entityId);

                    $('#step1').hide();
                    $('#step2').show();
                });

                // Evento do botão Voltar
                // Back button event
                $('#backButton').click(function() {
                    $('#step2').hide();
                    $('#step1').show();
                });

                // Mostra/esconde o seletor de intervalo conforme a recorrência é marcada
                // Shows/hides the interval selector as recurrence is toggled
                $('#is_recurring').change(function() {
                    $('#recurrence_months_wrapper').toggle(this.checked);
                });

                // Mostra/esconde o campo de quantidade personalizada de meses
                // Shows/hides the custom month-count field
                $('#recurrence_months').change(function() {
                    $('#recurrence_months_custom_wrapper').toggle($(this).val() === 'custom');
                });

                // Recarrega os itens disponíveis quando o tipo de item muda
                // Reloads available items when the item type changes
                $('#itemtype').change(function() {
                    const entityId = $('#entities_id').val();
                    if (entityId) {
                        loadItems($(this).val(), entityId);
                    }
                });

                // Carrega os itens disponíveis para o tipo e a entidade selecionados
                // Loads available items for the selected type and entity
                function loadItems(itemtype, entityId) {
                    const select = $('#items_id');
                    select.find('option').not(':first').remove();

                    const filteredItems = itemsData.filter(item => {
                        if (item.itemtype !== itemtype || item.entities_id != entityId) {
                            return false;
                        }
                        const key = item.itemtype + '|' + item.id;
                        const isCurrentEditItem = (currentEditItemtype === item.itemtype && currentEditItemsId === item.id);
                        return !blockedItems.includes(key) || isCurrentEditItem;
                    });

                    if (filteredItems.length > 0) {
                        filteredItems.forEach(item => {
                            select.append(new Option(item.name, item.id));
                        });
                    } else {
                        const option = new Option('<?php echo __("Nenhum item disponível"); ?>', '');
                        option.disabled = true;
                        select.append(option);
                    }
                }
                
                // ==============================================
                // CÓDIGO PARA O BOTÃO DE SELEÇÃO DE PERFIS TÉCNICOS
                // CODE FOR TECHNICIAN PROFILES SELECTION BUTTON
                // ==============================================
                
                // Abre o modal de seleção de perfis
                // Opens profile selection modal
                $('#selectProfilesBtn').click(function() {
                    $('#profileModal').show();
                });
                
                // Fecha o modal quando clica no X
                // Closes modal when clicking X
                $('.profile-modal-close').click(function() {
                    $('#profileModal').hide();
                });
                
                // Fecha o modal quando clica em Cancelar
                // Closes modal when clicking Cancel
                $('#cancelProfileSelection').click(function() {
                    $('#profileModal').hide();
                });
                
                // Fecha o modal quando clica fora da área de conteúdo
                // Closes modal when clicking outside content area
                $(window).click(function(event) {
                    if (event.target == $('#profileModal')[0]) {
                        $('#profileModal').hide();
                    }
                });
                
                // Processa o formulário de seleção de perfis
                // Processes profile selection form
                $('#profileSelectionForm').submit(function(e) {
                    e.preventDefault();
                    
                    // Verifica se pelo menos um perfil foi selecionado
                    // Checks if at least one profile was selected
                    if ($('#profileSelectionForm input[name="profiles[]"]:checked').length === 0) {
                        alert('<?php echo __("Selecione pelo menos um perfil técnico"); ?>');
                        return;
                    }
                    
                    // Envia o formulário via AJAX
                    // Submits form via AJAX
                    $.ajax({
                        url: window.location.href,
                        type: 'POST',
                        data: $(this).serialize(),
                        success: function(response) {
                            // Recarrega a página para atualizar a lista de técnicos
                            // Reloads page to update technicians list
                            window.location.reload();
                        },
                        error: function() {
                            alert('<?php echo __("Erro ao salvar a seleção de perfis"); ?>');
                        }
                    });
                });
            });
            </script>
        </div>
    </div>

    <?php if ($is_edit): ?>
    <!-- Histórico de chamados desta manutenção -->
    <!-- Ticket history for this maintenance -->
    <div class='card mt-4' id='history'>
        <div class='card-body'>
            <h4><i class="fas fa-history me-2"></i><?php echo __('Histórico de Chamados'); ?></h4>
            <?php if (empty($ticket_history)): ?>
                <div class="alert alert-warning mt-3">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo __('Nenhum chamado foi criado ainda para esta manutenção.'); ?>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="table table-hover mt-3">
                        <thead>
                            <tr>
                                <th style="text-align: center"><?php echo __('Chamado'); ?></th>
                                <th style="text-align: center"><?php echo __('Status'); ?></th>
                                <th style="text-align: center"><?php echo __('Criado em'); ?></th>
                                <th style="text-align: center"><?php echo __('Resolvido em'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ticket_history as $row): ?>
                                <?php
                                $is_closed = in_array((int)$row['status'], [Ticket::CLOSED, Ticket::SOLVED], true);
                                $status_class = $is_closed ? 'bg-success' : 'bg-warning';
                                ?>
                                <tr>
                                    <td style="text-align: center">
                                        <a href="<?php echo $CFG_GLPI['root_doc']; ?>/front/ticket.form.php?id=<?php echo $row['id']; ?>" target="_blank">
                                            #<?php echo $row['id']; ?> - <?php echo htmlspecialchars($row['name']); ?>
                                        </a>
                                    </td>
                                    <td style="text-align: center">
                                        <span class="badge <?php echo $status_class; ?>"><?php echo Ticket::getStatus((int)$row['status']); ?></span>
                                    </td>
                                    <td style="text-align: center"><?php echo Html::convDateTime($row['date_creation']); ?></td>
                                    <td style="text-align: center"><?php echo !empty($row['resolved_at']) ? Html::convDateTime($row['resolved_at']) : '-'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Rodapé personalizado -->
    <!-- Custom footer -->
    <div class="custom-footer">
        <i class="fas fa-code"></i> <?= __('Desenvolvido por WIDA - Work Information Developments and Analytics') ?>
    </div>
</div>

<?php
// Exibe o rodapé do GLPI
// Displays GLPI footer
Html::footer();
?>
