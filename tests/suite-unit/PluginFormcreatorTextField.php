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

class PluginFormcreatorTextField extends CommonTestCase {

   public function provider() {
      $dataset = [
         [
            'fields'          => [
               'fieldtype'       => 'text',
               'name'            => 'question',
               'required'        => '0',
               'show_empty'      => '0',
               'default_values'  => '',
               'values'          => "",
               'order'           => '1',
               'show_rule'       => 'always',
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
               ],
            ],
            'data'            => null,
            'expectedValue'   => '1',
            'expectedIsValid' => true
         ],
         [
            'fields'          => [
               'fieldtype'       => 'text',
               'name'            => 'question',
               'required'        => '0',
               'show_empty'      => '0',
               'default_values'  => 'a',
               'values'          => "",
               'order'           => '1',
               'show_rule'       => 'always',
               '_parameters'     => [
                  'text' => [
                     'range' => [
                        'range_min' => '5',
                        'range_max' => '8',
                     ],
                     'regex' => [
                        'regex' => ''
                     ]
                  ]
               ],
            ],
            'data'            => null,
            'expectedValue'   => '1',
            'expectedIsValid' => false
         ],
         [
            'fields'          => [
               'fieldtype'       => 'text',
               'name'            => 'question',
               'required'        => '0',
               'show_empty'      => '0',
               'default_values'  => 'short',
               'values'          => "",
               'order'           => '1',
               'show_rule'       => 'always',
               '_parameters'     => [
                  'text' => [
                     'range' => [
                        'range_min' => '6',
                        'range_max' => '8',
                     ],
                     'regex' => [
                        'regex' => ''
                     ]
                  ]
               ],
            ],
            'data'            => null,
            'expectedValue'   => '1',
            'expectedIsValid' => false
         ],
         [
            'fields'          => [
               'fieldtype'       => 'text',
               'name'            => 'question',
               'required'        => '0',
               'show_empty'      => '0',
               'default_values'  => 'very long',
               'values'          => "",
               'order'           => '1',
               'show_rule'       => 'always',
               '_parameters'     => [
                  'text' => [
                     'range' => [
                        'range_min' => '6',
                        'range_max' => '8',
                     ],
                     'regex' => [
                        'regex' => ''
                     ]
                  ]
               ],
            ],
            'data'            => null,
            'expectedValue'   => '1',
            'expectedIsValid' => false
         ],
         [
            'fields'          => [
               'fieldtype'       => 'text',
               'name'            => 'question',
               'required'        => '0',
               'show_empty'      => '0',
               'default_values'  => 'very long',
               'values'          => "",
               'order'           => '1',
               'show_rule'       => 'good',
               '_parameters'     => [
                  'text' => [
                     'range' => [
                        'range_min' => '3',
                        'range_max' => '8',
                     ],
                     'regex' => [
                        'regex' => ''
                     ]
                  ]
               ],
            ],
            'data'            => null,
            'expectedValue'   => '1',
            'expectedIsValid' => false
         ],
      ];
      return $dataset;
   }

   /**
    * @dataProvider provider
    */
   public function testFieldIsValid($fields, $data, $expectedValue, $expectedValidity) {
      $section = $this->getSection();
      $fields[$section::getForeignKeyField()] = $section->getID();

      $question = new \PluginFormcreatorQuestion();
      $question->add($fields);
      $question->updateParameters($fields);

      $fieldInstance = new \PluginFormcreatorTextField($question->fields, $data);

      $isValid = $fieldInstance->isValid($fields['default_values']);
      $this->boolean($isValid)->isEqualTo($expectedValidity, json_encode($_SESSION['MESSAGE_AFTER_REDIRECT'], JSON_PRETTY_PRINT));
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

   public function testGetName() {
      $output = \PluginFormcreatorTextField::getName();
      $this->string($output)->isEqualTo('Text');
   }

   public function testIsAnonymousFormCompatible() {
      $instance = new \PluginFormcreatorTextField([]);
      $output = $instance->isAnonymousFormCompatible();
      $this->boolean($output)->isTrue();
   }

   public function providerSerializeValue() {
      return [
         [
            'value' => '',
            'expected' => '',
         ],
         [
            'value' => "quote ' test",
            'expected' => "quote \' test",
         ],
      ];
   }

   /**
    * @dataProvider providerSerializeValue
    */
   public function testSerializeValue($value, $expected) {
      $instance = new \PluginFormcreatorTextField([]);
      $instance->prepareQuestionInputForSave([
         'default_values' => $value,
      ]);
      $output = $instance->serializeValue();
      $this->string($output)->isEqualTo($expected);
   }

   public function providerDeserializeValue() {
      return [
         [
            'value'     => '',
            'expected'  => '',
         ],
         [
            'value'     => 'foo',
            'expected'  => 'foo' ,
         ],
      ];
   }

   /**
    * @dataProvider providerDeserializeValue
    */
   public function testDeserializeValue($value, $expected) {
      $instance = new \PluginFormcreatorTextField([]);
      $instance->deserializeValue($value);
      $output = $instance->getValueForTargetText(false);
      $this->string($output)->isEqualTo($expected);
   }
}
