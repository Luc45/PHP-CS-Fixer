<?php

/*
 * This file is part of PHP CS Fixer.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *     Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace PhpCsFixer\Tests\Fixer\PhpUnit;

use PhpCsFixer\Tests\Test\AbstractFixerTestCase;

/**
 * @author Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * @internal
 *
 * @covers \PhpCsFixer\Fixer\PhpUnit\PhpUnitStrictFixer
 */
final class PhpUnitStrictFixerTest extends AbstractFixerTestCase
{
    /**
     * @param string      $expected
     * @param null|string $input
     *
     * @group legacy
     * @dataProvider provideTestFixCases
     * @expectedDeprecation Passing "assertions" at the root of the configuration for rule "php_unit_strict" is deprecated and will not be supported in 3.0, use "assertions" => array(...) option instead.
     */
    public function testLegacyFix($expected, $input = null)
    {
        $this->fixer->configure(array_keys($this->getMethodsMap()));
        $this->doTest($expected, $input);
    }

    /**
     * @param string      $expected
     * @param null|string $input
     *
     * @dataProvider provideTestFixCases
     */
    public function testFix($expected, $input = null)
    {
        $this->doTest($expected, $input);

        $this->fixer->configure(['assertions' => array_keys($this->getMethodsMap())]);
        $this->doTest($expected, $input);
    }

    public function provideTestFixCases()
    {
        $cases = [
            ['<?php $self->foo();'],
            [self::generateTest('$self->foo();')],
        ];

        foreach ($this->getMethodsMap() as $methodBefore => $methodAfter) {
            $cases[] = [self::generateTest("\$sth->{$methodBefore}(1, 1);")];
            $cases[] = [self::generateTest("\$sth->{$methodAfter}(1, 1);")];
            $cases[] = [self::generateTest("\$this->{$methodBefore}(1, 2, 'message', \$toMuch);")];
            $cases[] = [
                self::generateTest("\$this->{$methodAfter}(1, 2);"),
                self::generateTest("\$this->{$methodBefore}(1, 2);"),
            ];
            $cases[] = [
                self::generateTest("\$this->{$methodAfter}(1, 2); \$this->{$methodAfter}(1, 2);"),
                self::generateTest("\$this->{$methodBefore}(1, 2); \$this->{$methodBefore}(1, 2);"),
            ];
            $cases[] = [
                self::generateTest("\$this->{$methodAfter}(1, 2, 'descr');"),
                self::generateTest("\$this->{$methodBefore}(1, 2, 'descr');"),
            ];
            $cases[] = [
                self::generateTest("\$this->/*aaa*/{$methodAfter} \t /**bbb*/  ( /*ccc*/1  , 2);"),
                self::generateTest("\$this->/*aaa*/{$methodBefore} \t /**bbb*/  ( /*ccc*/1  , 2);"),
            ];
            $cases[] = [
                self::generateTest("\$this->{$methodAfter}(\$expectedTokens->count() + 10, \$tokens->count() ? 10 : 20 , 'Test');"),
                self::generateTest("\$this->{$methodBefore}(\$expectedTokens->count() + 10, \$tokens->count() ? 10 : 20 , 'Test');"),
            ];
            $cases[] = [
                self::generateTest("self::{$methodAfter}(1, 2);"),
                self::generateTest("self::{$methodBefore}(1, 2);"),
            ];
            $cases[] = [
                self::generateTest("static::{$methodAfter}(1, 2);"),
                self::generateTest("static::{$methodBefore}(1, 2);"),
            ];
            $cases[] = [
                self::generateTest("STATIC::{$methodAfter}(1, 2);"),
                self::generateTest("STATIC::{$methodBefore}(1, 2);"),
            ];
        }

        return $cases;
    }

    /**
     * Only method calls with 2 or 3 arguments should be fixed.
     *
     * @param string $expected
     *
     * @dataProvider provideTestNoFixWithWrongNumberOfArgumentsCases
     */
    public function testNoFixWithWrongNumberOfArguments($expected)
    {
        $this->fixer->configure(['assertions' => array_keys($this->getMethodsMap())]);
        $this->doTest($expected);
    }

    public function provideTestNoFixWithWrongNumberOfArgumentsCases()
    {
        $cases = [];
        foreach ($this->getMethodsMap() as $candidate => $fix) {
            $cases[sprintf('do not change call to "%s" without arguments.', $candidate)] = [
                self::generateTest(sprintf('$this->%s();', $candidate)),
            ];

            foreach ([1, 4, 5, 10] as $argumentCount) {
                $cases[sprintf('do not change call to "%s" with #%d arguments.', $candidate, $argumentCount)] = [
                    self::generateTest(
                        sprintf(
                            '$this->%s(%s);',
                            $candidate,
                            substr(str_repeat('$a, ', $argumentCount), 0, -2)
                        )
                    ),
                ];
            }
        }

        return $cases;
    }

    public function testInvalidConfig()
    {
        $this->expectException(\PhpCsFixer\ConfigurationException\InvalidFixerConfigurationException::class);
        $this->expectExceptionMessageRegExp('/^\[php_unit_strict\] Invalid configuration: The option "assertions" .*\.$/');

        $this->fixer->configure(['assertions' => ['__TEST__']]);
    }

    /**
     * @param string $expected
     * @param string $input
     *
     * @requires PHP 7.3
     * @dataProvider provideFix73Cases
     */
    public function testFix73($expected, $input)
    {
        $this->doTest($expected, $input);
    }

    public function provideFix73Cases()
    {
        foreach ($this->getMethodsMap() as $methodBefore => $methodAfter) {
            yield [
                self::generateTest("static::{$methodAfter}(1, 2,);"),
                self::generateTest("static::{$methodBefore}(1, 2,);"),
            ];

            yield [
                self::generateTest("self::{$methodAfter}(1, \$a, '', );"),
                self::generateTest("self::{$methodBefore}(1, \$a, '', );"),
            ];
        }
    }

    /**
     * @return array<string, string>
     */
    private function getMethodsMap()
    {
        return [
            'assertAttributeEquals' => 'assertAttributeSame',
            'assertAttributeNotEquals' => 'assertAttributeNotSame',
            'assertEquals' => 'assertSame',
            'assertNotEquals' => 'assertNotSame',
        ];
    }

    /**
     * @param string $content
     *
     * @return string
     */
    private static function generateTest($content)
    {
        return "<?php final class FooTest extends \\PHPUnit_Framework_TestCase {\n    public function testSomething() {\n        ".$content."\n    }\n}\n";
    }
}
