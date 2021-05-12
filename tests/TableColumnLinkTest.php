<?php

declare(strict_types=1);

namespace Atk4\Ui\Tests;

use Atk4\Core\AtkPhpunit;
use Atk4\Ui\Table;

class TableColumnLinkTest extends AtkPhpunit\TestCase
{
    use Concerns\HandlesTable;

    public $db;
    public $table;
    public $column;

    protected function setUp(): void
    {
        $arr = ['table' => [1 => ['id' => 1, 'name' => 'bar', 'ref' => 'ref123', 'salary' => -123]]];
        $db = new \Atk4\Data\Persistence\Array_($arr);
        $m = new \Atk4\Data\Model($db, ['table' => 'table']);
        $m->addField('name');
        $m->addField('ref');
        $m->addField('salary');
        $this->table = new \Atk4\Ui\Table();
        $this->table->invokeInit();
        $this->table->setModel($m, ['name', 'ref']);
    }

    public function testgetDataRowHtml(): void
    {
        $this->assertSame('<td>{$name}</td><td>{$ref}</td>', $this->table->getDataRowHtml());

        $this->assertSame(
            '<tr data-id="1"><td>bar</td><td>ref123</td></tr>',
            $this->extractTableRow($this->table)
        );
    }

    public function testMultipleFormatters(): void
    {
        $this->table->addDecorator('name', new Table\Column\Template('<b>{$name}</b>'));

        $this->assertSame('<td><b>{$name}</b></td><td>{$ref}</td>', $this->table->getDataRowHtml());

        $this->assertSame(
            '<tr data-id="1"><td><b>bar</b></td><td>ref123</td></tr>',
            $this->extractTableRow($this->table)
        );
    }

    public function testTdLast(): void
    {
        $salary = $this->table->addColumn('salary', new Table\Column\Money());

        $this->assertSame(
            '<td>{$name}</td><td>{$ref}</td><td class="{$' . $this->getColumnClass($salary) . '} right aligned single line">{$salary}</td>',
            $this->table->getDataRowHtml()
        );

        $this->assertSame(
            '<tr data-id="1"><td>bar</td><td>ref123</td><td class="negative right aligned single line">-123</td></tr>',
            $this->extractTableRow($this->table)
        );
    }

    public function testTdNotLast(): void
    {
        $salary = $this->table->addColumn('salary', new Table\Column\Money());
        $this->table->addDecorator('salary', new Table\Column\Template('<b>{$salary}</b>'));

        $this->assertSame(
            '<td>{$name}</td><td>{$ref}</td><td class="{$' . $this->getColumnClass($salary) . '} right aligned single line"><b>{$salary}</b></td>',
            $this->table->getDataRowHtml()
        );

        $this->assertSame(
            '<tr data-id="1"><td>bar</td><td>ref123</td><td class="negative right aligned single line"><b>-123</b></td></tr>',
            $this->extractTableRow($this->table)
        );
    }

    public function testTwoMoneys(): void
    {
        $salary_1 = $this->table->addDecorator('name', new Table\Column\Money());
        $salary_2 = $this->table->addColumn('salary', new Table\Column\Money());
        $this->table->addDecorator('salary', new Table\Column\Template('<b>{$salary}</b>'));

        $this->assertSame(
            '<td class="{$' . $this->getColumnClass($salary_1) . '} right aligned single line">{$name}</td><td>{$ref}</td><td class="{$' . $this->getColumnClass($salary_2) . '} right aligned single line"><b>{$salary}</b></td>',
            $this->table->getDataRowHtml()
        );

        $this->assertSame(
            '<tr data-id="1"><td class=" right aligned single line">bar</td><td>ref123</td><td class="negative right aligned single line"><b>-123</b></td></tr>',
            $this->extractTableRow($this->table)
        );
    }

    public function testTemplateStacking(): void
    {
        // Simplest way to integrate
        $this->table->addDecorator('name', new Table\Column\Template('<b>{$name}</b>'));
        $this->table->addDecorator('name', new Table\Column\Template('<u>{$name}</u>'));

        $this->assertSame(
            '<td><u><b>{$name}</b></u></td><td>{$ref}</td>',
            $this->table->getDataRowHtml()
        );

        $this->assertSame(
            '<tr data-id="1"><td><u><b>bar</b></u></td><td>ref123</td></tr>',
            $this->extractTableRow($this->table)
        );
    }

    public function testRender1(): void
    {
        // Simplest way to integrate
        $this->table->addDecorator('name', new Table\Column\Template('<b>{$name}</b>'));
        $this->table->addDecorator('name', new Table\Column\Template('<u>{$name}</u>'));

        $this->assertSame(
            '<tr data-id="1"><td><u><b>bar</b></u></td><td>ref123</td></tr>',
            $this->extractTableRow($this->table)
        );
    }

    public function testRender1a(): void
    {
        // Simplest way to integrate
        $this->table->addColumn(null, [Table\Column\Template::class, 'hello<b>world</b>']);

        $this->assertSame(
            '<td>{$name}</td><td>{$ref}</td><td>hello<b>world</b></td>',
            $this->table->getDataRowHtml()
        );

        $this->assertSame(
            '<tr data-id="1"><td>bar</td><td>ref123</td><td>hello<b>world</b></td></tr>',
            $this->extractTableRow($this->table)
        );
    }

