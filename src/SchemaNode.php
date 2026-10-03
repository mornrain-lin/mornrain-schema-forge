<?php
/**
 * 可链式设置的 Schema 节点。
 *
 * 每个实例代表一个 JSON-LD 对象。提供两种设置风格：
 *
 * 通用式（属性名任意）：
 *   $node->set('headline', '标题')->set('datePublished', '2026-10-02');
 *
 * 领域语义式（自动做类型推断与格式归一）：
 *   $node->name('标题')->datePublished('2026-10-02')->image('/a.jpg');
 *
 * 所有 set* 方法都会跳过 null / 空字符串，避免输出无意义的空节点。
 *
 * @package MornRain\SchemaForge
 */

declare(strict_types=1);

namespace MornRain\SchemaForge;

use InvalidArgumentException;

/**
 * Schema 节点。
 */
class SchemaNode
{
    /** @var string @context */
    protected $context;

    /** @var array<int,string> @type 列表（第一个为主类型） */
    protected $types = [];

    /** @var array<string,mixed> 属性表 */
    protected $properties = [];

    /**
     * 构造函数。
     *
     * @param string $context @context 值。
     */
    public function __construct(string $context = 'https://schema.org')
    {
        $this->context = $context;
    }

    /* ================================================================
     *  链式设置
     * ================================================================ */

    /**
     * 设置属性。
     *
     * @param string $key   属性名。
     * @param mixed  $value 属性值，传 null 或空串会被忽略（数组空值同样忽略）。
     */
    public function set(string $key, $value): self
    {
        $key = trim($key);
        if ($key === '') {
            return $this;
        }
        if ($value === null) {
            return $this;
        }
        if (is_string($value) && trim($value) === '') {
            return $this;
        }
        if (is_array($value) && $value === []) {
            return $this;
        }

        $this->properties[$key] = $this->normalize($key, $value);

        return $this;
    }

    /**
     * 批量设置属性。
     *
     * @param array<string,mixed> $data 属性表。
     */
    public function setMany(array $data): self
    {
        foreach ($data as $key => $value) {
            $this->set((string) $key, $value);
        }

        return $this;
    }

    /**
     * 一次性替换全部属性。
     *
     * @param array<string,mixed> $data 属性表。
     */
    public function replace(array $data): self
    {
        $this->properties = [];
        foreach ($data as $key => $value) {
            if (strpos((string) $key, '@') === 0) {
                continue;
            }
            $this->set((string) $key, $value);
        }

        return $this;
    }

    /**
     * 移除属性。
     *
     * @param string $key 属性名。
     */
    public function remove(string $key): self
    {
        unset($this->properties[$key]);

        return $this;
    }

    /**
     * 读取属性。
     *
     * @param string $key     属性名。
     * @param mixed  $default 缺省值。
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return $this->properties[$key] ?? $default;
    }

    /**
     * 是否已设置属性。
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->properties);
    }

    /**
     * 移除全部属性（保留 @type 与 @context）。
     */
    public function clear(): self
    {
        $this->properties = [];

        return $this;
    }

    /**
     * 设置 @type。
     *
     * @param string            $type        主类型。
     * @param array<int,string> $extraTypes  附加类型。
     */
    public function setType(string $type, array $extraTypes = []): self
    {
        $type = trim($type);
        if ($type === '') {
            throw new InvalidArgumentException('@type 不能为空');
        }
        if (!Type::exists($type)) {
            throw new InvalidArgumentException(
                sprintf('未知的 schema.org 类型「%s」，可用 Type 常量或参见 schema.org 官网。', $type)
            );
        }

        $this->types = [$type];
        foreach ($extraTypes as $extra) {
            $extra = trim((string) $extra);
            if ($extra !== '' && Type::exists($extra) && !in_array($extra, $this->types, true)) {
                $this->types[] = $extra;
            }
        }

        return $this;
    }

    /**
     * 追加 @type。
     */
    public function addType(string $type): self
    {
        $type = trim($type);
        if ($type === '' || !Type::exists($type) || in_array($type, $this->types, true)) {
            return $this;
        }
        $this->types[] = $type;

        return $this;
    }

