<?php

/* Copyright (c) 1998-2012 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once("./Services/UIComponent/Explorer2/classes/class.ilTreeExplorerGUI.php");

/**
 * Taxonomy explorer GUI class
 *
 * @author Alex Killing <alex.killing@gmx.de>
 * @version $Id$
 * @ingroup ServicesTaxonomy
 */
class ilTaxonomyExplorerGUI extends ilTreeExplorerGUI
{
    /**
     * @var ilCtrl
     */
    protected $ctrl;

    /**
     * Constructor
     *
     * @param
     * @return
     */
    public function __construct(
        $a_parent_obj,
        $a_parent_cmd,
        $a_tax_id,
        $a_target_gui,
        $a_target_cmd,
        $a_id = ""
    ) {
        global $DIC;
        $this->ctrl = $DIC->ctrl();
        include_once("./Services/Taxonomy/classes/class.ilTaxonomyTree.php");
        $this->tax_tree = new ilTaxonomyTree($a_tax_id);
        if ($a_id != "") {
            $this->id = $a_id;
        } else {
            $this->id = "tax_expl_" . $this->tax_tree->getTreeId();
        }
        include_once("./Services/Taxonomy/classes/class.ilObjTaxonomy.php");
        if (ilObjTaxonomy::lookupSortingMode($a_tax_id) == ilObjTaxonomy::SORT_ALPHABETICAL) {
            $this->setOrderField("title");
        } else {
            $this->setOrderField("order_nr", true);
        }
        $this->setPreloadChilds(true);
        $this->target_gui = $a_target_gui;
        $this->target_cmd = $a_target_cmd;
        //$this->setOrderField("title");
        parent::__construct($this->id, $a_parent_obj, $a_parent_cmd, $this->tax_tree);
    }


    /**
     * Get content of node
     *
     * @param
     * @return
     */
    public function getNodeContent($a_node)
    {
        $rn = $this->getRootNode();
        if ($rn["child"] == $a_node["child"]) {
            return ilObject::_lookupTitle($this->tax_tree->getTreeId());
        } else {
            // fau: taxDesc - add tooltip for taxonomy description
            if ($a_node['description']) {
                require_once("Services/UIComponent/Tooltip/classes/class.ilTooltipGUI.php");
                ilTooltipGUI::addTooltip('ilTaxExplorerNode' . $a_node["child"], $a_node['description']);

                return '<span id="ilTaxExplorerNode' . $a_node["child"] . '">' . $a_node["title"]
                    . ' <small><span class="glyphicon glyphicon-info-sign"></span></small></span>';
            } else {
                return $a_node["title"];
            }
            // fau.
        }
    }
    
    /**
     * Get node href
     *
     * @param
     * @return
     */
    public function getNodeHref($a_node)
    {
        $ilCtrl = $this->ctrl;

        if (!$this->onclick && $this->target_gui != "") {
            $ilCtrl->setParameterByClass($this->target_gui, "tax_node", $a_node["child"]);
            if (is_array($this->parent_obj)) {
                // Used for taxonomies in categories
                $href = $ilCtrl->getLinkTargetByClass($this->parent_obj, $this->target_cmd);
            } else {
                // See: https://mantis.ilias.de/view.php?id=27727
                $href = $ilCtrl->getLinkTargetByClass($this->target_gui, $this->target_cmd);
            }
            if (isset($_GET["tax_node"]) && !is_array($_GET['tax_node'])) {
                $ilCtrl->setParameterByClass($this->target_gui, "tax_node", ilUtil::stripSlashes((string) $_GET["tax_node"]));
            }
            return $href;
        } else {
            return "#";
        }
    }
    
    /**
     * Get node icon
     *
     * @param
     * @return
     */
    public function getNodeIcon($a_node)
    {
        return ilUtil::getImagePath("icon_taxn.svg");
    }
    
    /**
     *
     *
     * @param
     * @return
     */
    public function isNodeHighlighted($a_node)
    {
        if ((!$this->onclick && $a_node["child"] == $_GET["tax_node"]) ||
            ($this->onclick && is_array($this->selected_nodes) && in_array($a_node["child"], $this->selected_nodes))) {
            return true;
        }
        return false;
    }
    
    public function setOnClick($a_value)
    {
        $this->onclick = $a_value;
    }
    
    public function getNodeOnClick($a_node)
    {
        if ($this->onclick) {
            return str_replace("{NODE_CHILD}", $a_node["child"], $this->onclick);
        } else {
            // #14623
            return parent::getNodeOnClick($a_node);
        }
    }
}