    public function testLink1(): void
    {
        $link = $this->table->addDecorator('name', new Table\Column\Link('example.php?id={$id}'));

        $this->assertSame(
            '<td><a href="{$' . $this->getColumnRef($link) . '}">{$name}</a></td><td>{$ref}</td>',
            $this->table->getDataRowHtml()
        );

        $this->assertSame(
            '<tr data-id="1"><td><a href="example.php?id=1">bar</a></td><td>ref123</td></tr>',
            $this->extractTableRow($this->table)
        );
    }

    public function testLink1a(): void
    {
        $link = $this->table->addDecorator('name', [Table\Column\Link::class, 'url' => 'example.php?id={$id}']);

        $this->assertSame(
            '<td><a href="{$' . $this->getColumnRef($link) . '}">{$name}</a></td><td>{$ref}</td>',
            $this->table->getDataRowHtml()
        );

        $this->assertSame(
            '<tr data-id="1"><td><a href="example.php?id=1">bar</a></td><td>ref123</td></tr>',
            $this->extractTableRow($this->table)
        );
    }

    public function testLink2(): void
    {
        $this->table->addDecorator('name', new Table\Column\Link(['example', 'id' => '{$id}']));

        // url is properly encoded

        $this->assertSame(
            '<tr data-id="1"><td><a href="example.php?id=%7B%24id%7D">bar</a></td><td>ref123</td></tr>',
            $this->extractTableRow($this->table)
        );
    }

    public function testLink3(): void
    {
        $this->table->addDecorator('name', new Table\Column\Link(['example'], ['id']));

        $this->assertSame(
            '<tr data-id="1"><td><a href="example.php?id=1">bar</a></td><td>ref123</td></tr>',
            $this->extractTableRow($this->table)
        );
    }

    public function testLink4(): void
    {
        $this->table->addDecorator('name', new Table\Column\Link(['example'], ['test' => 'id']));

        $this->assertSame(
            '<tr data-id="1"><td><a href="example.php?test=1">bar</a></td><td>ref123</td></tr>',
            $this->extractTableRow($this->table)
        );
    }

    public function testLink5(): void
    {
        $this->table->addDecorator('name', [Table\Column\Link::class, ['example'], ['test' => 'id']]);

        $this->assertSame(
            '<tr data-id="1"><td><a href="example.php?test=1">bar</a></td><td>ref123</td></tr>',
            $this->extractTableRow($this->table)
        );
    }

    public function testLink6(): void
    {
        $this->table->addDecorator('name', [Table\Column\Link::class, ['example'], ['test' => 'id'], 'force_download' => true]);

        $this->assertSame(
            '<tr data-id="1"><td><a href="example.php?test=1" download="true" >bar</a></td><td>ref123</td></tr>',
            $this->extractTableRow($this->table)
        );
    }

    public function testLink7(): void
    {
        $this->table->addDecorator('name', [Table\Column\Link::class, ['example'], ['test' => 'id'], 'target' => '_blank']);

        $this->assertSame(
            '<tr data-id="1"><td><a href="example.php?test=1" target="_blank" >bar</a></td><td>ref123</td></tr>',
            $this->extractTableRow($this->table)
        );
    }

    public function testLink8(): void
    {
        $this->table->addDecorator('name', [Table\Column\Link::class, ['example'], ['test' => 'id'], 'icon' => 'info']);

        $this->assertSame(
            '<tr data-id="1"><td><a href="example.php?test=1"><i class="icon info"></i>bar</a></td><td>ref123</td></tr>',
            $this->extractTableRow($this->table)
        );
    }

    public function testLink9(): void
    {
        $this->table->addDecorator('name', [Table\Column\Link::class, ['example'], ['test' => 'id'], 'label' => false]);

        $this->assertSame(
            '<tr data-id="1"><td><a href="example.php?test=1"></a></td><td>ref123</td></tr>',
            $this->extractTableRow($this->table)
        );
    }

    public function testLink10(): void
    {
        // need to reset all to set a nulled value in field name model
        $arr = ['table' => [1 => ['id' => 1, 'name' => '', 'ref' => 'ref123', 'salary' => -123]]];
        $db = new \Atk4\Data\Persistence\Array_($arr);
        $m = new \Atk4\Data\Model($db, ['table' => 'table']);
        $m->addField('name');
        $m->addField('ref');
        $m->addField('salary');
        $this->table = new \Atk4\Ui\Table();
        $this->table->invokeInit();
        $this->table->setModel($m, ['name', 'ref']);

        $this->table->addDecorator('name', [Table\Column\NoValue::class, ['no_value' => ' --- ']]);

        $this->assertSame(
            '<tr data-id="1"><td> --- </td><td>ref123</td></tr>',
            $this->extractTableRow($this->table)
        );
    }

    public function testLink11(): void
    {
        $this->table->addDecorator('name', [Table\Column\Tooltip::class, ['tooltip_field' => 'ref']]);

        $this->assertSame(
            '<tr data-id="1"><td class=""> bar<span class="ui icon link " data-tooltip="ref123"><i class="ui icon info circle"></span></td><td>ref123</td></tr>',
            $this->extractTableRow($this->table)
        );
    }

    /*
    function testLink1() {

        // Simplest way to integrate
        $this->table->addColumn('name', new Table\Column\Link());

        $this->assertEquals(
            '<td><u><b>{$name}</b></u></td><td>{$ref}</td>',
            $this->table->getDataRowHtml()
        );
    }
     */
}
