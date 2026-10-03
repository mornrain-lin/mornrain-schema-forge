<?php
/**
 * Schema.org 类型常量与继承关系。
 *
 * 用「类常量 + 静态映射」描述 schema.org 常用类型体系，供 SchemaForge 门面
 * 做类型推导、@type 数组展开与属性归属判断。
 * 全部使用 PHP 7.4 兼容语法（不使用 enum）。
 *
 * @package MornRain\SchemaForge
 */

declare(strict_types=1);

namespace MornRain\SchemaForge;

/**
 * Schema.org 类型定义。
 */
final class Type
{
    /* ---------- 顶层与容器 ---------- */
    public const THING                = 'Thing';
    public const INTELLECTUAL_ENTITY  = 'IntellectualEntity';
    public const CREATIVE_WORK        = 'CreativeWork';
    public const WEB_PAGE             = 'WebPage';
    public const WEB_SITE             = 'WebSite';
    public const WEB_PAGE_ELEMENT     = 'WebPageElement';
    public const ITEM_LIST            = 'ItemList';
    public const LIST_ITEM            = 'ListItem';
    public const BREADCRUMB_LIST      = 'BreadcrumbList';
    public const ENTRY_POINT          = 'EntryPoint';

    /* ---------- 人物与组织 ---------- */
    public const PERSON               = 'Person';
    public const ORGANIZATION         = 'Organization';
    public const PLACE                = 'Place';
    public const LOCAL_BUSINESS       = 'LocalBusiness';
    public const POSTAL_ADDRESS       = 'PostalAddress';
    public const GEO_COORDINATES      = 'GeoCoordinates';
    public const OPENING_HOURS        = 'OpeningHoursSpecification';
    public const CONTACT_POINT        = 'ContactPoint';

    /* ---------- 文章与页面 ---------- */
    public const ARTICLE              = 'Article';
    public const NEWS_ARTICLE         = 'NewsArticle';
    public const TECH_ARTICLE         = 'TechArticle';
    public const BLOG_POSTING         = 'BlogPosting';
    public const LIVE_BLOG_POSTING    = 'LiveBlogPosting';
    public const SOCIAL_MEDIA_POSTING = 'SocialMediaPosting';
    public const DISCUSSION_FORUM_POSTING = 'DiscussionForumPosting';
    public const SPEECH               = 'Speech';
    public const BLOG                 = 'Blog';
    public const QAPAGE               = 'QAPage';
    public const FAQPAGE              = 'FAQPage';
    public const ABOUT_PAGE           = 'AboutPage';
    public const CONTACT_PAGE         = 'ContactPage';
    public const CHECKOUT_PAGE        = 'CheckoutPage';
    public const ITEM_PAGE            = 'ItemPage';
    public const COLLECTION_PAGE      = 'CollectionPage';
    public const PROFILE_PAGE         = 'ProfilePage';
    public const SEARCH_RESULTS_PAGE  = 'SearchResultsPage';

    /* ---------- 媒体 ---------- */
    public const MEDIA_OBJECT         = 'MediaObject';
    public const IMAGE_OBJECT         = 'ImageObject';
    public const AUDIO_OBJECT         = 'AudioObject';
    public const VIDEO_OBJECT         = 'VideoObject';
    public const CLIP                 = 'Clip';
    public const PODCAST_EPISODE      = 'PodcastEpisode';

    /* ---------- 商品与交易 ---------- */
    public const PRODUCT              = 'Product';
    public const BRAND                = 'Brand';
    public const OFFER                = 'Offer';
    public const REVIEW               = 'Review';
    public const AGGREGATE_RATING     = 'AggregateRating';
    public const ORDER                = 'Order';
    public const MONETARY_AMOUNT      = 'MonetaryAmount';
    public const DEMAND               = 'Demand';
    public const SHIPPING_DETAILS     = 'OfferShippingDetails';
    public const RETURN_POLICY        = 'MerchantReturnPolicy';
    public const WARRANTY             = 'WarrantyPromise';

    /* ---------- 活动 ---------- */
    public const EVENT                = 'Event';
    public const BUSINESS_EVENT       = 'BusinessEvent';
    public const ONLINE_EVENT         = 'OnlineEvent';
    public const EDUCATION_EVENT      = 'EducationEvent';
    public const MUSIC_EVENT          = 'MusicEvent';
    public const SPORTS_EVENT         = 'SportsEvent';
    public const THEATER_EVENT        = 'TheaterEvent';
    public const COMEDY_EVENT         = 'ComedyEvent';
    public const DANCE_EVENT          = 'DanceEvent';
    public const LITERARY_EVENT       = 'LiteraryEvent';
    public const SCREENING_EVENT      = 'ScreeningEvent';
    public const FESTIVAL             = 'Festival';
    public const TOUR                 = 'Tour';

