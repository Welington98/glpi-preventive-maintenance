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

// Verifica se o arquivo está sendo acessado diretamente (segurança GLPI)
// Check if file is being accessed directly (GLPI security)
if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

/**
 * Classe principal do plugin que estende CommonDBTM para gerenciar manutenções preventivas
 * Main plugin class extending CommonDBTM to manage preventive maintenances
 */
class PluginPreventivemaintenancePreventivemaintenance extends CommonDBTM {

   // Ativa histórico de alterações
   // Enable change history
   public $dohistory = true;
   
   // Nome do direito de acesso
   // Access right name
   static $rightname = 'plugin_preventivemaintenance';

   // Tipos de item nativos sempre disponíveis para vínculo
   // Native item types always available for linking
   const BASE_ITEMTYPES = ['Computer', 'Monitor', 'Printer', 'NetworkEquipment', 'Peripheral', 'Phone'];

   /**
    * Retorna os tipos de item permitidos para vínculo: a lista fixa de ativos
    * nativos + qualquer Asset Definition personalizada ativa (GLPI 10.1+/11).
    * Returns the item types allowed for linking: the fixed list of native
    * assets + any active custom Asset Definition (GLPI 10.1+/11).
    */
   public static function getAllowedItemtypes() {
      $itemtypes = self::BASE_ITEMTYPES;

      if (class_exists(\Glpi\Asset\AssetDefinitionManager::class)) {
         $manager = \Glpi\Asset\AssetDefinitionManager::getInstance();
         foreach ($manager->getDefinitions(true) as $definition) {
            $itemtypes[] = $definition->getAssetClassName(true);
         }
      }

      return $itemtypes;
   }

   /**
    * Opções de intervalo de recorrência automática, em meses.
    * Automatic recurrence interval options, in months.
    */
   public static function getRecurrenceMonthOptions() {
      return [
         1  => __('1 mês'),
         2  => __('2 meses'),
         3  => __('3 meses'),
         6  => __('6 meses'),
         12 => __('1 ano'),
         18 => __('18 meses'),
         24 => __('2 anos'),
         36 => __('3 anos'),
      ];
   }

   /**
    * Retorna o nome do tipo do item (singular/plural)
    * Returns the type name of the item (singular/plural)
    */
   static function getTypeName($nb = 0) {
      return _n('Manutenção Preventiva', 'Manutenções Preventivas', $nb, 'preventivemaintenance');
   }

   /**
    * Retorna o nome da tabela no banco de dados
    * Returns the database table name
    */
   static function getTable($classname = null) {
      return 'glpi_plugin_preventivemaintenance_preventivemaintenances';
   }

   /**
    * Prepara os dados antes da exclusão (validações de segurança)
    * Prepares data before deletion (security validations)
    */
   public function prepareInputForDelete($input) {
    // Verificação adicional de segurança
    // Additional security check
    if (!isset($input['id'])) {
        Session::addMessageAfterRedirect(
            __('ID do registro não especificado'),
            false,
            ERROR
        );
        return false;
    }
    
    // Verifica se o registro existe
    // Check if record exists
    if (!$this->getFromDB($input['id'])) {
        Session::addMessageAfterRedirect(
            __('Registro não encontrado'),
            false,
            ERROR
        );
        return false;
    }
    
    return $input;
   }

   /**
    * Retorna a URL do formulário do item
    * Returns the item form URL
    */
   public static function getItemTypeFormURL($full = true) {
    global $CFG_GLPI;
    return ($full ? $CFG_GLPI['root_doc'] : '') . 
           '/plugins/preventivemaintenance/front/preventivemaintenance.form.php';
   }

   /**
    * Retorna o nome do item (usado para exibição)
    * Returns the item name (used for display)
    */
   function getName($options = []) {
      return $this->fields['comment'];
   }

   /**
    * Verifica permissão de criação
    * Checks create permission
    */
   static function canCreate(): bool {
      return Session::haveRight(self::$rightname, CREATE);
   }

   /**
    * Verifica permissão de visualização
    * Checks view permission
    */
   static function canView(): bool {
      return Session::haveRight(self::$rightname, READ);
   }

   /**
    * Verifica permissão de atualização
    * Checks update permission
    */
   static function canUpdate(): bool {
      return Session::haveRight(self::$rightname, UPDATE);
   }

   /**
    * Verifica permissão de exclusão (com tratamento especial para Super-Admin)
    * Checks delete permission (with special handling for Super-Admin)
    */
   static function canDelete(): bool {
    // Permissão sempre verdadeira para Super-Admin
    // Always true for Super-Admin
    if (isset($_SESSION['glpiactiveprofile']['name']) &&
        $_SESSION['glpiactiveprofile']['name'] == 'Super-Admin') {
        return true;
    }
    return Session::haveRight(self::$rightname, DELETE);
   }

