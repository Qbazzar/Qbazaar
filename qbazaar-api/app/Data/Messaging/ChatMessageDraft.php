<?php

declare(strict_types=1);

namespace App\Data\Messaging;

use App\Enums\ChatMessageKey;
use App\Enums\MessageType;
use Illuminate\Http\UploadedFile;

/**
 * A chat message about to be written. System and offer bubbles carry a
 * translation key instead of fixed text so every reader sees them in their
 * own language.
 */
final readonly class ChatMessageDraft
{
    /**
     * @param array<string, scalar|null> $params
     */
    private function __construct(
        public MessageType $type,
        public string $body = '',
        public ?ChatMessageKey $key = null,
        public array $params = [],
        public ?UploadedFile $image = null,
    ) {}

    public static function text(string $body): self
    {
        return new self(MessageType::TEXT, $body);
    }

    public static function image(UploadedFile $image, ?string $caption): self
    {
        return new self(MessageType::IMAGE, $caption ?? '', image: $image);
    }

    /**
     * The inbox shows "Photo" in the reader's language for a photo sent
     * without a caption.
     */
    public function previewKey(): ?ChatMessageKey
    {
        return $this->type === MessageType::IMAGE && $this->body === '' ? ChatMessageKey::IMAGE : $this->key;
    }

    /**
     * @param array<string, scalar|null> $params
     */
    public static function system(ChatMessageKey $key, array $params = []): self
    {
        return new self(MessageType::SYSTEM, key: $key, params: $params);
    }

    public static function offer(ChatMessageKey $key, string $amount, string $currency, ?string $note): self
    {
        $params = ['amount' => $amount, 'currency' => $currency];

        if ($note !== null && $note !== '') {
            $params['note'] = $note;
        }

        return new self(MessageType::OFFER, key: $key, params: $params);
    }

    /**
     * The "Buy Now" card. `amount` is the total for all units, so the bubble
     * reads correctly in clients that do not render the card.
     */
    public static function purchaseRequest(string $total, string $currency, int $quantity, ?string $note): self
    {
        $params = ['amount' => $total, 'currency' => $currency, 'quantity' => $quantity];

        if ($note !== null && $note !== '') {
            $params['note'] = $note;
        }

        return new self(MessageType::PURCHASE_REQUEST, key: ChatMessageKey::PURCHASE_REQUEST_CREATED, params: $params);
    }
}