    /* ---------- 菜谱与教程 ---------- */
    public const RECIPE               = 'Recipe';
    public const NUTRITION_INFORMATION = 'NutritionInformation';
    public const HOW_TO               = 'HowTo';
    public const HOW_TO_STEP          = 'HowToStep';
    public const HOW_TO_SECTION       = 'HowToSection';
    public const QUESTION             = 'Question';
    public const ANSWER               = 'Answer';

    /* ---------- 软件应用 ---------- */
    public const SOFTWARE_APPLICATION = 'SoftwareApplication';
    public const WEB_APPLICATION      = 'WebApplication';
    public const MOBILE_APPLICATION   = 'MobileApplication';

    /**
     * 类型继承关系。
     *
     * 键为子类型，值为父类型列表。仅收录本库实际会用到的关系。
     *
     * @return array<string,array<int,string>>
     */
    public static function hierarchy(): array
    {
        return [
            // 人物 / 组织
            self::PERSON         => [self::THING],
            self::ORGANIZATION   => [self::THING],
            self::PLACE          => [self::THING],
            self::LOCAL_BUSINESS => [self::ORGANIZATION, self::PLACE, self::THING],
            self::POSTAL_ADDRESS => [self::THING],
            self::GEO_COORDINATES => [self::THING],
            self::OPENING_HOURS  => [self::PLACE, self::THING],
            self::CONTACT_POINT  => [self::THING],

            // 文章
            self::ARTICLE      => [self::CREATIVE_WORK, self::INTELLECTUAL_ENTITY, self::THING],
            self::NEWS_ARTICLE => [self::ARTICLE],
            self::TECH_ARTICLE => [self::ARTICLE, self::INTELLECTUAL_ENTITY, self::THING],
            self::BLOG_POSTING => [self::ARTICLE],
            self::SOCIAL_MEDIA_POSTING => [self::ARTICLE, self::CREATIVE_WORK, self::INTELLECTUAL_ENTITY, self::THING],
            self::LIVE_BLOG_POSTING    => [self::BLOG_POSTING, self::ARTICLE],
            self::DISCUSSION_FORUM_POSTING => [self::ARTICLE, self::CREATIVE_WORK, self::INTELLECTUAL_ENTITY, self::THING],
            self::SPEECH               => [self::CREATIVE_WORK, self::INTELLECTUAL_ENTITY, self::THING],
            self::BLOG                 => [self::CREATIVE_WORK, self::INTELLECTUAL_ENTITY, self::THING],

            // 页面
            self::WEB_PAGE         => [self::CREATIVE_WORK, self::THING],
            self::QAPAGE            => [self::WEB_PAGE],
            self::FAQPAGE           => [self::WEB_PAGE, self::CREATIVE_WORK, self::THING],
            self::ABOUT_PAGE        => [self::WEB_PAGE],
            self::CONTACT_PAGE      => [self::WEB_PAGE],
            self::CHECKOUT_PAGE     => [self::WEB_PAGE],
            self::ITEM_PAGE         => [self::WEB_PAGE],
            self::COLLECTION_PAGE   => [self::WEB_PAGE],
            self::PROFILE_PAGE      => [self::WEB_PAGE],
            self::SEARCH_RESULTS_PAGE => [self::WEB_PAGE],

            // 站点
            self::WEB_SITE => [self::CREATIVE_WORK, self::THING],

            // 列表
            self::ITEM_LIST       => [self::INTELLECTUAL_ENTITY, self::THING],
            self::LIST_ITEM       => [self::INTELLECTUAL_ENTITY, self::THING],
            self::BREADCRUMB_LIST => [self::ITEM_LIST],

            // 媒体
            self::MEDIA_OBJECT    => [self::CREATIVE_WORK, self::INTELLECTUAL_ENTITY, self::THING],
            self::IMAGE_OBJECT    => [self::MEDIA_OBJECT, self::INTELLECTUAL_ENTITY, self::THING],
            self::AUDIO_OBJECT    => [self::MEDIA_OBJECT, self::CREATIVE_WORK, self::THING],
            self::VIDEO_OBJECT    => [self::MEDIA_OBJECT, self::CREATIVE_WORK, self::THING],
            self::CLIP            => [self::CREATIVE_WORK, self::INTELLECTUAL_ENTITY, self::THING],
            self::PODCAST_EPISODE => [self::CREATIVE_WORK, self::INTELLECTUAL_ENTITY, self::THING],

            // 商品
            self::PRODUCT          => [self::INTELLECTUAL_ENTITY, self::THING],
            self::BRAND            => [self::INTELLECTUAL_ENTITY, self::THING],
            self::OFFER            => [self::THING],
            self::REVIEW           => [self::THING],
            self::AGGREGATE_RATING => [self::THING],
            self::ORDER            => [self::THING],
            self::MONETARY_AMOUNT  => [self::THING],
            self::DEMAND           => [self::INTELLECTUAL_ENTITY, self::OFFER, self::THING],
            self::SHIPPING_DETAILS => [self::THING],
            self::RETURN_POLICY    => [self::THING],
            self::WARRANTY         => [self::THING],

            // 活动
            self::EVENT           => [self::THING],
            self::BUSINESS_EVENT  => [self::EVENT],
            self::ONLINE_EVENT    => [self::EVENT],
            self::EDUCATION_EVENT => [self::EVENT],
            self::MUSIC_EVENT     => [self::EVENT],
            self::SPORTS_EVENT    => [self::EVENT],
            self::THEATER_EVENT   => [self::EVENT],
            self::COMEDY_EVENT    => [self::EVENT],
            self::DANCE_EVENT     => [self::EVENT],
            self::LITERARY_EVENT  => [self::EVENT],
            self::SCREENING_EVENT => [self::EVENT],
            self::FESTIVAL        => [self::EVENT],
            self::TOUR            => [self::EVENT],

            // 菜谱 / 教程
            self::RECIPE                => [self::CREATIVE_WORK, self::INTELLECTUAL_ENTITY, self::THING],
            self::NUTRITION_INFORMATION => [self::THING],
            self::HOW_TO                => [self::CREATIVE_WORK, self::INTELLECTUAL_ENTITY, self::THING],
            self::HOW_TO_STEP           => [self::INTELLECTUAL_ENTITY, self::THING],
            self::HOW_TO_SECTION        => [self::HOW_TO_STEP, self::INTELLECTUAL_ENTITY, self::THING],
            self::QUESTION              => [self::THING],
            self::ANSWER                => [self::THING],

            // 软件
            self::SOFTWARE_APPLICATION => [self::INTELLECTUAL_ENTITY, self::THING],
            self::WEB_APPLICATION      => [self::SOFTWARE_APPLICATION, self::INTELLECTUAL_ENTITY, self::THING],
            self::MOBILE_APPLICATION   => [self::SOFTWARE_APPLICATION, self::INTELLECTUAL_ENTITY, self::THING],
        ];
    }