    /**
     * 取得 @type 列表。
     *
     * @return array<int,string>
     */
    public function types(): array
    {
        return $this->types;
    }

    /**
     * 取得主类型。
     */
    public function primaryType(): string
    {
        return $this->types[0] ?? '';
    }

    /**
     * 设置 @id。
     */
    public function id(string $id): self
    {
        $id = trim($id);
        if ($id === '') {
            return $this;
        }

        $this->properties['@id'] = $id;

        return $this;
    }

    /**
     * 设置 @context（覆盖默认）。
     */
    public function context(string $context): self
    {
        $context = trim($context);
        if ($context !== '') {
            $this->context = $context;
        }

        return $this;
    }

    /* ================================================================
     *  领域语义快捷方法
     * ================================================================ */

    /**
     * 名称 / 标题。
     */
    public function name(string $name): self
    {
        return $this->set('name', $name);
    }

    /**
     * 标题（Article / VideoObject 用 headline）。
     */
    public function headline(string $headline): self
    {
        return $this->set('headline', $headline);
    }

    /**
     * 描述。
     */
    public function description(string $description): self
    {
        return $this->set('description', $this->clip($description, 5000));
    }

    /**
     * URL。
     */
    public function url(string $url): self
    {
        return $this->set('url', $this->absolutize($url));
    }

    /**
     * 图片。
     *
     * @param string|array<string,string>|array<int,array<string,string>> $image 图片 URL 或 ImageObject。
     */
    public function image($image): self
    {
        if (is_string($image)) {
            return $this->set('image', $this->absolutize($image));
        }
        if (is_array($image) && $image !== []) {
            return $this->set('image', $this->normalize('image', $image));
        }

        return $this;
    }

    /**
     * 发布日期，自动归一为 ISO 8601。
     *
     * 接受 'now'、时间戳、DateTime 或字符串。
     *
     * @param mixed $date 日期。
     */
    public function datePublished($date): self
    {
        return $this->set('datePublished', $this->toIso8601($date));
    }

    /**
     * 修改日期。
     *
     * @param mixed $date 日期。
     */
    public function dateModified($date): self
    {
        return $this->set('dateModified', $this->toIso8601($date));
    }

    /**
     * 作者（Person 或 Organization 节点）。
     *
     * @param string               $name  作者名。
     * @param array<string,mixed>  $extra 作者附加属性。
     */
    public function author(string $name, array $extra = []): self
    {
        $name = trim($name);
        if ($name === '') {
            return $this;
        }

        $author = array_merge(['@type' => Type::PERSON, 'name' => $name], $extra);

        return $this->set('author', $author);
    }

    /**
     * 发布者。
     *
     * @param string              $name  发布者名。
     * @param array<string,mixed> $extra 附加属性。
     */
    public function publisher(string $name, array $extra = []): self
    {
        $name = trim($name);
        if ($name === '') {
            return $this;
        }

        $publisher = array_merge(['@type' => Type::ORGANIZATION, 'name' => $name], $extra);

        return $this->set('publisher', $publisher);
    }

    /**
     * 语言。
     */
    public function inLanguage(string $language): self
    {
        $language = trim($language);
        if ($language === '') {
            return $this;
        }

        return $this->set('inLanguage', $language);
    }

    /**
     * 站点信息（WebPage 的 isPartOf / breadcrumb 的常见搭配）。
     *
     * @param string              $name 站点名。
     * @param array<string,mixed> $extra 附加属性。
     */
    public function partOfWebsite(string $name, array $extra = []): self
    {
        $name = trim($name);
        if ($name === '') {
            return $this;
        }

        $site = array_merge(['@type' => Type::WEB_SITE, 'name' => $name], $extra);

        return $this->set('isPartOf', $site);
    }

