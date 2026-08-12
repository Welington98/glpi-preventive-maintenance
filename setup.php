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
 
 


//Define a versão e informações básicas do plugin
//Defines the plugin version and basic information
function plugin_version_preventivemaintenance() {
    return [
        'name'           => 'Manutenção Preventiva',
        'version'        => '1.0.0',
        'author'         => 'WIDA',
        'license'        => 'GPLv2+',
        'homepage'       => 'https://widatecnologia.com.br',
        'description'    => __('Gerencie manutenções preventivas de equipamentos.'),
        'minGlpiVersion' => '10.0.0',
        'requirements'   => [
            'glpi' => [
                'min' => '10.0.0',
                'max' => '11.9.99'
            ]
        ]
    ];
}

//Verifica os pré-requisitos para instalação do plugin (versão do GLPI)
//Checks plugin installation prerequisites (GLPI version)
function plugin_preventivemaintenance_check_prerequisites() {
    if (version_compare(GLPI_VERSION, '10.0.0', '<')) {
        echo "This plugin requires GLPI version 10.0.0 or higher";
        return false;
    }
    return true;
}

//Verifica a configuração do plugin (sempre retorna true neste caso)
//Checks plugin configuration (always returns true in this case)
function plugin_preventivemaintenance_check_config($verbose = false) {
    return true;
}

