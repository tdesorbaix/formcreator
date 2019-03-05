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

class PluginFormcreatorFields extends CommonTestCase {

   public function answersProvider() {
      return [
         'no condition' => [
            'always',
            [
               'show_logic' => [],
               'show_field'   => [],
               'show_condition'  => [],
               'show_value' => [],
            ],
            [],
            true,
         ],
         'simple condition' => [
            'hidden',
            [
               'show_logic' => [
                  'OR',
               ],
               'show_field'   => [
                  0,
               ],
               'show_condition'  => [
                  '==',
               ],
               'show_value' => [
                  'foo',
               ],
            ],
            [
               0 => 'foo',
            ],
            true,
         ],
         'failed condition' => [
            'hidden',
            [
               'show_logic' => [
                  'OR',
               ],
               'show_field'   => [
                  0,
               ],
               'show_condition'  => [
                  '==',
               ],
               'show_value' => [
                  'bar',
               ],
            ],
            [
                  0 => 'foo',
            ],
            false,
         ],
         'multiple condition OR' => [
            'hidden',
            [
               'show_logic' => [
                  'OR',
                  'OR',
               ],
               'show_field'   => [
                  0,
                  1,
               ],
               'show_condition'  => [
                  '==',
                  '==',
               ],
               'show_value' => [
                  'val1',
                  'val2',
               ],
            ],
            [
                  0 => 'val1',
                  1 => 'val2',
            ],
            true,
         ],
         'failed multiple condition OR' => [
            'hidden',
            [
               'show_logic' => [
                  'OR',
                  'OR',
               ],
               'show_field'   => [
                  0,
                  1,
               ],
               'show_condition'  => [
                  '==',
                  '==',
               ],
               'show_value' => [
                  'val1',
                  'val2',
               ],
            ],
            [
               0 => 'val1',
               1 => 'not val2',
            ],
            true,
         ],
         'multiple condition AND' => [
            'hidden',
            [
                  'show_logic' => [
                        'OR',
                        'AND',
                  ],
                  'show_field'   => [
                        0,
                        1,
                  ],
                  'show_condition'  => [
                        '==',
                        '==',
                  ],
                  'show_value' => [
                        'val1',
                        'val2',
                  ],
            ],
            [
                  0 => 'val1',
                  1 => 'val2',
            ],
            true,
         ],
         'failed multiple condition AND' => [
            'hidden',
            [
               'show_logic' => [
                  'OR',
                  'AND',
               ],
               'show_field'   => [
                  0,
                  1,
               ],
               'show_condition'  => [
                  '==',
                  '==',
               ],
               'show_value' => [
                  'val1',
                  'val2',
               ],
            ],
            [
               0 => 'val1',
               1 => 'not val2',
            ],
            false,
         ],
         'operator priority' => [
            'hidden',
            [
               'show_logic' => [
                  'OR',
                  'AND',
                  'OR',
                  'AND',
               ],
               'show_field'   => [
                  0,
                  1,
                  2,
                  3,
               ],
               'show_condition'  => [
                  '==',
                  '==',
                  '==',
                  '==',
               ],
               'show_value' => [
                  'val1',
                  'val2',
                  'val3',
                  'val4',
               ],
            ],
            [
               0 => 'val1',
               1 => 'val2',
               2 => 'val8',
               3 => 'val9',
            ],
            true,
         ],
      ];
   }

   /**
    * @dataProvider answersProvider
    */
   public function testIsVisible($show_rule, $conditions, $answers, $expectedVisibility) {
      // Create section
      $section = $this->getSection();
      $this->boolean($section->isNewItem())->isFalse();

      // Create a question
      $question = $this->getQuestion([
         'name'                           => 'text question',
         'fieldtype'                      => 'text',
         'plugin_formcreator_sections_id' => $section->getID(),
      ]);
      $this->boolean($question->isNewItem())->isFalse();

      $questionPool = [];
      for ($i = 0; $i < 4; $i++) {
         $item = $this->getQuestion([
            'fieldtype'                      => 'text',
            'name'                           => "question $i",
            'plugin_formcreator_sections_id' => $section->getID(),
         ]);
         $questionPool[$i] = $item;
      }

      foreach ($conditions['show_field'] as $id => &$showField) {
         $showField = $questionPool[$showField]->getID();
      }
      $realAnswers = [];
      foreach ($answers as $id => $answer) {
         $realAnswers[$questionPool[$id]->getID()] = \PluginFormcreatorFields::getFieldInstance(
            $questionPool[$id]->fields['fieldtype'], $questionPool[$id]
         );
         $realAnswers[$questionPool[$id]->getID()]->deserializeValue($answer);
      }
      $input = $conditions + [
         'id'        => $question->getID(),
         'fieldtype' => 'text',
         'show_rule' => $show_rule,
         'default_values' => '',
         '_parameters'     => [
            'text' => [
               'range' => [
                  'range_min' => '',
                  'range_max' => '',
               ],
               'regex' => [
                  'regex' => ''
               ]
            ]
         ]
      ];
      $question->update($input);
      $question->updateConditions($input);
      $isVisible = \PluginFormcreatorFields::isVisible($question->getID(), $realAnswers);
      $this->boolean((boolean) $isVisible)->isEqualTo($expectedVisibility);
   }

   public function testGetFieldClassname() {
      $output = \PluginFormcreatorFields::getFieldClassname('dummy');
      $this->string($output)->isEqualTo('PluginFormcreatorDummyField');
   }

   public function testFieldTypeExists() {
      $output = \PluginFormcreatorFields::fieldTypeExists('dummy');
      $this->boolean($output)->isFalse();
      $output = \PluginFormcreatorFields::fieldTypeExists('textarea');
      $this->boolean($output)->isTrue();
   }

   public function testUpdateVisibility() {
      $question1 = $this->getQuestion();
      $question2 = $this->getQuestion([
         'plugin_formcreator_sections_id' => $question1->fields['plugin_formcreator_sections_id'],
      ]);

      $form = new \PluginFormcreatorForm();
      $section = new \PluginFormcreatorSection();
      $section->getFromDB($question1->fields['plugin_formcreator_sections_id']);
      $form->getFromDBBySection($section);
      $input = [
         'formcreator_form' => $form->getID(),
         $question1->getID() => '',
         $question2->getID() => '',
      ];
      $output = \PluginFormcreatorFields::updateVisibility($input);
      $this->array($output)->isIdenticalTo([
         $question1->getID() => true,
         $question2->getID() => true,
      ]);
   }
}