   /**
    * Exibe o formulário de cadastro/edição
    * Displays the add/edit form
    */
   public function showForm($ID, $options = []) {
      $this->initForm($ID, $options);
      $this->showFormHeader($options);

    // Campo de seleção do tipo de item + item vinculado
    // Item type + linked item selection field
    // NOTE: verificar em uma instância GLPI real os parâmetros exatos aceitos por
    // Dropdown::showSelectItemFromItemtypes() para a versão instalada (10.x/11.x).
    // NOTE: verify the exact parameters accepted by Dropdown::showSelectItemFromItemtypes()
    // against a real GLPI instance for the installed version (10.x/11.x).
    echo "<tr class='tab_bg_1'><td>".__('Item')."</td><td>";
    Dropdown::showSelectItemFromItemtypes([
        'itemtype'        => $this->fields['itemtype'] ?? 'Computer',
        'items_id'        => $this->fields['items_id'] ?? 0,
        'itemtypes'       => self::getAllowedItemtypes(),
        'entity_restrict' => $this->fields['entities_id'] ?? $_SESSION['glpiactive_entity'],
    ]);
    echo "</td></tr>";

    // Campo de nome/comentário
    // Name/comment field
    echo "<tr class='tab_bg_1'><td>" . __('Nome') . "</td><td><input type='text' name='comment' value='" . $this->fields['comment'] . "'></td></tr>";

    // Campo de data da última manutenção
    // Last maintenance date field
    echo "<tr class='tab_bg_1'><td>" . __('Última Manutenção') . "</td><td>";
    Html::showDateField("last_maintenance_date", ['value' => $this->fields['last_maintenance_date'] ?? '']);
    echo "</td></tr>";

    // Campo de data da próxima manutenção
    // Next maintenance date field
    echo "<tr class='tab_bg_1'><td>" . __('Próxima Manutenção') . "</td><td>";
    Html::showDateField("next_maintenance_date", ['value' => $this->fields['next_maintenance_date'] ?? '']);
    echo "</td></tr>";

    // Campo de seleção do técnico responsável
    // Technician selection field
    echo "<tr class='tab_bg_1'><td>" . __('Técnico Responsável') . "</td><td>";
    User::dropdown([
       'name'  => 'technician_id',
       'value' => $this->fields['technician_id'] ?? 0
    ]);
    echo "</td></tr>";

    // Campo de seleção do grupo responsável (mais estável que o técnico, que
    // pode mudar com o tempo — pode ser usado no lugar do técnico ou junto).
    // Responsible group selection field (more stable than the technician, who
    // can change over time — can be used instead of or alongside the technician).
    echo "<tr class='tab_bg_1'><td>" . __('Grupo Responsável') . "</td><td>";
    Group::dropdown([
       'name'   => 'groups_id',
       'value'  => $this->fields['groups_id'] ?? 0,
       'entity' => $this->fields['entities_id'] ?? $_SESSION['glpiactive_entity'],
    ]);
    echo "</td></tr>";

    // Campo de recorrência automática (por calendário, independe da resolução do chamado)
    // Automatic recurrence field (calendar-based, independent of ticket resolution)
    echo "<tr class='tab_bg_1'><td>" . __('Recorrência Automática') . "</td><td>";
    Html::showCheckbox([
        'name'    => 'is_recurring',
        'checked' => !empty($this->fields['is_recurring']),
    ]);
    echo "&nbsp;";
    Dropdown::showFromArray('recurrence_months', self::getRecurrenceMonthOptions(), [
        'value' => $this->fields['recurrence_months'] ?? 3,
    ]);
    echo "</td></tr>";

    // Campo de seleção do modelo de chamado
    // Ticket template selection field
    echo "<tr class='tab_bg_1'><td>" . __('Modelo de Chamado') . "</td><td>";
    TicketTemplate::dropdown([
        'name'   => 'tickettemplates_id',
        'value'  => $this->fields['tickettemplates_id'] ?? 0,
        'entity' => $this->fields['entities_id'] ?? $_SESSION['glpiactive_entity'],
    ]);
    echo "</td></tr>";

    // Botões do formulário
    // Form buttons
    $this->showFormButtons($options);
    return true;
   }
   
