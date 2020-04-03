<?php

/* Copyright (c) 2018 Nils Haagen <nils.haagen@concepts-and-training.de> Extended GPL, see docs/LICENSE */

require_once(__DIR__ . "/../../../../libs/composer/vendor/autoload.php");
require_once(__DIR__ . "/../../Base.php");

use \ILIAS\UI\Component as C;
use \ILIAS\UI\Implementation as I;
use \ILIAS\UI\Implementation\Component\Signal;

/**
 * Test on button implementation.
 */
class BulkyButtonTest extends ILIAS_UI_TestBase
{
    public function setUp() : void
    {
        $this->button_factory = new I\Component\Button\Factory();
        $this->glyph = new I\Component\Symbol\Glyph\Glyph("briefcase", "briefcase");
        $this->icon = new I\Component\Symbol\Icon\Standard("someExample", "Example", "small", false);
    }

    public function test_implements_factory_interface()
    {
        $this->assertInstanceOf(
            "ILIAS\\UI\\Component\\Button\\Bulky",
            $this->button_factory->bulky($this->glyph, "label", "http://www.ilias.de")
        );
    }

    public function test_construction_icon_type_wrong()
    {
        $this->expectException(\TypeError::class);

        $f = $this->button_factory;
        $f->bulky(new StdClass(), "", "http://www.ilias.de");
    }

    public function test_glyph_or_icon_for_glyph()
    {
        $b = $this->button_factory->bulky($this->glyph, "label", "http://www.ilias.de");
        $this->assertEquals(
            $this->glyph,
            $b->getIconOrGlyph()
        );
    }

    public function test_glyph_or_icon_for_icon()
    {
        $b = $this->button_factory->bulky($this->icon, "label", "http://www.ilias.de");
        $this->assertEquals(
            $this->icon,
            $b->getIconOrGlyph()
        );
    }

    public function test_engaged()
    {
        $b = $this->button_factory->bulky($this->glyph, "label", "http://www.ilias.de");
        $this->assertFalse($b->isEngaged());

        $b = $b->withEngagedState(true);
        $this->assertInstanceOf(
            "ILIAS\\UI\\Component\\Button\\Bulky",
            $b
        );
        $this->assertTrue($b->isEngaged());
    }

    public function test_with_aria_role()
    {
        try {
            $b = $this->button_factory->bulky($this->glyph, "label", "http://www.ilias.de")
                                      ->withAriaRole(C\Button\Bulky::MENUITEM);
            $this->assertEquals("menuitem", $b->getAriaRole());
        } catch (\InvalidArgumentException $e) {
            $this->assertFalse("This should not happen");
        }
    }

    public function test_with_aria_role_incorrect()
    {
        try {
            $this->button_factory->bulky($this->glyph, "label", "http://www.ilias.de")
                                      ->withAriaRole("loremipsum");
            $this->assertFalse("This should not happen");
        } catch (\InvalidArgumentException $e) {
            $this->assertTrue(true);
        }
    }

    public function test_render_with_glyph_in_context()
    {
        $r = $this->getDefaultRenderer();
        $b = $this->button_factory->bulky($this->glyph, "label", "http://www.ilias.de");

        $expected = ''
            . '<button class="btn btn-bulky" data-action="http://www.ilias.de" id="id_1">'
            . '	<span class="glyph" aria-label="briefcase">'
            . '		<span class="glyphicon glyphicon-briefcase" aria-hidden="true"></span>'
            . '	</span>'
            . '	<span class="bulky-label">label</span>'
            . '</button>';

        $this->assertHTMLEquals(
            $expected,
            $r->render($b)
        );
    }

    public function test_render_with_glyph_in_context_and_engaged()
    {
        $r = $this->getDefaultRenderer();
        $b = $this->button_factory->bulky($this->glyph, "label", "http://www.ilias.de")
                ->withEngagedState(true);
        $expected = ''
            . '<button class="btn btn-bulky engaged" data-action="http://www.ilias.de" id="id_1" aria-pressed="true">'
            . '	<span class="glyph" aria-label="briefcase">'
            . '		<span class="glyphicon glyphicon-briefcase" aria-hidden="true"></span>'
            . '	</span>'
            . '	<span class="bulky-label">label</span>'
            . '</button>';

        $this->assertHTMLEquals(
            $expected,
            $r->render($b)
        );
    }

    public function test_render_with_icon()
    {
        $r = $this->getDefaultRenderer();
        $b = $this->button_factory->bulky($this->icon, "label", "http://www.ilias.de");

        $expected = ''
            . '<button class="btn btn-bulky" data-action="http://www.ilias.de" id="id_1">'
            . '	<div class="icon someExample small" aria-label="Example"></div>'
            . '	<span class="bulky-label">label</span>'
            . '</button>';

        $this->assertHTMLEquals(
            $expected,
            $r->render($b)
        );
    }

    public function test_render_button_with_aria_role()
    {
        $r = $this->getDefaultRenderer();
        $b = $this->button_factory->bulky($this->icon, "label", "http://www.ilias.de")
            ->withAriaRole(C\Button\Bulky::MENUITEM);

        $expected = ''
            . '<button class="btn btn-bulky" data-action="http://www.ilias.de" id="id_1" role="menuitem" aria-haspopup="true">'
            . '	<div class="icon someExample small" aria-label="Example"></div>'
            . '	<span class="bulky-label">label</span>'
            . '</button>';

        $this->assertHTMLEquals(
            $expected,
            $r->render($b)
        );
    }
}