    /**
     * 全部已知类型名。
     *
     * @return array<int,string>
     */
    public static function all(): array
    {
        static $names = null;
        if ($names !== null) {
            return $names;
        }

        $constants  = (new \ReflectionClass(self::class))->getConstants();
        $names      = [];
        foreach ($constants as $value) {
            if (is_string($value) && $value !== '' && preg_match('/^[A-Z][A-Za-z]+$/', $value) === 1) {
                $names[] = $value;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * 类型是否存在于本库已知类型表中。
     */
    public static function exists(string $type): bool
    {
        return in_array($type, self::all(), true);
    }

    /**
     * 取得某类型及其全部祖先类型（广度优先，自身在首位）。
     *
     * @return array<int,string>
     */
    public static function ancestry(string $type): array
    {
        $hierarchy = self::hierarchy();
        $result    = [$type];
        $queue     = [$type];

        while ($queue !== []) {
            $current = array_shift($queue);
            foreach ($hierarchy[$current] ?? [] as $parent) {
                if (!in_array($parent, $result, true)) {
                    $result[] = $parent;
                    $queue[]  = $parent;
                }
            }
        }

        return $result;
    }

    /**
     * 判断子类型是否为给定类型的实例（含自身）。
     *
     * @param string $type    待判断的类型。
     * @param string $against 目标基类型。
     */
    public static function isA(string $type, string $against): bool
    {
        return in_array($against, self::ancestry($type), true);
    }
}
