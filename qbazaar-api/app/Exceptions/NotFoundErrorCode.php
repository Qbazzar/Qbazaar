<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Ad;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\HelpArticle;
use App\Models\HelpCategory;
use App\Models\Location;
use App\Models\Message;
use App\Models\Offer;
use App\Models\Order;
use App\Models\Page;
use App\Models\SavedSearch;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Notifications\DatabaseNotification;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Picks the error code for a 404: the resource's own code when a route-model
 * binding missed, the generic NOT_FOUND for an unknown route or resource.
 */
final class NotFoundErrorCode
{
    /** @var array<class-string, ErrorCode> */
    private const array BY_MODEL = [
        Ad::class => ErrorCode::AD_NOT_FOUND,
        User::class => ErrorCode::USER_NOT_FOUND,
        Category::class => ErrorCode::CATEGORY_NOT_FOUND,
        Location::class => ErrorCode::LOCATION_NOT_FOUND,
        Offer::class => ErrorCode::OFFER_NOT_FOUND,
        Order::class => ErrorCode::ORDER_NOT_FOUND,
        Conversation::class => ErrorCode::MSG_CONVERSATION_NOT_FOUND,
        Message::class => ErrorCode::MSG_NOT_FOUND,
        SavedSearch::class => ErrorCode::SEARCH_SAVED_NOT_FOUND,
        Page::class => ErrorCode::CMS_PAGE_NOT_FOUND,
        HelpArticle::class => ErrorCode::HELP_ARTICLE_NOT_FOUND,
        HelpCategory::class => ErrorCode::HELP_CATEGORY_NOT_FOUND,
        SupportTicket::class => ErrorCode::TICKET_NOT_FOUND,
        UserAddress::class => ErrorCode::ADDRESS_NOT_FOUND,
        DatabaseNotification::class => ErrorCode::NOTIF_NOT_FOUND,
    ];

    public static function for(NotFoundHttpException $exception): ErrorCode
    {
        $previous = $exception->getPrevious();

        if (! $previous instanceof ModelNotFoundException) {
            return ErrorCode::NOT_FOUND;
        }

        foreach (self::BY_MODEL as $model => $code) {
            if (is_a($previous->getModel(), $model, true)) {
                return $code;
            }
        }

        return ErrorCode::NOT_FOUND;
    }
}
