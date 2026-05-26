<?php

declare(strict_types=1);

namespace Kappelas\Resources;

use Kappelas\Internal\HttpClient;
use Kappelas\Types\DeleteResult;
use Kappelas\Types\EditMessageResult;
use Kappelas\Types\SendCarouselResult;
use Kappelas\Types\SendMediaResult;
use Kappelas\Types\SendResult;
use Kappelas\Types\TypingResult;

final class MessagesResource
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly string     $base,
    ) {}

    /**
     * Send a text message, optionally with a reply keyboard.
     *
     * @param array{
     *   chat_id: int,
     *   text: string,
     *   reply_to_id?: int,
     *   reply_markup?: array,
     * } $params
     */
    public function send(array $params): SendResult
    {
        $body = ['chat_id' => $params['chat_id'], 'text' => $params['text']];
        if (isset($params['reply_to_id'])) {
            $body['reply_to_id'] = $params['reply_to_id'];
        }
        if (isset($params['reply_markup'])) {
            $body['reply_markup'] = $params['reply_markup'];
        }
        return SendResult::fromArray($this->http->post($this->base . '/sendMessage', $body));
    }

    /**
     * Send a photo.
     *
     * @param array{chat_id: int, file: array{data: string, filename: string, content_type: string}, caption?: string} $params
     */
    public function sendPhoto(array $params): SendMediaResult
    {
        return SendMediaResult::fromArray($this->sendMedia('/sendPhoto', $params));
    }

    /**
     * Send a video.
     *
     * @param array{chat_id: int, file: array{data: string, filename: string, content_type: string}, caption?: string} $params
     */
    public function sendVideo(array $params): SendMediaResult
    {
        return SendMediaResult::fromArray($this->sendMedia('/sendVideo', $params));
    }

    /**
     * Send a document.
     *
     * @param array{chat_id: int, file: array{data: string, filename: string, content_type: string}, caption?: string} $params
     */
    public function sendDocument(array $params): SendMediaResult
    {
        return SendMediaResult::fromArray($this->sendMedia('/sendDocument', $params));
    }

    /**
     * Send an audio file.
     *
     * @param array{chat_id: int, file: array{data: string, filename: string, content_type: string}, caption?: string} $params
     */
    public function sendAudio(array $params): SendMediaResult
    {
        return SendMediaResult::fromArray($this->sendMedia('/sendAudio', $params));
    }

    /**
     * Send a carousel.
     *
     * @param array{
     *   chat_id: int,
     *   text: string,
     *   carousel: array<array{id: string, title: string, subtitle?: string, button_text?: string}>,
     *   quick_reply_buttons?: string[],
     * } $params
     */
    public function sendCarousel(array $params): SendCarouselResult
    {
        $body = [
            'chat_id'  => $params['chat_id'],
            'text'     => $params['text'],
            'carousel' => $params['carousel'],
        ];
        if (isset($params['quick_reply_buttons'])) {
            $body['quick_reply_buttons'] = $params['quick_reply_buttons'];
        }
        return SendCarouselResult::fromArray($this->http->post($this->base . '/sendCarousel', $body));
    }

    /**
     * Show or hide the typing indicator.
     *
     * @param array{chat_id: int, is_typing?: bool} $params
     */
    public function sendTyping(array $params): TypingResult
    {
        $isTyping = $params['is_typing'] ?? true;
        return TypingResult::fromArray($this->http->post($this->base . '/sendTyping', [
            'chat_id'   => $params['chat_id'],
            'is_typing' => $isTyping,
        ]));
    }

    /**
     * Edit a message's text and/or inline keyboard.
     *
     * @param array{chat_id: int, message_id: int, new_text?: string, new_extra_data?: array} $params
     */
    public function edit(array $params): EditMessageResult
    {
        $body = ['chat_id' => $params['chat_id'], 'message_id' => $params['message_id']];
        if (isset($params['new_text'])) {
            $body['new_text'] = $params['new_text'];
        }
        if (isset($params['new_extra_data'])) {
            $body['new_extra_data'] = $params['new_extra_data'];
        }
        return EditMessageResult::fromArray($this->http->post($this->base . '/editMessage', $body));
    }

    /**
     * Delete a message.
     *
     * @param array{chat_id: int, message_id: int} $params
     */
    public function delete(array $params): DeleteResult
    {
        return DeleteResult::fromArray($this->http->post($this->base . '/deleteMessage', [
            'chat_id'    => $params['chat_id'],
            'message_id' => $params['message_id'],
        ]));
    }

    private function sendMedia(string $path, array $params): array
    {
        $fields = ['chat_id' => (string) $params['chat_id']];
        if (isset($params['caption'])) {
            $fields['caption'] = $params['caption'];
        }
        return $this->http->postMultipart($this->base . $path, $fields, $params['file']);
    }
}
