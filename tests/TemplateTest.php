<?php

declare(strict_types=1);

namespace atk4\ui\tests;

use atk4\core\AtkPhpunit;
use atk4\ui\Exception;

class TemplateTest extends AtkPhpunit\TestCase
{
    /**
     * Test constructor.
     */
    public function testBasicInit()
    {
        $t = new \atk4\ui\Template('hello, {foo}world{/}');
        $t['foo'] = 'bar';

        $this->assertSame('hello, bar', $t->render());
    }

    /**
     * Test isTopTag().
     */
    public function testIsTopTag()
    {
        $t = new \atk4\ui\Template('a{$foo}b');
        $this->assertTrue($t->isTopTag('_top'));
        $this->assertFalse($t->isTopTag('foo'));
    }

    /**
     * Test getTagRef().
     */
    public function testGetTagRef()
    {
        // top tag
        $t = new \atk4\ui\Template('{foo}hello{/}, cruel {bar}world{/}. {foo}hello{/}');
        $t1 = &$t->getTagRef('_top');
        $this->assertSame(['', 'foo#1' => ['hello'], ', cruel ', 'bar#1' => ['world'], '. ', 'foo#2' => ['hello']], $t1);

        $t1 = ['good bye']; // will change $t->template because it's by reference
        $this->assertSame(['good bye'], $t->template);

        // any tag
        $t = new \atk4\ui\Template('{foo}hello{/}, cruel {bar}world{/}. {foo}hello{/}');
        $t2 = &$t->getTagRef('foo');
        $this->assertSame(['hello'], $t2);

        $t2 = ['good bye']; // will change $t->template because it's by reference
        $this->assertSame(['', 'foo#1' => ['good bye'], ', cruel ', 'bar#1' => ['world'], '. ', 'foo#2' => ['hello']], $t->template);
    }

    /**
     * Test conditional tag.
     */
    public function testConditionalTags()
    {
        $s = 'My {email?}e-mail {$email}{/email?} {phone?}phone {$phone}{/?}. Contact me!';
        $t = new \atk4\ui\Template($s);

        $t1 = &$t->getTagRef('_top');
        $this->assertSame([
            0 => 'My ',
            'email?#1' => [
                0 => 'e-mail ',
                'email#1' => [''],
            ],
            1 => ' ',
            'phone?#1' => [
                0 => 'phone ',
                'phone#1' => [''],
            ],
            2 => '. Contact me!',
        ], $t1);

        // test filled values
        $t = new \atk4\ui\Template($s);
        $t->set('email', 'test@example.com');
        $t->set('phone', 123);
        $this->assertSame('My e-mail test@example.com phone 123. Contact me!', $t->render());

        $t = new \atk4\ui\Template($s);
        $t->set('email', null);
        $t->set('phone', 123);
        $this->assertSame('My  phone 123. Contact me!', $t->render());

        $t = new \atk4\ui\Template($s);
        $t->set('email', '');
        $t->set('phone', 123);
        $this->assertSame('My  phone 123. Contact me!', $t->render());

        $t = new \atk4\ui\Template($s);
        $t->set('email', false);
        $t->set('phone', 0);
        $this->assertSame('My  phone 0. Contact me!', $t->render());

        // nested conditional tags (renders comma only when both values are provided)
        $s = 'My {email?}e-mail {$email}{/email?}{email?}{phone?}, {/?}{/?}{phone?}phone {$phone}{/?}. Contact me!';

        $t = new \atk4\ui\Template($s);
        $t->set('email', 'test@example.com');
        $t->set('phone', 123);
        $this->assertSame('My e-mail test@example.com, phone 123. Contact me!', $t->render());

        $t = new \atk4\ui\Template($s);
        $t->set('email', null);
        $t->set('phone', 123);
        $this->assertSame('My phone 123. Contact me!', $t->render());

        $t = new \atk4\ui\Template($s);
        $t->set('email', false);
        $t->set('phone', 0);
        $this->assertSame('My phone 0. Contact me!', $t->render());
    }

