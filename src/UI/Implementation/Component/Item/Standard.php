<?php

/* Copyright (c) 2017 Alex Killing <killing@leifos.de> Extended GPL, see docs/LICENSE */

namespace ILIAS\UI\Implementation\Component\Item;

use ILIAS\UI\Component as C;

class Standard extends Item implements C\Item\Standard
{
    /**
     * @var \ILIAS\Data\Color color
     */
    protected $color = null;
    /**
     * @var null|string|\ILIAS\UI\Component\Image\Image
     */
    protected $lead = null;

    // fau: studySearch - properties for checkboxes
    protected $checkbox_name = null;
    protected $checkbox_value = null;
    // fau.

    /**
     * @inheritdoc
     */
    public function withColor(\ILIAS\Data\Color $color) : C\Item\Item
    {
        $clone = clone $this;
        $clone->color = $color;
        return $clone;
    }

    /**
     * @inheritdoc
     */
    public function getColor() : ?\ILIAS\Data\Color
    {
        return $this->color;
    }

    /**
     * @inheritdoc
     */
    public function withLeadImage(\ILIAS\UI\Component\Image\Image $image) : C\Item\Item
    {
        $clone = clone $this;
        $clone->lead = $image;
        return $clone;
    }

    /**
     * @inheritdoc
     */
    public function withLeadIcon(\ILIAS\UI\Component\Symbol\Icon\Icon $icon) : C\Item\Item
    {
        $clone = clone $this;
        $clone->lead = $icon;
        return $clone;
    }

    /**
     * @inheritdoc
     */
    public function withLeadText(string $text) : C\Item\Item
    {
        $this->checkStringArg("lead_text", $text);
        $clone = clone $this;
        $clone->lead = (string) $text;
        return $clone;
    }

    // fau: studySearch - implement checkbox functions
    public function withCheckbox(string $name, ?string $value = null) : C\Item\Item
    {
        $clone = clone $this;
        $clone->checkbox_name = $name;
        $clone->checkbox_value = $value;
        return $clone;
    }

    public function getCheckboxName() : ?string
    {
        return $this->checkbox_name;
    }

    public function getCheckboxValue() : ?string
    {
        return $this->checkbox_value;
    }
    // fau.

    /**
     * @inheritdoc
     */
    public function withNoLead() : C\Item\Item
    {
        $clone = clone $this;
        $clone->lead = null;
        return $clone;
    }

    /**
     * @inheritdoc
     */
    public function getLead()
    {
        return $this->lead;
    }
}