    /**
     * 主实体（WebPage 指向被描述的页面）。
     *
     * @param string $url    页面 URL。
     * @param string $type   页面类型。
     */
    public function mainEntityOfPage(string $url, string $type = Type::WEB_PAGE): self
    {
        $url = $this->absolutize($url);
        if ($url === '') {
            return $this;
        }

        // @type 会原样进入 JSON-LD，非法类型会让搜索引擎丢弃整个节点，
        // 因此这里与 setType() 保持同样的校验，而不是默默接受。
        $type = trim($type);
        if (!Type::exists($type)) {
            throw new InvalidArgumentException(
                sprintf('未知的 schema.org 类型「%s」，请使用 Type 常量。', $type)
            );
        }

        // Google 推荐 WebPage 的 mainEntityOfPage 写成带 @id 的最小节点。
        return $this->set('mainEntityOfPage', ['@type' => $type, '@id' => $url]);
    }

    /**
     * 评分（AggregateRating）。
     *
     * @param float $rating 评分值。
     * @param int   $count  评价数量。
     * @param float $best   最高分。
     */
    public function aggregateRating(float $rating, int $count, float $best = 5.0): self
    {
        if (is_nan($rating) || is_infinite($rating) || is_nan($best) || is_infinite($best)) {
            throw new InvalidArgumentException('评分值不得为 NaN 或 INF。');
        }
        if ($best <= 0) {
            $best = 5.0;
        }
        $rating = max(0.0, min($best, $rating));

        return $this->set('aggregateRating', [
            '@type'       => Type::AGGREGATE_RATING,
            'ratingValue' => round($rating, 2),
            'ratingCount' => max(0, $count),
            'bestRating'  => $best,
            'worstRating' => 1,
        ]);
    }

    /**
     * 报价（Offer）。
     *
     * @param float  $price    价格。
     * @param string $currency 货币代码。
     * @param string $url      购买链接。
     * @param string $availability 库存枚举，见 Enum::availability()。
     */
    public function offer(float $price, string $currency = 'CNY', string $url = '', string $availability = ''): self
    {
        $currency = strtoupper(trim($currency));
        if (strlen($currency) !== 3) {
            $currency = 'CNY';
        }

        $offer = [
            '@type'         => Type::OFFER,
            'price'         => round(max(0.0, $price), 2),
            'priceCurrency' => $currency,
        ];

        if ($url !== '') {
            $offer['url'] = $this->absolutize($url);
        }
        if ($availability !== '' && in_array($availability, Enum::availability(), true)) {
            $offer['availability'] = $availability;
        }

        return $this->set('offers', $offer);
    }

    /**
     * 地理位置。
     *
     * @param float  $latitude  纬度。
     * @param float  $longitude 经度。
     */
    public function geo(float $latitude, float $longitude): self
    {
        // NaN / INF 无法被 json_encode，交给它们会把整个节点输出成空。
        // 这里直接拒绝，交由调用方修正数据源头。
        if (is_nan($latitude) || is_nan($longitude) || is_infinite($latitude) || is_infinite($longitude)) {
            throw new InvalidArgumentException('经纬度不能为 NaN 或 INF。');
        }

        // 超出合法区间通常意味着单位搞错（如传了毫度），静默接受会产生无效数据。
        if ($latitude < -90.0 || $latitude > 90.0) {
            throw new InvalidArgumentException('纬度须在 -90 到 90 之间，收到：' . $latitude);
        }
        if ($longitude < -180.0 || $longitude > 180.0) {
            throw new InvalidArgumentException('经度须在 -180 到 180 之间，收到：' . $longitude);
        }

        return $this->set('geo', [
            '@type'     => Type::GEO_COORDINATES,
            'latitude'  => round($latitude, 6),
            'longitude' => round($longitude, 6),
        ]);
    }

