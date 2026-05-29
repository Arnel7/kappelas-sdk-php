<?php

declare(strict_types=1);

namespace Kappelas\Types;

final class Message
{
    public function __construct(
        /** Message ID (from WS: 'id', from webhook: 'message_id'). */
        public readonly int           $id,
        public readonly int           $chatId,
        public readonly ?string       $senderId         = null,
        /** @var 'text'|'image'|'video'|'audio'|'document'|'system'|'poll'|'sticker'|'location'|'contact'|null */
        public readonly ?string       $type             = null,
        public readonly ?string       $text             = null,
        public readonly ?string       $mediaId          = null,
        /** Raw extra_data payload (inline keyboard definition, etc.). */
        public readonly mixed         $extraData        = null,
        public readonly string        $status           = 'sent',
        public readonly ?int          $editedAt         = null,
        public readonly ?int          $deletedAt        = null,
        public readonly int           $createdAt        = 0,
        public readonly ?int          $replyToId        = null,
        public readonly ?ReplySnapshot $replyToSnapshot  = null,
        /** @var array<mixed> */
        public readonly array         $mentions         = [],
        public readonly mixed         $forwardedFrom    = null,
        public readonly ?int          $expiresAt        = null,
        public readonly ?string       $senderName       = null,
        public readonly ?string       $senderUsername   = null,
        public readonly ?string       $senderAvatarUrl  = null,
        public readonly ?string       $clientMsgId      = null,
        public readonly ?int          $width            = null,
        public readonly ?int          $height           = null,
        /** @var 'private'|'group'|'channel'|null */
        public readonly ?string       $chatType         = null,
    ) {}

    public static function fromArray(array $d): self
    {
        $rs = $d['reply_to_snapshot'] ?? null;
        return new self(
            id:               (int) ($d['id'] ?? $d['message_id'] ?? 0),
            chatId:           (int) ($d['chat_id'] ?? 0),
            senderId:         $d['sender_id']          ?? null,
            type:             $d['type']               ?? null,
            text:             $d['text']               ?? null,
            mediaId:          $d['media_id']           ?? null,
            extraData:        $d['extra_data']         ?? null,
            status:           $d['status']             ?? 'sent',
            editedAt:         isset($d['edited_at'])   ? (int) $d['edited_at']   : null,
            deletedAt:        isset($d['deleted_at'])  ? (int) $d['deleted_at']  : null,
            createdAt:        (int) ($d['created_at']  ?? $d['sent_at'] ?? 0),
            replyToId:        isset($d['reply_to_id']) ? (int) $d['reply_to_id'] : null,
            replyToSnapshot:  is_array($rs) ? ReplySnapshot::fromArray($rs) : null,
            mentions:         (array) ($d['mentions']  ?? []),
            forwardedFrom:    $d['forwarded_from']     ?? null,
            expiresAt:        isset($d['expires_at'])  ? (int) $d['expires_at']  : null,
            senderName:       $d['sender_name']        ?? $d['sender_nom']       ?? null,
            senderUsername:   $d['sender_username']    ?? null,
            senderAvatarUrl:  $d['sender_avatar_url']  ?? null,
            clientMsgId:      $d['client_msg_id']      ?? null,
            width:            isset($d['width'])       ? (int) $d['width']       : null,
            height:           isset($d['height'])      ? (int) $d['height']      : null,
            chatType:         $d['chat_type']          ?? null,
        );
    }
}
