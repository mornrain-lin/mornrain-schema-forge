<?php
/**
 * Schema.org 常用枚举值常量。
 *
 * 仅收录搜索引擎富媒体结果实际支持的枚举子集，避免引入完整 schema.org
 * 词表带来的维护成本。所有值为 schema.org 官方定义的小写驼峰形式。
 *
 * @package MornRain\SchemaForge
 */

declare(strict_types=1);

namespace MornRain\SchemaForge;

/**
 * 枚举值定义。
 */
final class Enum
{
    /* ---------- Availability ---------- */
    public const AVAILABILITY_IN_STOCK         = 'https://schema.org/InStock';
    public const AVAILABILITY_OUT_OF_STOCK     = 'https://schema.org/OutOfStock';
    public const AVAILABILITY_PRE_ORDER        = 'https://schema.org/PreOrder';
    public const AVAILABILITY_BACK_ORDER       = 'https://schema.org/BackOrder';
    public const AVAILABILITY_DISCONTINUED     = 'https://schema.org/Discontinued';
    public const AVAILABILITY_IN_STORE_ONLY    = 'https://schema.org/InStoreOnly';
    public const AVAILABILITY_LIMITED_AVAILABILITY = 'https://schema.org/LimitedAvailability';
    public const AVAILABILITY_ONLINE_ONLY      = 'https://schema.org/OnlineOnly';
    public const AVAILABILITY_SOLD_OUT         = 'https://schema.org/SoldOut';

    /* ---------- ItemCondition ---------- */
    public const CONDITION_NEW                 = 'https://schema.org/NewCondition';
    public const CONDITION_USED                = 'https://schema.org/UsedCondition';
    public const CONDITION_REFURBISHED         = 'https://schema.org/RefurbishedCondition';
    public const CONDITION_DAMAGED             = 'https://schema.org/DamagedCondition';

    /* ---------- EventAttendanceMode ---------- */
    public const ATTENDANCE_OFFLINE            = 'https://schema.org/OfflineEventAttendanceMode';
    public const ATTENDANCE_ONLINE             = 'https://schema.org/OnlineEventAttendanceMode';
    public const ATTENDANCE_MIXED              = 'https://schema.org/MixedEventAttendanceMode';

    /* ---------- EventStatus ---------- */
    public const EVENT_STATUS_SCHEDULED        = 'https://schema.org/EventScheduled';
    public const EVENT_STATUS_CANCELLED        = 'https://schema.org/EventCancelled';
    public const EVENT_STATUS_POSTPONED        = 'https://schema.org/EventPostponed';
    public const EVENT_STATUS_SOLD_OUT         = 'https://schema.org/EventSoldOut';

    /* ---------- DayOfWeek ---------- */
    public const DAY_MONDAY    = 'Monday';
    public const DAY_TUESDAY   = 'Tuesday';
    public const DAY_WEDNESDAY = 'Wednesday';
    public const DAY_THURSDAY  = 'Thursday';
    public const DAY_FRIDAY    = 'Friday';
    public const DAY_SATURDAY  = 'Saturday';
    public const DAY_SUNDAY    = 'Sunday';

    /* ---------- Review / AggregateRating ---------- */
    public const RATING_VALID      = 'https://schema.org/Valid';
    public const RATING_INVALID    = 'https://schema.org/Invalid';
    public const REVIEW_POSITIVE   = 'https://schema.org/Positive';
    public const REVIEW_NEGATIVE   = 'https://schema.org/Negative';
    public const REVIEW_NEUTRAL    = 'https://schema.org/Neutral';

    /* ---------- Comment ---------- */
    public const COMMENT_ALLOWED   = 'https://schema.org/CommentAllowed';
    public const COMMENT_BLOCKED   = 'https://schema.org/CommentBlocked';

    /* ---------- Payment ---------- */
    public const PAYMENT_CREDIT_CARD  = 'https://schema.org/CreditCard';
    public const PAYMENT_CASH         = 'https://schema.org/Cash';
    public const PAYMENT_INVOICE      = 'https://schema.org/Invoice';
    public const PAYMENT_CHECK        = 'https://schema.org/Check';
    public const PAYMENT_BANK_TRANSFER = 'https://schema.org/BankTransfer';

    /* ---------- Speakable / MediaObject ---------- */
    public const MEDIA_CONTENT      = 'https://schema.org/ContentUrl';
    public const MEDIA_EMBEDDED     = 'https://schema.org/EmbedUrl';
    public const MEDIA_THUMBNAIL    = 'https://schema.org/ThumbnailUrl';

    /* ---------- SpeakableProperty ---------- */
    public const SPEAKABLE           = 'SpeakableSpecification';
    public const SPEAKABLE_WEBSITE   = 'xpath/text()';

    /* ---------- Misc ---------- */
    public const CURRENCY_USD = 'USD';
    public const CURRENCY_CNY = 'CNY';
    public const CURRENCY_EUR = 'EUR';
    public const CURRENCY_JPY = 'JPY';
    public const CURRENCY_GBP = 'GBP';

    /**
     * 库存状态允许值集合。
     *
     * @return array<int,string>
     */
    public static function availability(): array
    {
        return [
            self::AVAILABILITY_IN_STOCK,
            self::AVAILABILITY_OUT_OF_STOCK,
            self::AVAILABILITY_PRE_ORDER,
            self::AVAILABILITY_BACK_ORDER,
            self::AVAILABILITY_DISCONTINUED,
            self::AVAILABILITY_IN_STORE_ONLY,
            self::AVAILABILITY_LIMITED_AVAILABILITY,
            self::AVAILABILITY_ONLINE_ONLY,
            self::AVAILABILITY_SOLD_OUT,
        ];
    }

    /**
     * 商品成色允许值集合。
     *
     * @return array<int,string>
     */
    public static function condition(): array
    {
        return [
            self::CONDITION_NEW,
            self::CONDITION_USED,
            self::CONDITION_REFURBISHED,
            self::CONDITION_DAMAGED,
        ];
    }

    /**
     * 活动参与方式允许值集合。
     *
     * @return array<int,string>
     */
    public static function attendanceMode(): array
    {
        return [
            self::ATTENDANCE_OFFLINE,
            self::ATTENDANCE_ONLINE,
            self::ATTENDANCE_MIXED,
        ];
    }

    /**
     * 活动状态允许值集合。
     *
     * @return array<int,string>
     */
    public static function eventStatus(): array
    {
        return [
            self::EVENT_STATUS_SCHEDULED,
            self::EVENT_STATUS_CANCELLED,
            self::EVENT_STATUS_POSTPONED,
            self::EVENT_STATUS_SOLD_OUT,
        ];
    }

    /**
     * 星期允许值集合。
     *
     * @return array<int,string>
     */
    public static function dayOfWeek(): array
    {
        return [
            self::DAY_MONDAY,
            self::DAY_TUESDAY,
            self::DAY_WEDNESDAY,
            self::DAY_THURSDAY,
            self::DAY_FRIDAY,
            self::DAY_SATURDAY,
            self::DAY_SUNDAY,
        ];
    }

    /**
     * 按分组读取枚举。
     *
     * @param string $group 分组名。
     * @return array<int,string>
     */
    public static function group(string $group): array
    {
        switch ($group) {
            case 'availability':
                return self::availability();
            case 'condition':
                return self::condition();
            case 'attendanceMode':
                return self::attendanceMode();
            case 'eventStatus':
                return self::eventStatus();
            case 'dayOfWeek':
                return self::dayOfWeek();
            default:
                return [];
        }
    }
}