    /**
     * 营业时间。
     *
     * @param string $day       星期，见 Enum::dayOfWeek()。
     * @param string $opens     开门时间 HH:MM。
     * @param string $closes    关门时间 HH:MM。
     */
    public function openingHours(string $day, string $opens, string $closes): self
    {
        if (!in_array($day, Enum::dayOfWeek(), true)) {
            return $this;
        }
        $timePattern = '/^([01]\d|2[0-3]):[0-5]\d$/';
        if (preg_match($timePattern, $opens) !== 1 || preg_match($timePattern, $closes) !== 1) {
            return $this;
        }

        return $this->set('openingHoursSpecification', [
            '@type'     => Type::OPENING_HOURS,
            'dayOfWeek' => $day,
            'opens'     => $opens,
            'closes'    => $closes,
        ]);
    }

    /**
     * 面包屑（WebPage 专用，与 SchemaForge::breadcrumb() 生成的节点配合）。
     *
     * @param array<string,mixed> $breadcrumbNode breadcrumb 节点数据。
     */
    public function breadcrumb(array $breadcrumbNode): self
    {
        return $this->set('breadcrumb', $breadcrumbNode);
    }

    /**
     * SEO 关键字（inKeywords）。
     *
     * @param array<int,string> $keywords 关键词列表。
     */
    public function keywords(array $keywords): self
    {
        $list = [];
        foreach ($keywords as $keyword) {
            $keyword = trim((string) $keyword);
            if ($keyword !== '') {
                $list[] = $keyword;
            }
        }
        if ($list === []) {
            return $this;
        }

        return $this->set('keywords', implode(', ', $list));
    }

    /**
     * 文章正文。
     *
     * @param string $html HTML 正文（调用方需自行完成 HTML 清洗）。
     */
    public function articleBody(string $html): self
    {
        return $this->set('articleBody', $html);
    }

    /**
     * 追加一个同类节点到列表型属性（如 step、itemListElement、recipeIngredient）。
     *
     * @param string $property 属性名。
     * @param mixed  $item     追加项。
     */
    public function push(string $property, $item): self
    {
        if ($item === null || $item === '' || $item === []) {
            return $this;
        }

        $current = $this->properties[$property] ?? [];
        if (!is_array($current)) {
            $current = [$current];
        }
        $current[] = $this->normalize($property, $item);

        $this->properties[$property] = $current;

        return $this;
    }

    /**
     * 合并数组型属性（去重）。
     *
     * @param string           $property 属性名。
     * @param array<int,mixed> $items    追加项列表。
     */
    public function merge(string $property, array $items): self
    {
        foreach ($items as $item) {
            $this->push($property, $item);
        }

        return $this;
    }

    /* ================================================================
     *  输出
     * ================================================================ */

    /**
     * 转为可 json_encode 的数组。
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        $data = ['@context' => $this->context];

        if ($this->types !== []) {
            $data['@type'] = count($this->types) === 1 ? $this->types[0] : $this->types;
        }

        foreach ($this->properties as $key => $value) {
            $data[$key] = $value;
        }

        return $data;
    }

    /**
     * 转为 JSON 字符串。
     */
    public function toJson(int $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES): string
    {
        $json = json_encode($this->toArray(), $flags);

        return $json === false ? '{}' : $json;
    }

    /**
     * 克隆当前节点。
     */
    public function copy(): self
    {
        $clone = new self($this->context);
        $clone->types      = $this->types;
        $clone->properties = $this->properties;

        return $clone;
    }

    /* ================================================================
     *  内部工具
     * ================================================================ */

    /**
     * 属性值归一化。
     *
     * @param string $key   属性名。
     * @param mixed  $value 原始值。
     * @return mixed
     */
    protected function normalize(string $key, $value)
    {
        if (is_array($value)) {
            // 数字键视为列表，字符串键视为对象，递归处理。
            $out = [];
            foreach ($value as $subKey => $subValue) {
                $out[$subKey] = is_array($subValue)
                    ? $this->normalize($key . '.' . $subKey, $subValue)
                    : $this->normalizeScalar($key, $subValue);
            }

            return $out;
        }

        return $this->normalizeScalar($key, $value);
    }

