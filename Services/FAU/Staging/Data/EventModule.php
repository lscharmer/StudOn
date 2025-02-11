<?php declare(strict_types=1);

namespace FAU\Staging\Data;

use FAU\RecordData;

/**
 * Record of the campo_module table
 */
class EventModule extends RecordData
{
    // table name has only the module - but modules may be repeated for their events
    protected const tableName = 'campo_module';
    protected const hasSequence = false;
    protected const keyTypes = [
        'event_id' => 'integer',
        'module_id' => 'integer'
    ];
    protected const otherTypes = [
        'module_nr' => 'text',
        'module_name' => 'text',
    ];

    protected int $event_id;
    protected int $module_id;
    protected ?string $module_nr;
    protected ?string $module_name;


    public function __construct(
        int $event_id,
        int $module_id,
        ?string $module_nr,
        ?string $module_name
    )
    {
        $this->event_id = $event_id;
        $this->module_id = $module_id;
        $this->module_nr = $module_nr;
        $this->module_name = $module_name;
    }

    public static function model(): self
    {
        return new self (0,0,null,null);
    }

    /**
     * @return int
     */
    public function getEventId() : int
    {
        return $this->event_id;
    }

    /**
     * @return int
     */
    public function getModuleId() : int
    {
        return $this->module_id;
    }

    /**
     * @return ?string
     */
    public function getModuleNr() : ?string
    {
        return $this->module_nr;
    }

    /**
     * @return ?string
     */
    public function getModuleName() : ?string
    {
        return $this->module_name;
    }

}