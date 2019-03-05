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

class PluginFormcreatorFloatField extends CommonTestCase {

   public function provider() {
      $dataset = [
         [
            'fields'          => [
               'fieldtype'       => 'float',
               'name'            => 'question',
               'required'        => '0',
               'default_values'  => '',
               'order'           => '1',
               'show_rule'       =>\PluginFormcreatorQuestion::SHOW_RULE_ALWAYS,
               'show_empty'      => '0',
               'values'          => '',
               '_parameters'     => [
                  'float' => [
                     'range' => [
                        'range_min'       => '',
                        'range_max'       => '',
                     ],
                     'regex' => ['regex' => ''],
                  ]
               ]
            ],
            'data'            => null,
            'expectedValue'   => '',
            'expectedIsValid' => true
         ],
         [
            'fields'          => [
               'fieldtype'       => 'float',
               'name'            => 'question',
               'required'        => '0',
               'default_values'  => '2',
               'order'           => '1',
               'show_rule'       =>\PluginFormcreatorQuestion::SHOW_RULE_ALWAYS,
               'show_empty'      => '0',
               'values'          => '',
               '_parameters'     => [
                  'float' => [
                     'range' => [
                        'range_min'       => '',
                        'range_max'       => '',
                     ],
                     'regex' => ['regex' => ''],
                  ]
               ]
            ],
            'data'            => null,
            'expectedValue'   => '2',
            'expectedIsValid' => true
         ],
         [
            'fields'          => [
               'fieldtype'       => 'float',
               'name'            => 'question',
               'required'        => '0',
               'default_values'  => "2",
               'order'           => '1',
               'show_rule'       =>\PluginFormcreatorQuestion::SHOW_RULE_ALWAYS,
               'show_empty'      => '0',
               '_parameters'     => [
                  'float' => [
                     'range' => [
                        'range_min'       => 3,
                        'range_max'       => 4,
                     ],
                     'regex' => ['regex' => ''],
                  ]
               ]
            ],
            'data'            => null,
            'expectedValue'   => '2',
            'expectedIsValid' => false
         ],
         [
            'fields'          => [
               'fieldtype'       => 'float',
               'name'            => 'question',
               'required'        => '0',
               'default_values'  => "5",
               'order'           => '1',
               'show_rule'       =>\PluginFormcreatorQuestion::SHOW_RULE_ALWAYS,
               'show_empty'      => '0',
               'values'          => '',
               '_parameters'     => [
                  'float' => [
                     'range' => [
                        'range_min'       => 3,
                        'range_max'       => 4,
                     ],
                     'regex' => ['regex' => ''],
                  ]
               ]
            ],
            'data'            => null,
            'expectedValue'   => '5',
            'expectedIsValid' => false
         ],
         [
            'fields'          => [
               'fieldtype'       => 'float',
               'name'            => 'question',
               'required'        => '0',
               'default_values'  => "3.141592",
               'order'           => '1',
               'show_rule'       =>\PluginFormcreatorQuestion::SHOW_RULE_ALWAYS,
               'show_empty'      => '0',
               'values'          => '',
               '_parameters'     => [
                  'float' => [
                     'range' => [
                        'range_min'       => 3,
                        'range_max'       => 4,
                     ],
                     'regex' => ['regex' => ''],
                  ]
               ]
            ],
            'data'            => null,
            'expectedValue'   => '3.141592',
            'expectedIsValid' => true
         ],
      ];

      return $dataset;
   }

   /**
    * @dataProvider provider
    */
   public function testIsValid($fields, $data, $expectedValue, $expectedValidity) {
      $section = $this->getSection();
      $fields[$section::getForeignKeyField()] = $section->getID();

      $question = new \PluginFormcreatorQuestion();
      $question->add($fields);
      $this->boolean($question->isNewItem())->isFalse(json_encode($_SESSION['MESSAGE_AFTER_REDIRECT'], JSON_PRETTY_PRINT));
      $question->updateParameters($fields);

      $instance = new \PluginFormcreatorFloatField($question->fields, $data);
      $instance->deserializeValue($fields['default_values']);
      $isValid = $instance->isValid();
      $this->boolean((boolean) $isValid)->isEqualTo($expectedValidity);
   }

   public function testGetEmptyParameters() {
      $instance = $this->newTestedInstance([]);
      $output = $instance->getEmptyParameters();
      $this->array($output)
         ->hasKey('range')
         ->hasKey('regex')
         ->array($output)->size->isEqualTo(2);
      $this->object($output['range'])
         ->isInstanceOf(\PluginFormcreatorQuestionRange::class);
      $this->object($output['regex'])
         ->isInstanceOf(\PluginFormcreatorQuestionRegex::class);
   }

   public function testIsAnonymousFormCompatible() {
      $instance = new \PluginFormcreatorFloatField([]);
      $output = $instance->isAnonymousFormCompatible();
      $this->boolean($output)->isTrue();
   }

   public function testIsPrerequisites() {
      $instance = $this->newTestedInstance([]);
      $output = $instance->isPrerequisites();
      $this->boolean($output)->isEqualTo(true);
   }
}