    /**
     * Conditional tag usage example - VAT usage.
     */
    public function testConditionalTagsVat()
    {
        $s = '{vat_applied?}VAT is {$vat}%{/?}' .
             '{vat_zero?}VAT is zero{/?}' .
             '{vat_not_applied?}VAT is not applied{/?}';

        $f = function ($vat) use ($s) {
            return (new \atk4\ui\Template($s))->set([
                'vat_applied' => !empty($vat),
                'vat_zero' => ($vat === 0),
                'vat_not_applied' => ($vat === null),
                'vat' => $vat,
            ])->render();
        };

        $this->assertSame('VAT is 21%', $f(21));
        $this->assertSame('VAT is zero', $f(0));
        $this->assertSame('VAT is not applied', $f(null));
    }

    /**
     * Exception in getTagRef().
     */
    public function testGetTagRefException()
    {
        $this->expectException(Exception::class);
        $t = new \atk4\ui\Template('{foo}hello{/}');
        $t->getTagRef('bar'); // not existent tag
    }

    /**
     * Test getTagRefList().
     */
    public function testGetTagRefList()
    {
        // top tag
        $t = new \atk4\ui\Template('{foo}hello{/}, cruel {bar}world{/}. {foo}hello{/}');
        $t1 = $t->getTagRefList('_top');
        $this->assertSame([['', 'foo#1' => ['hello'], ', cruel ', 'bar#1' => ['world'], '. ', 'foo#2' => ['hello']]], $t1);

        $t1[0] = ['good bye']; // will change $t->template because it's by reference
        $this->assertSame(['good bye'], $t->template);

        // any tag
        $t = new \atk4\ui\Template('{foo}hello{/}, cruel {bar}world{/}. {foo}hello{/}');
        $t2 = $t->getTagRefList('foo');
        $this->assertSame([['hello'], ['hello']], $t2);

        $t2[1] = ['good bye']; // will change $t->template last "foo" tag because it's by reference
        $this->assertSame(['', 'foo#1' => ['hello'], ', cruel ', 'bar#1' => ['world'], '. ', 'foo#2' => ['good bye']], $t->template);

        // array of tags
        $t = new \atk4\ui\Template('{foo}hello{/}, cruel {bar}world{/}. {foo}hello{/}');
        $t2 = $t->getTagRefList(['foo', 'bar']);
        $this->assertSame([['hello'], ['hello'], ['world']], $t2);

        $t2[1] = ['good bye']; // will change $t->template last "foo" tag because it's by reference
        $t2[2] = ['planet'];   // will change $t->template "bar" tag because it's by reference too
        $this->assertSame(['', 'foo#1' => ['hello'], ', cruel ', 'bar#1' => ['planet'], '. ', 'foo#2' => ['good bye']], $t->template);
    }

    /**
     * Non existant template - throw exception.
     */
    public function testBadTemplate1()
    {
        $this->expectException(Exception::class);
        $t = new \atk4\ui\Template();
        $t->load('bad_template_file');
    }

    /**
     * Non existant template - no exception.
     */
    public function testBadTemplate2()
    {
        $t = new \atk4\ui\Template();
        $this->assertFalse($t->tryLoad('bad_template_file'));
    }

    /**
     * Exception in getTagRefList().
     */
    public function testGetTagRefListException()
    {
        $this->expectException(Exception::class);
        $t = new \atk4\ui\Template('{foo}hello{/}');
        $t->getTagRefList('bar'); // not existent tag
    }

    /**
     * Test hasTag().
     */
    public function testHasTag()
    {
        $t = new \atk4\ui\Template('{foo}hello{/}, cruel {bar}world{/}. {foo}hello{/}');
        $this->assertTrue($t->hasTag(['foo', 'bar'])); // all tags exist
        $this->assertFalse($t->hasTag(['foo', 'bar', 'qwe'])); // qwe tag does not exist
    }

    /**
     * Test set() exception.
     */
    public function testSetException1()
    {
        $this->expectException(Exception::class);
        $t = new \atk4\ui\Template('{foo}hello{/} guys');
        $t->set('qwe', 'Hello'); // not existent tag
    }

