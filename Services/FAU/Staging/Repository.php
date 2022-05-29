<?php

namespace FAU\Staging;

use FAU\Staging\Data\Education;
use FAU\Staging\Data\DipData;
use FAU\RecordRepo;
use FAU\Staging\Data\Module;
use FAU\Staging\Data\ModuleCos;

/**
 * Repository for accessing the staging database
 */
class Repository extends RecordRepo
{
    /**
     * @return Education[]
     */
    public function getEducationsToDo() : array
    {
        return $this->getDipRecords(new Education());
    }

    public function setEducationDone(Education $record)
    {
        $this->setDipRecordAsProcessed($record);
    }

    /**
     * @return Module[]
     */
    public function getModulesToDo() : array
    {
        return $this->getDipRecords(new Module());
    }

    public function setModuleDone(Module $record)
    {
        $this->setDipRecordAsProcessed($record);
    }


    /**
     * @return ModuleCos[]
     */
    public function getModuleCosToDo() : array
    {
        return $this->getDipRecords(new ModuleCos());
    }

    public function setModuleCosDone(ModuleCos $record)
    {
        $this->setDipRecordAsProcessed($record);
    }



    /**
     * Get the record objects for DIP table rows with a certain status
     * @return DipData[]
     */
    protected function getDipRecords(DipData $prototype, string $dip_status = DipData::MARKED) : array
    {
        $query = "SELECT * FROM " . $this->db->quoteIdentifier($prototype::getTableName())
            . " WHERE " . $this->getDipStatusCondition($dip_status);
        return $this->queryRecords($query, $prototype);
    }

    /**
     * @param DipData $record
     */
    protected function setDipRecordAsProcessed(DipData $record)
    {
        switch ($record->getDipStatus()) {
            case DipData::INSERTED:
            case DipData::CHANGED:
                $this->updateRecord($record->asProcessed());
                break;
            case DipData::DELETED:
                $this->deleteRecord($record);
        }
    }


    /**
     * Get the SQL condition to query for a DIP status
     */
    private function getDipStatusCondition(string $dip_status) : string
    {
        switch ($dip_status) {
            case DipData::INSERTED:
                return "dip_status = 'inserted'";
            case DipData::CHANGED:
                return "dip_status = 'changed'";
            case DipData::DELETED:
                return "dip_status = 'deleted'";
            case DipData::MARKED:
            default:
                return "dip_status IS NOT NULL";
        }
    }
}