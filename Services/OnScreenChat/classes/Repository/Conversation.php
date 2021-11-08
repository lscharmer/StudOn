<?php declare(strict_types=1);
/* Copyright (c) 1998-2019 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\OnScreenChat\Repository;

use ilDBInterface;
use ILIAS\OnScreenChat\DTO\ConversationDto;
use ILIAS\OnScreenChat\DTO\MessageDto;
use ilObjUser;

/**
 * Class Conversation
 * @package ILIAS\OnScreenChat\DTO
 */
class Conversation
{
    private ilDBInterface $db;
    protected ilObjUser $user;

    public function __construct(ilDBInterface $db, ilObjUser $user)
    {
        $this->db = $db;
        $this->user = $user;
    }

    /**
     * @param string[] $conversationIds
     * @return ConversationDto[]
     */
    public function findByIds(array $conversationIds) : array
    {
        $conversations = [];

        $res = $this->db->query(
            'SELECT * FROM osc_conversation WHERE ' . $this->db->in(
                'id',
                $conversationIds,
                false,
                'text'
            )
        );

        while ($row = $this->db->fetchAssoc($res)) {
            $participants = json_decode($row['participants'], true, 512, JSON_THROW_ON_ERROR);
            $participantIds = array_filter(array_map(static function ($user) : int {
                if (is_array($user) && isset($user['id'])) {
                    return (int) $user['id'];
                }

                return 0;
            }, $participants));

            if (!in_array($this->user->getId(), $participantIds, true)) {
                continue;
            }
            
            $conversation = new ConversationDto($row['id']);
            $conversation->setIsGroup((bool) $row['is_group']);
            $conversation->setSubscriberUsrIds($participantIds);

            $inParticipants = $this->db->in(
                'osc_messages.user_id',
                $participantIds,
                false,
                'text'
            );

            $this->db->setLimit(1, 0);
            $query = "
                SELECT osc_messages.*
                FROM osc_messages
                WHERE osc_messages.conversation_id = %s
                AND {$inParticipants}
                ORDER BY osc_messages.timestamp DESC
            ";
            $msgRes = $this->db->queryF($query, ['text'], [$conversation->getId()]);
            while ($msgRow = $this->db->fetchAssoc($msgRes)) {
                $message = new MessageDto($msgRow['id'], $conversation);
                $message->setMessage($msgRow['message']);
                $message->setAuthorUsrId((int) $msgRow['user_id']);
                $message->setCreatedTimestamp((int) $msgRow['timestamp']);
                $conversation->setLastMessage($message);
                break;
            }

            $conversations[] = $conversation;
        }

        return $conversations;
    }
}