    /**
     * Test set() exception.
     */
    public function testSetException2()
    {
        $this->expectException(Exception::class);
        $t = new \atk4\ui\Template('{foo}hello{/} guys');
        $t->set('foo', new \StdClass()); // bad value
    }

    /**
     * Test set, append, tryAppend, tryAppendHtml, del, tryDel.
     */
    public function testSetAppendDel()
    {
        $t = new \atk4\ui\Template('{foo}hello{/} guys');

        // del tests
        $t->set('foo', 'Hello');
        $t->del('foo');
        $this->assertSame(' guys', $t->render());
        $t->set('foo', 'Hello');
        $t->tryDel('qwe'); // non existent tag, ignores
        $this->assertSame('Hello guys', $t->render());

        // set and append tests
        $t->set('foo', 'Hello');
        $t->set('foo', 'Hi'); // overwrites
        $t->setHtml('foo', '<b>Hi</b>'); // overwrites
        $t->trySet('qwe', 'ignore this'); // ignores
        $t->trySetHtml('qwe', '<b>ignore</b> this'); // ignores

        $t->append('foo', ' and'); // appends
        $t->appendHtml('foo', ' <b>welcome</b> my'); // appends
        $t->tryAppend('foo', ' dear'); // appends
        $t->tryAppend('qwe', 'ignore this'); // ignores
        $t->tryAppendHtml('foo', ' and <b>smart</b>'); // appends html
        $t->tryAppendHtml('qwe', '<b>ignore</b> this'); // ignores

        $this->assertSame('<b>Hi</b> and <b>welcome</b> my dear and <b>smart</b> guys', $t->render());
    }

    /**
     * ArrayAccess test.
     */
    public function testArrayAccess()
    {
        $t = new \atk4\ui\Template('{foo}hello{/}, cruel {bar}world{/}. {foo}welcome{/}');

        $this->assertTrue(isset($t['foo']));

        $t['foo'] = 'Hi';
        $this->assertSame(['Hi'], $t['foo']);

        unset($t['foo']);
        $this->assertSame([], $t['foo']);

        $this->assertTrue(isset($t['foo'])); // it's still set even after unset - that's specific for Template
    }

    /**
     * Test eachTag.
     */
    public function testEachTag()
    {
        $t = new \atk4\ui\Template('{foo}hello{/}, {how}cruel{/how} {bar}world{/}. {foo}welcome{/}');

        // don't throw exception if tag does not exist
        $t->eachTag('ignore', function () {
        });

        // replace values in these tags
        $t->eachTag(['foo', 'bar'], function ($value, $tag) {
            return strtoupper($value);
        });
        $this->assertSame('HELLO, cruel WORLD. WELCOME', $t->render());

        // tag contains all template (for example in Lister)
        $t = new \atk4\ui\Template('{foo}hello{/}');
        $t->eachTag('foo', function ($value, $tag) {
            return strtoupper($value);
        });
        $this->assertSame('HELLO', $t->render());
    }

    /**
     * Clone region.
     */
    public function testClone()
    {
        $t = new \atk4\ui\Template('{foo}hello{/} guys');

        // clone only {foo} region
        $t1 = $t->cloneRegion('foo');
        $this->assertSame('hello', $t1->render());

        // clone all template
        $t1 = $t->cloneRegion('_top');
        $this->assertSame('hello guys', $t1->render());
    }

    /**
     * Try to load template from non existent file - exception.
     */
    public function testLoadException()
    {
        $this->expectException(Exception::class);
        $t = new \atk4\ui\Template();
        $t->load('such-file-does-not-exist.txt');
    }

    /**
     * Test renderRegion.
     */
    public function testRenderRegion()
    {
        $t = new \atk4\ui\Template('{foo}hello{/} guys');
        $this->assertSame('hello', $t->render('foo'));
    }

    public function testDollarTags()
    {
        $t = new \atk4\ui\Template('{$foo} guys and {$bar} here');
        $t->set([
            'foo' => 'Hello',
            'bar' => 'welcome',
        ]);
        $this->assertSame('Hello guys and welcome here', $t->render());
    }
}