//Função de instalação do plugin - cria tabelas, registra plugin e configura permissões
//Plugin installation function - creates tables, registers plugin and configures permissions
function plugin_preventivemaintenance_install() {
    global $DB;

    // 1. Criar tabelas se não existirem
    // 1. Create tables if they don't exist
    
    // Tabela principal de manutenções preventivas
    if (!$DB->tableExists('glpi_plugin_preventivemaintenance_preventivemaintenances')) {
        $query = "CREATE TABLE `glpi_plugin_preventivemaintenance_preventivemaintenances` (
            `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `entities_id` int(10) UNSIGNED NOT NULL DEFAULT '0',
            `is_recursive` tinyint(1) NOT NULL DEFAULT '0',
            `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `items_id` int(10) UNSIGNED NOT NULL DEFAULT '0',
            `itemtype` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'Computer',
            `technician_id` int(10) UNSIGNED NOT NULL,
            `groups_id` int(10) UNSIGNED NOT NULL DEFAULT '0',
            `last_maintenance_date` date DEFAULT NULL,
            `next_maintenance_date` date NOT NULL,
            `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `date_mod` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `maintenance_interval` int(11) NOT NULL DEFAULT '30',
            `is_recurring` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
            `recurrence_months` int(11) UNSIGNED NOT NULL DEFAULT '3',
            `tickettemplates_id` int(10) UNSIGNED NOT NULL DEFAULT '0',
            PRIMARY KEY (`id`),
            KEY `entities_id` (`entities_id`),
            KEY `is_recursive` (`is_recursive`),
            KEY `items_id` (`items_id`),
            KEY `technician_id` (`technician_id`),
            KEY `groups_id` (`groups_id`),
            KEY `tickettemplates_id` (`tickettemplates_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $DB->doQuery($query);
    } else {
        // Instalação existente: adiciona as colunas de recorrência fixa e template
        // de chamado de forma idempotente, sem afetar registros já cadastrados.
        // Existing install: idempotently adds the fixed-recurrence and ticket
        // template columns, without affecting already-registered records.
        if (!$DB->fieldExists('glpi_plugin_preventivemaintenance_preventivemaintenances', 'is_recurring')) {
            $DB->doQuery(
                "ALTER TABLE `glpi_plugin_preventivemaintenance_preventivemaintenances`
                 ADD COLUMN `is_recurring` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' AFTER `maintenance_interval`"
            );
        }
        if (!$DB->fieldExists('glpi_plugin_preventivemaintenance_preventivemaintenances', 'recurrence_months')) {
            $DB->doQuery(
                "ALTER TABLE `glpi_plugin_preventivemaintenance_preventivemaintenances`
                 ADD COLUMN `recurrence_months` int(11) UNSIGNED NOT NULL DEFAULT '3' AFTER `is_recurring`"
            );
        }
        if (!$DB->fieldExists('glpi_plugin_preventivemaintenance_preventivemaintenances', 'tickettemplates_id')) {
            $DB->doQuery(
                "ALTER TABLE `glpi_plugin_preventivemaintenance_preventivemaintenances`
                 ADD COLUMN `tickettemplates_id` int(10) UNSIGNED NOT NULL DEFAULT '0' AFTER `is_recurring`"
            );
            $DB->doQuery(
                "ALTER TABLE `glpi_plugin_preventivemaintenance_preventivemaintenances`
                 ADD KEY `tickettemplates_id` (`tickettemplates_id`)"
            );
        }
        if (!$DB->fieldExists('glpi_plugin_preventivemaintenance_preventivemaintenances', 'groups_id')) {
            $DB->doQuery(
                "ALTER TABLE `glpi_plugin_preventivemaintenance_preventivemaintenances`
                 ADD COLUMN `groups_id` int(10) UNSIGNED NOT NULL DEFAULT '0' AFTER `technician_id`"
            );
            $DB->doQuery(
                "ALTER TABLE `glpi_plugin_preventivemaintenance_preventivemaintenances`
                 ADD KEY `groups_id` (`groups_id`)"
            );
        }
    }

    // Tabela de tickets de manutenção
    if (!$DB->tableExists('glpi_plugin_preventivemaintenance_tickets')) {
        // Instalação nova: já cria com colunas genéricas items_id/itemtype
        // Fresh install: create directly with generic items_id/itemtype columns
        $query = "CREATE TABLE `glpi_plugin_preventivemaintenance_tickets` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `ticket_id` int(11) NOT NULL,
            `maintenance_id` int(10) UNSIGNED NOT NULL DEFAULT '0',
            `items_id` int(11) NOT NULL,
            `itemtype` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Computer',
            `maintenance_name` varchar(255) NOT NULL,
            `date_creation` datetime NOT NULL,
            `resolved_at` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `ticket_id` (`ticket_id`),
            KEY `item` (`items_id`, `itemtype`),
            KEY `maintenance_id` (`maintenance_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $DB->doQuery($query);
    } else {
        // Instalação existente: migração idempotente de computer_id para items_id/itemtype
        // Existing install: idempotent migration from computer_id to items_id/itemtype
        if (!$DB->fieldExists('glpi_plugin_preventivemaintenance_tickets', 'itemtype')) {
            $DB->doQuery(
                "ALTER TABLE `glpi_plugin_preventivemaintenance_tickets`
                 ADD COLUMN `itemtype` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Computer' AFTER `computer_id`"
            );
        }

        if ($DB->fieldExists('glpi_plugin_preventivemaintenance_tickets', 'computer_id')
            && !$DB->fieldExists('glpi_plugin_preventivemaintenance_tickets', 'items_id')) {
            // Garante itemtype preenchido antes de renomear a coluna
            // Ensures itemtype is filled before renaming the column
            $DB->doQuery(
                "UPDATE `glpi_plugin_preventivemaintenance_tickets`
                 SET `itemtype` = 'Computer' WHERE `itemtype` = '' OR `itemtype` IS NULL"
            );
            // Rename preserva os dados existentes
            // Rename preserves existing data
            $DB->doQuery(
                "ALTER TABLE `glpi_plugin_preventivemaintenance_tickets`
                 CHANGE COLUMN `computer_id` `items_id` int(11) NOT NULL"
            );
            $DB->doQuery(
                "ALTER TABLE `glpi_plugin_preventivemaintenance_tickets`
                 ADD KEY `item` (`items_id`, `itemtype`)"
            );
        }

        // Adiciona maintenance_id (vínculo direto e confiável com o registro de
        // manutenção) e resolved_at (para manter histórico em vez de apagar a
        // linha ao resolver o chamado).
        // Adds maintenance_id (direct, reliable link to the maintenance record)
        // and resolved_at (to keep history instead of deleting the row when the
        // ticket is resolved).
        if (!$DB->fieldExists('glpi_plugin_preventivemaintenance_tickets', 'maintenance_id')) {
            $DB->doQuery(
                "ALTER TABLE `glpi_plugin_preventivemaintenance_tickets`
                 ADD COLUMN `maintenance_id` int(10) UNSIGNED NOT NULL DEFAULT '0' AFTER `ticket_id`"
            );
            $DB->doQuery(
                "ALTER TABLE `glpi_plugin_preventivemaintenance_tickets`
                 ADD KEY `maintenance_id` (`maintenance_id`)"
            );
            // Preenche o vínculo de linhas existentes por correspondência de
            // item+nome (melhor esforço, para não perder o histórico já gravado).
            // Backfills existing rows' link by item+name match (best effort, so
            // already-recorded history isn't lost).
            $DB->doQuery(
                "UPDATE `glpi_plugin_preventivemaintenance_tickets` pmt
                 INNER JOIN `glpi_plugin_preventivemaintenance_preventivemaintenances` pm
                     ON pm.`items_id` = pmt.`items_id`
                     AND pm.`itemtype` = pmt.`itemtype`
                     AND pm.`name` = pmt.`maintenance_name`
                 SET pmt.`maintenance_id` = pm.`id`
                 WHERE pmt.`maintenance_id` = 0"
            );
        }
        if (!$DB->fieldExists('glpi_plugin_preventivemaintenance_tickets', 'resolved_at')) {
            $DB->doQuery(
                "ALTER TABLE `glpi_plugin_preventivemaintenance_tickets`
                 ADD COLUMN `resolved_at` datetime DEFAULT NULL AFTER `date_creation`"
            );
        }
    }

    // Tabela de configuração do plugin
    // Plugin configuration table
    if (!$DB->tableExists('glpi_plugin_preventivemaintenance_config')) {
        $query = "CREATE TABLE `glpi_plugin_preventivemaintenance_config` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            `value` text DEFAULT NULL,
            `date_creation` datetime DEFAULT NULL,
            `date_mod` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $DB->doQuery($query);
    }

    // 2. Registrar o plugin
    // 2. Register the plugin
    $plugin = new Plugin();
    if (!$plugin->getFromDBbyDir('preventivemaintenance')) {
        $plugin_id = $plugin->add([
            'directory' => 'preventivemaintenance',
            'name'      => 'Manutenção Preventiva',
            'state'     => Plugin::NOTINSTALLED
        ]);
    } else {
        $plugin_id = $plugin->getID();
    }

    // 3. Configurar permissões corretamente
    // 3. Configure permissions correctly
    $rightname = 'plugin_preventivemaintenance';
    // addProfileRights() sempre faz INSERT (não é idempotente); evita erro de
    // chave duplicada caso install() rode de novo sem uninstall antes.
    // addProfileRights() always INSERTs (not idempotent); avoids a duplicate-key
    // error if install() runs again without an uninstall in between.
    $dbu = new DbUtils();
    if ($dbu->countElementsInTable('glpi_profilerights', ['name' => $rightname]) == 0) {
        ProfileRight::addProfileRights([$rightname]);
    }

    // Define os níveis de permissão via ProfileRight::updateProfileRights(), o método
    // padrão do GLPI para gravar em glpi_profilerights ($profile->update() não grava
    // direitos de plugin, só campos nativos de glpi_profiles).
    // Sets permission levels via ProfileRight::updateProfileRights(), GLPI's standard
    // method for writing to glpi_profilerights ($profile->update() does not persist
    // plugin rights, only glpi_profiles' native fields).
    $profile = new Profile();
    foreach ($profile->find() as $prof) {
        $rights = match($prof['name']) {
            'Super-Admin' => 31,  // Todos direitos
            'Admin', 'Supervisor' => 7,  // Ler+Criar+Atualizar
            'Technician' => 3,    // Ler+Criar
            default => 1          // Ler
        };

        ProfileRight::updateProfileRights($prof['id'], [$rightname => $rights]);
    }

    return true;
}
//Função de desinstalação - remove direitos e registro do plugin
//Uninstallation function - removes rights and plugin registration
//
// As tabelas de dados do plugin (manutenções cadastradas, histórico de
// chamados e configuração) são propositalmente preservadas: desinstalar o
// plugin não deve apagar dados já cadastrados. Se o plugin for reinstalado
// depois, plugin_preventivemaintenance_install() encontra as tabelas
// existentes e continua funcionando normalmente (guardas idempotentes).
//
// The plugin's data tables (registered maintenances, ticket history and
// configuration) are intentionally preserved: uninstalling the plugin
// should not delete already-registered data. If the plugin is reinstalled
// later, plugin_preventivemaintenance_install() finds the existing tables
// and keeps working normally (idempotent guards).
function plugin_preventivemaintenance_uninstall() {
    global $DB;

    // 1. Remover direitos
    // 1. Remove rights
    $rightname = 'plugin_preventivemaintenance';
    $DB->delete('glpi_profilerights', ['name' => $rightname]);

    // 2. Remover o plugin
    // 2. Remove the plugin
    $plugin = new Plugin();
    if ($plugin->getFromDBbyDir('preventivemaintenance')) {
        $plugin->delete(['id' => $plugin->getID()]);
    }

    return true;
}
//Inicializa o plugin e configura hooks (ganchos) para integração com o GLPI
//Initializes the plugin and configures hooks for GLPI integration
function plugin_init_preventivemaintenance() {
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['preventivemaintenance'] = true;
    $PLUGIN_HOOKS['config_page']['preventivemaintenance'] = 'front/preventivemaintenance.php';
    $PLUGIN_HOOKS["menu_toadd"]['preventivemaintenance'] = array('plugins'  => 'PluginPreventivemaintenanceMenu');
    $PLUGIN_HOOKS['rights']['preventivemaintenance'] = 'plugin_preventivemaintenance_getRights';
    $PLUGIN_HOOKS['menu_entry_icon']['preventivemaintenance'] = Plugin::getWebDir('preventivemaintenance', false) . '/pics/logopm.png';
    
    $PLUGIN_HOOKS['use_massive_action']['preventivemaintenance'] = true;
    $PLUGIN_HOOKS['search_options']['preventivemaintenance'] = true;

    $PLUGIN_HOOKS['item_update']['preventivemaintenance'] = [
        'Ticket' => ['PluginPreventivemaintenancePreventivemaintenance', 'updateMaintenanceAfterTicket']];
        
        
    $PLUGIN_HOOKS['post_init']['preventivemaintenance'] = 'plugin_preventivemaintenance_postinit';
    $PLUGIN_HOOKS['user_login']['preventivemaintenance'] = ['PluginPreventivemaintenanceProfile', 'userLoginHook'];

    Plugin::registerClass('PluginPreventivemaintenanceProfile', ['addtabon' => ['Profile']]);
    Plugin::registerClass('PluginPreventivemaintenanceMenu');
    Plugin::registerClass('PluginPreventivemaintenancePreventivemaintenance');
}

//Define os direitos/permissões disponíveis para o plugin
//Defines available rights/permissions for the plugin
function plugin_preventivemaintenance_getRights() {
    return [
        ['itemtype' => 'PluginPreventivemaintenancePreventivemaintenance',
         'label'    => __('Manutenção Preventiva'),
         'field'    => 'plugin_preventivemaintenance',
         'rights'   => [
             READ    => __('Read'),
             CREATE  => __('Create'),
             UPDATE  => __('Update'),
             DELETE  => __('Delete'),
             PURGE   => __('Purge')
         ]]
    ];
}
