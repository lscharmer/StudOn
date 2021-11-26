<?php

use ILIAS\Refinery;
use ILIAS\Setup;

/**
 * Class ilBibliographicSetupAgent
 * @author Thibeau Fuhrer <thf@studer-raimann.ch>
 */
final class ilBibliographicSetupAgent implements Setup\Agent
{
    use Setup\Agent\HasNoNamedObjective;

    /**
     * @var string component dir within ilias's data dir
     */
    public const COMPONENT_DIR = 'bibl';

    /**
     * ilBibliographicSetupAgent constructor
     * @param Refinery\Factory $refinery
     */
    public function __construct(Refinery\Factory $refinery)
    {
    }

    /**
     * @inheritdoc
     */
    public function hasConfig() : bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getArrayToConfigTransformation() : Refinery\Transformation
    {
        throw new LogicException("ilBibliographicSetupAgent has no config.");
    }

    /**
     * @inheritdoc
     */
    public function getInstallObjective(Setup\Config $config = null) : Setup\Objective
    {
        return new ilFileSystemComponentDataDirectoryCreatedObjective(
            self::COMPONENT_DIR,
            ilFileSystemComponentDataDirectoryCreatedObjective::DATADIR
        );
    }

    /**
     * @inheritdoc
     */
    public function getUpdateObjective(Setup\Config $config = null) : Setup\Objective
    {
        return new ilFileSystemComponentDataDirectoryCreatedObjective(
            self::COMPONENT_DIR,
            ilFileSystemComponentDataDirectoryCreatedObjective::DATADIR
        );
    }

    /**
     * @inheritdoc
     */
    public function getBuildArtifactObjective() : Setup\Objective
    {
        return new Setup\Objective\NullObjective();
    }

    /**
     * @inheritdoc
     */
    public function getStatusObjective(Setup\Metrics\Storage $storage) : Setup\Objective
    {
        return new Setup\Objective\NullObjective();
    }

    /**
     * @inheritDoc
     */
    public function getMigrations() : array
    {
        return [
            new ilBibliographicStorageMigration(),
        ];
    }
}