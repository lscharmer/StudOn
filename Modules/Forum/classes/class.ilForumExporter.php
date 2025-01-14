<?php
/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Exporter class for sessions
 *
 * @author Stefan Meyer <meyer@leifos.com>
 * @version $Id: $
 * @ingroup ModulesForum
 */
class ilForumExporter extends ilXmlExporter implements ilForumObjectConstants
{
    private $ds;

    /**
     * Initialisation
     */
    public function init()
    {
    }


    /**
     * Get xml representation
     *
     * @param	string		entity
     * @param	string		target release
     * @param	string		id
     * @return	string		xml string
     */
    public function getXmlRepresentation($a_entity, $a_schema_version, $a_id)
    {
        $xml = '';

        if (ilObject::_lookupType($a_id) == 'frm') {
            $writer = new ilForumXMLWriter();
            $writer->setForumId($a_id);
            ilUtil::makeDirParents($this->getAbsoluteExportDirectory());
            $writer->setFileTargetDirectories($this->getRelativeExportDirectory(), $this->getAbsoluteExportDirectory());
            $writer->start();
            $xml .= $writer->getXml();
        }

        return $xml;
    }

    /**
     * @inheritdoc
     */
    public function getXmlExportTailDependencies($a_entity, $a_target_release, $a_ids)
    {
        $deps = [];

        if ('frm' === $a_entity) {
            $deps[] = [
                'component' => 'Services/Object',
                'entity' => 'common',
                'ids' => $a_ids
            ];

            $deps[] = [
                "component" => "Services/News",
                "entity" => "news_settings",
                "ids" => $a_ids
            ];
        }

        $pageObjectIds = [];
        $styleIds = [];

        foreach ($a_ids as $frmObjId) {
            $frm = ilObjectFactory::getInstanceByObjId($frmObjId, false);
            if (!$frm || !($frm instanceof ilObjForum)) {
                continue;
            }

            $frmPageObjIds = $frm->getPageObjIds();
            foreach ($frmPageObjIds as $frmPageObjId) {
                $pageObjectIds[] = self::OBJ_TYPE . ':' . $frmPageObjId;
            }

            $properties = ilForumProperties::getInstance($frm->getId());
            if ($properties->getStyleSheetId() > 0) {
                $styleIds[$properties->getStyleSheetId()] = $properties->getStyleSheetId();
            }
        }

        if (count($pageObjectIds) > 0) {
            $deps[] = [
                'component' => 'Services/COPage',
                'entity' => 'pg',
                'ids' => $pageObjectIds,
            ];
        }

        if (count($styleIds) > 0) {
            $deps[] = [
                'component' => 'Services/Style',
                'entity' => 'sty',
                'ids' => array_values($styleIds),
            ];
        }

        return $deps;
    }

    /**
     * Returns schema versions that the component can export to.
     * ILIAS chooses the first one, that has min/max constraints which
     * fit to the target release. Please put the newest on top.
     *
     * @return
     */
    public function getValidSchemaVersions($a_entity)
    {
        return array(
            "4.1.0" => array(
                "namespace" => "http://www.ilias.de/Modules/Forum/frm/4_1",
                "xsd_file" => "ilias_frm_4_1.xsd",
                "uses_dataset" => false,
                "min" => "4.1.0",
                "max" => "4.4.999"
            ),
            "4.5.0" => array(
                "namespace" => "http://www.ilias.de/Modules/Forum/frm/4_5",
                "xsd_file" => "ilias_frm_4_5.xsd",
                "uses_dataset" => false,
                "min" => "4.5.0",
                "max" => "5.0.999"
            ),
            "5.1.0" => array(
                "namespace" => "http://www.ilias.de/Modules/Forum/frm/5_1",
                "xsd_file" => "ilias_frm_5_1.xsd",
                "uses_dataset" => false,
                "min" => "5.1.0",
                "max" => ""
            )
        );
    }
}