   /**
    * Exibe valores específicos para determinados campos (especialmente o status)
    * Displays specific values for certain fields (especially status)
    */
   public static function getSpecificValueToDisplay($field, $values, array $options = []) {
    if ($field == 'items_id') {
        // Resolve o nome do item genericamente a partir do itemtype
        // Generically resolves the item's name from its itemtype
        if (empty($values['items_id']) || empty($values['itemtype'])) {
            return '';
        }
        $itemtype = $values['itemtype'];
        return Dropdown::getDropdownName($itemtype::getTable(), $values['items_id']);
    }
    if ($field == 'status') {
        // Verifica se há item vinculado
        // Checks if there's a linked item
        if (empty($values['items_id'])) {
            return '<span class="state_undefined"><i class="fas fa-question-circle"></i> '.__('No Item Linked').'</span>';
        }

        // Verifica se tem datas válidas
        // Checks for valid dates
        if (empty($values['last_maintenance_date']) || empty($values['next_maintenance_date'])) {
            return '<span class="state_pending"><i class="fas fa-clock"></i> '.__('Not Scheduled').'</span>';
        }

        $last = strtotime($values['last_maintenance_date']);
        $next = strtotime($values['next_maintenance_date']);
        $now = time();

        // Cálculo do tempo restante
        // Days remaining calculation
        $days_remaining = round(($next - $now) / (60 * 60 * 24));
        
        // Determinar o estado
        // Determine status
        if ($days_remaining < 0) {
            return '<span class="state_overdue"><i class="fas fa-exclamation-triangle"></i> '.__('Overdue').' ('.abs($days_remaining).' '.__('days').')</span>';
        } elseif ($days_remaining <= 7) {
            return '<span class="state_due_soon"><i class="fas fa-clock"></i> '.__('Due Soon').' ('.$days_remaining.' '.__('days').')</span>';
        } else {
            return '<span class="state_ok"><i class="fas fa-check-circle"></i> '.__('On Track').' ('.$days_remaining.' '.__('days').')</span>';
        }
    }
    return parent::getSpecificValueToDisplay($field, $values, $options);
   }
   
   /**
    * Valida os dados antes de adicionar um novo registro
    * Validates data before adding a new record
    */
   public function prepareInputForAdd($input) {
    // Validação obrigatória do tipo e do item vinculado
    // Required validation of the item type and the linked item
    if (!$this->validateItemtypeAndItem($input)) {
        return false;
    }

    // Validação das datas
    // Date validation
    if (!empty($input['last_maintenance_date']) &&
        !empty($input['next_maintenance_date']) &&
        $input['next_maintenance_date'] <= $input['last_maintenance_date']) {
        Session::addMessageAfterRedirect(
            __('Next maintenance date must be after last maintenance date', 'preventivemaintenance'),
            false,
            ERROR
        );
        return false;
    }

    // Definir valores padrão
    // Set default values
    $input['entities_id'] = $_SESSION['glpiactive_entity'] ?? 0;
    $input['is_recursive'] = 0;
    // Checkbox não envia nada quando desmarcado; normaliza para 0/1.
    // Checkbox sends nothing when unchecked; normalize to 0/1.
    $input['is_recurring'] = !empty($input['is_recurring']) ? 1 : 0;
    $input['tickettemplates_id'] = (int)($input['tickettemplates_id'] ?? 0);
    $input['recurrence_months'] = self::normalizeRecurrenceMonths($input['recurrence_months'] ?? null);

    return $input;
   }

   /**
    * Normaliza o valor de recorrência: aceita tanto uma das opções fixas
    * quanto um valor personalizado (qualquer inteiro positivo, até 120 meses).
    * Normalizes the recurrence value: accepts either one of the fixed options
    * or a custom value (any positive integer, up to 120 months).
    */
   private static function normalizeRecurrenceMonths($value) {
      $months = (int)$value;
      if ($months < 1) {
         return 3;
      }
      return min($months, 120);
   }

   /**
    * Valida se o registro será atualizado com um tipo/item válido
    * Validates that the record will be updated with a valid type/item
    */
   public function prepareInputForUpdate($input) {
    if (isset($input['itemtype']) || isset($input['items_id'])) {
        if (!$this->validateItemtypeAndItem($input)) {
            return false;
        }
    }
    if (array_key_exists('is_recurring', $input)) {
        $input['is_recurring'] = !empty($input['is_recurring']) ? 1 : 0;
    }
    if (array_key_exists('tickettemplates_id', $input)) {
        $input['tickettemplates_id'] = (int)$input['tickettemplates_id'];
    }
    if (array_key_exists('recurrence_months', $input)) {
        $input['recurrence_months'] = self::normalizeRecurrenceMonths($input['recurrence_months']);
    }
    return $input;
   }

