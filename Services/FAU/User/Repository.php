<?php declare(strict_types=1);

namespace FAU\User;

use FAU\User\Data\Education;
use FAU\RecordRepo;
use FAU\RecordData;
use FAU\User\Data\Achievement;

/**
 * Repository for accessing FAU user data
 * @todo replace type hints with union types in PHP 8
 */
class Repository extends RecordRepo
{
    /**
     * Delete the educations of a user account (e.g. if user is deleted)
     */
    public function deleteEducationsOfUser(int $user_id) : void
    {
        $this->db->manipulateF("DELETE FROM fau_user_educations WHERE user_id = %s", ['int'], [$user_id]);
    }

    /**
     * Get the educations assigned to a user
     * @return Education[]
     */
    public function getEducationsOfUser(int $user_id, ?string $type = null) : array
    {
        $query = "SELECT * FROM fau_user_educations WHERE user_id = " . $this->db->quote($user_id, 'integer');
        if (isset($type))  {
            $query .= " AND " . $this->db->quoteIdentifier('type') . ' = ' . $this->db->quote($type, 'text');
        }
        return $this->queryRecords($query, Education::model());
    }

    /**
     * Get all achievements
     * @return Achievement[]
     */
    public function getAllAchievements() : array
    {
        return $this->getAllRecords(Achievement::model(), false);
    }

    /**
     * Get the achievements of a person
     * @return Achievement[]
     */
    public function getAchievementsOfPerson(int $person_id) : array
    {
        $query = "SELECT * FROM fau_user_achievements WHERE person_id = " . $this->db->quote($person_id, 'integer');
        return $this->queryRecords($query, Achievement::model());
    }

    /**
     * Save record data of an allowed type
     * @param Achievement|Education $record
     */
    public function save(RecordData $record)
    {
        $this->replaceRecord($record);
    }


    /**
     * Delete record data of an allowed type
     * @param Achievement|Education $record
     */
    public function delete(RecordData $record)
    {
        $this->deleteRecord($record);
    }
}