    /**
     * 标量值归一化。
     *
     * @param string $key   属性名。
     * @param mixed  $value 原始值。
     * @return mixed
     */
    protected function normalizeScalar(string $key, $value)
    {
        if (is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }
        if (!is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);

        switch ($key) {
            case 'url':
            case 'contentUrl':
            case 'embedUrl':
            case 'thumbnailUrl':
            case 'targetUrl':
                return $this->absolutize($trimmed);
            case 'datePublished':
            case 'dateModified':
            case 'dateCreated':
            case 'uploadDate':
            case 'startDate':
            case 'endDate':
            case 'expires':
                return $this->toIso8601($trimmed);
            case 'inLanguage':
                return $trimmed;
            case 'priceCurrency':
                return strtoupper($trimmed);
            default:
                return $value;
        }
    }

    /**
     * 相对地址补全为绝对地址。
     *
     * 非 http/https 协议（mailto、tel 等）原样返回。
     */
    protected function absolutize(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if ($scheme !== '' && $scheme !== 'http' && $scheme !== 'https') {
            return $url;
        }

        if (strpos($url, '//') === 0) {
            return 'https:' . $url;
        }

        if ($scheme === 'http' || $scheme === 'https') {
            return $url;
        }

        if (strpos($url, '/') === 0) {
            return $this->guessBaseUrl() . $url;
        }

        return $url;
    }

    /**
     * 推测站点根地址。
     *
     * 优先使用 WordPress 的 home_url()，否则从 $_SERVER 推断，
     * 两者都不可用时返回空串（保持相对路径原样）。
     */
    protected function guessBaseUrl(): string
    {
        if (function_exists('home_url')) {
            return rtrim((string) home_url('/'), '/');
        }
        if (function_exists('site_url')) {
            return rtrim((string) site_url('/'), '/');
        }

        $https = isset($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
        $host  = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '';
        if ($host === '' || !preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $host)) {
            return '';
        }

        return ($https ? 'https://' : 'http://') . $host;
    }

    /**
     * 归一为 ISO 8601 日期 / 日期时间。
     *
     * @param mixed $date 输入。
     */
    protected function toIso8601($date): string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d\TH:i:sP');
        }
        if (is_int($date)) {
            return date('Y-m-d\TH:i:sP', $date);
        }
        if (!is_string($date)) {
            return '';
        }

        $date = trim($date);
        if ($date === '' || strtolower($date) === 'now') {
            return date('Y-m-d\TH:i:sP');
        }

        // 已是合法 ISO 8601 写法。
        if (preg_match('/^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+\-]\d{2}:?\d{2})?)?$/', $date) === 1) {
            // 纯日期补 00:00:00，符合 Google 对 datePublished 的建议。
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
                return $date . 'T00:00:00' . $this->utcOffset();
            }

            // 空格分隔的日期时间统一改用 T，并补齐秒与时区偏移。
            $normalized = str_replace(' ', 'T', $date);
            if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $normalized) === 1) {
                $normalized .= ':00';
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?$/', $normalized) === 1) {
                $normalized .= $this->utcOffset();
            }

            return $normalized;
        }

        // 常见非标准写法。
        $replacements = [
            '/' => '-',
            '年' => '-',
            '月' => '-',
            '日' => '',
            '  ' => 'T',
        ];
        $candidate = strtr($date, $replacements);
        $candidate = trim($candidate);
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})T?(\d{1,2}):(\d{2})(:(\d{2}))?$/', $candidate, $m) === 1) {
            return sprintf(
                '%04d-%02d-%02dT%02d:%02d:%02d%s',
                (int) $m[1],
                (int) $m[2],
                (int) $m[3],
                (int) $m[4],
                (int) $m[5],
                isset($m[7]) ? (int) $m[7] : 0,
                $this->utcOffset()
            );
        }

        $timestamp = strtotime($date);

        return $timestamp === false ? $date : date('Y-m-d\TH:i:sP', $timestamp);
    }

    /**
     * 当前站点时区偏移。
     */
    protected function utcOffset(): string
    {
        $offset = date('P');

        return $offset === '+00:00' ? '+00:00' : $offset;
    }

    /**
     * 截断过长文本。
     */
    protected function clip(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length);
    }
}