   /**
    * Valida o itemtype (contra a whitelist) e a existência do item informado.
    * Nunca instancia uma classe a partir de input não validado.
    * Validates itemtype (against the whitelist) and that the given item exists.
    * Never instantiates a class from unvalidated input.
    */
   private function validateItemtypeAndItem($input) {
    if (empty($input['itemtype']) || !in_array($input['itemtype'], self::getAllowedItemtypes(), true)) {
        Session::addMessageAfterRedirect(
            __('Invalid item type selected', 'preventivemaintenance'),
            false,
            ERROR
        );
        return false;
    }

    if (empty($input['items_id']) || (int)$input['items_id'] <= 0) {
        Session::addMessageAfterRedirect(
            __('You must select a valid item', 'preventivemaintenance'),
            false,
            ERROR
        );
        return false;
    }

    $itemtype = $input['itemtype'];
    $item = new $itemtype();
    if (!$item->getFromDB((int)$input['items_id'])) {
        Session::addMessageAfterRedirect(
            __('Selected item was not found', 'preventivemaintenance'),
            false,
            ERROR
        );
        return false;
    }

    return true;
   }

   /**
    * Ajustes após carregar dados do banco
    * Adjustments after loading data from database
    */
   public function post_getFromDB() {
    // Corrige visualização de registros antigos
    // Fixes display for old records
    if ($this->fields['items_id'] == 0) {
        $this->fields['items_id'] = '';
    }
    if ($this->fields['last_maintenance_date'] == '0000-00-00') {
        $this->fields['last_maintenance_date'] = '';
    }
    if ($this->fields['next_maintenance_date'] == '0000-00-00') {
        $this->fields['next_maintenance_date'] = '';
    }
   }

   /**
    * Define as opções de busca/pesquisa
    * Defines search options
    */
   public function getSearchOptionsNew() {
    $options = parent::getSearchOptionsNew();

    // Opção para exibir o item vinculado (qualquer itemtype permitido)
    // Option to display the linked item (any allowed itemtype)
    // NOTE: não é ordenável/filtrável via SQL, pois o item pode estar em tabelas
    // diferentes conforme o itemtype — só exibição via getSpecificValueToDisplay().
    // NOTE: not SQL-sortable/filterable, since the item can live in different
    // tables depending on itemtype — display only, via getSpecificValueToDisplay().
    $options[] = [
        'id'                 => '2',
        'name'               => __('Item'),
        'field'              => 'items_id',
        'nosearch'           => true,
        'massiveaction'      => false,
        'datatype'           => 'specific',
        'additionalfields'   => ['itemtype', 'items_id']
    ];

    // Opção para buscar por técnico
    // Option to search by technician
    $options[] = [
        'id'                 => '3',
        'table'              => 'glpi_users',
        'field'              => 'name',
        'name'               => __('Technician'),
        'datatype'           => 'dropdown',
        'forcegroupby'       => true,
        'massiveaction'      => false,
        'joinparams'         => [
            'beforejoin' => [
                'table'      => 'glpi_plugin_preventivemaintenance_preventivemaintenances',
                'joinparams' => [
                    'jointype' => 'itemtype_item'
                ]
            ]
        ]
    ];

    // Opção para buscar por data da última manutenção
    // Option to search by last maintenance date
    $options[] = [
        'id'                 => '4',
        'table'              => 'glpi_plugin_preventivemaintenance_preventivemaintenances',
        'field'              => 'last_maintenance_date',
        'name'               => __('Last Maintenance'),
        'datatype'           => 'date'
    ];

    // Opção para buscar por data da próxima manutenção
    // Option to search by next maintenance date
    $options[] = [
        'id'                 => '5',
        'table'              => 'glpi_plugin_preventivemaintenance_preventivemaintenances',
        'field'              => 'next_maintenance_date',
        'name'               => __('Next Maintenance'),
        'datatype'           => 'date',
        'searchtype'         => 'equals'
    ];

    // Opção para mostrar o status (calculado)
    // Option to show status (calculated)
    $options[] = [
        'id'                 => '100',
        'name'               => __('Status'),
        'field'              => 'status',
        'nosearch'           => true,
        'nodisplay'          => false,
        'datatype'           => 'specific',
        'additionalfields'   => ['items_id', 'last_maintenance_date', 'next_maintenance_date']
    ];

    return $options;
   }

   /**
    * Processa resultados de busca personalizados (especialmente a barra de status)
    * Processes custom search results (especially the status bar)
    */
   public function getSearchResultNew($field, $values, $options = []) {
      if ($field === 'status_bar') {
         $last = strtotime($this->fields['last_maintenance_date']);
         $next = strtotime($this->fields['next_maintenance_date']);
         $now  = time();

         if (!$last || !$next || $next <= $last) {
            return '⚠️';
         }

         $percent = round(($now - $last) / ($next - $last) * 100);
         $percent = max(0, min(100, $percent));

         $color = 'green';
         if ($percent >= 95) $color = 'orange';
         if ($percent >= 99) $color = 'red';

         return "<div style='width:100%; background:#eee; border-radius:4px; height:15px;'>
                  <div style='width:{$percent}%; background:{$color}; height:100%; border-radius:4px;'></div>
                 </div>";
      }

      return parent::getSearchResultNew($field, $values, $options);
      error_log("getSearchResult: $field");
   }
   
}