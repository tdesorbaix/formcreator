<?php
/**
 * ---------------------------------------------------------------------
 * Formcreator is a plugin which allows creation of custom forms of
 * easy access.
 * ---------------------------------------------------------------------
 * LICENSE
 *
 * This file is part of Formcreator.
 *
 * Formcreator is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Formcreator is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Formcreator. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 * @copyright Copyright © 2011 - 2019 Teclib'
 * @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
 * @link      https://github.com/pluginsGLPI/formcreator/
 * @link      https://pluginsglpi.github.io/formcreator/
 * @link      http://plugins.glpi-project.org/#/plugin/formcreator
 * ---------------------------------------------------------------------
 */
namespace tests\units;
use GlpiPlugin\Formcreator\Tests\CommonTestCase;

class PluginFormcreatorSection extends CommonTestCase {

   private $form = null;

   private $sectionData = null;

   public function beforeTestMethod($method) {
      parent::beforeTestMethod($method);

      switch ($method) {
         case 'testAdd':
         case 'testUpdate':
         case 'testDelete':
            $this->login('glpi', 'glpi');
            $this->form = new \PluginFormcreatorForm();
            $this->form->add([
               'entities_id'           => $_SESSION['glpiactive_entity'],
               'name'                  => $method . ' ' . $this->getUniqueString(),
               'description'           => 'form description',
               'content'               => 'a content',
               'is_active'             => 1,
               'validation_required'   => 0
            ]);
            break;

         case 'testDuplicate':
            $this->login('glpi', 'glpi');
            break;
      }
   }

   public function testAdd() {
      $instance = new \PluginFormcreatorSection();
      $instance->add([
         'plugin_formcreator_forms_id' => $this->form->getID(),
         'name'                        => $this->getUniqueString()
      ]);
      $this->boolean($instance->isNewItem())->isFalse();
   }

   public function testUpdate() {
      $instance = new \PluginFormcreatorSection();
      $instance->add([
         'plugin_formcreator_forms_id' => $this->form->getID(),
         'name'                        => $this->getUniqueString()
      ]);
      $this->boolean($instance->isNewItem())->isFalse();

      $success = $instance->update([
            'id'     => $instance->getID(),
            'name'   => 'section renamed'
      ]);
      $this->boolean($success)->isTrue();
   }

   public function testDelete() {
      $instance = new \PluginFormcreatorSection();
      $instance->add([
         'plugin_formcreator_forms_id' => $this->form->getID(),
         'name'                        => $this->getUniqueString()
      ]);
      $this->boolean($instance->isNewItem())->isFalse();

      $success = $instance->delete([
            'id' => $instance->getID()
      ], 1);
      $this->boolean($success)->isTrue();
   }

   /**
    *
    */
   public function testDuplicate() {
      global $DB;

      $form = $this->getForm();

      $section = new \PluginFormcreatorSection();
      $question = new \PluginFormcreatorQuestion();
      $sections_id = $section->add(['name'                        => "test clone section",
                                    'plugin_formcreator_forms_id' => $form->getID()]);

      $question->add(['name'                           => "test clone question 1",
                                        'fieldtype'                      => 'text',
                                        'plugin_formcreator_sections_id' => $sections_id,
                                        'default_values' => '',
                                        '_parameters' => [
                                           'text' => [
                                           'regex' => ['regex' => ''],
                                           'range' => ['min' => '', 'max' => ''],
                                           ]
                                         ],
                                        ]);
      $question->add(['name'                           => "test clone question 2",
                                        'fieldtype'                      => 'textarea',
                                        'plugin_formcreator_sections_id' => $sections_id
                                        ]);

      //clone it
      $this->integer($section->duplicate());

      //get cloned section
      $new_section   = new \PluginFormcreatorSection;
      $new_section->getFromDBByCrit([
         'AND' => [
            'name'                        => 'test clone section',
            'NOT'                         => ['uuid' => $section->getField('uuid')], // operator <> available in GLPI 9.3+ only
            'plugin_formcreator_forms_id' => $section->getField('plugin_formcreator_forms_id')
         ]
      ]);
      $this->boolean($new_section->isNewItem())->isFalse();

      // check questions
      $all_questions = $DB->request([
         'SELECT' => ['uuid'],
         'FROM'  => $question::getTable(),
         'WHERE' => [
            'plugin_formcreator_sections_id' => $section->getID(),
         ]
      ]);
      $all_new_questions = $DB->request([
         'SELECT' => ['uuid'],
         'FROM'  => $question::getTable(),
         'WHERE' => [
            'plugin_formcreator_sections_id' => $new_section->getID(),
         ]
      ]);
      $this->integer(count($all_new_questions))->isEqualTo(count($all_questions));

      // check that all question uuid are new
      $uuids = $new_uuids = [];
      foreach ($all_questions as $question) {
         $uuids[] = $question['uuid'];
      }
      foreach ($all_new_questions as $question) {
         $new_uuids[] = $question['uuid'];
      }
      $this->integer(count(array_diff($new_uuids, $uuids)))->isEqualTo(count($new_uuids));
   }
}
