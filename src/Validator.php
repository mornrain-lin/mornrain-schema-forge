<?php
/**
 * Schema 数据校验器。
 *
 * 三层校验：
 * 1. 必填属性校验（各类型必填字段表）；
 * 2. 属性值类型校验（URL / ISO8601 日期 / 数值范围 / 枚举值 / 语言标记等）；
 * 3. 输出前自检（@context 存在、@type 合法、无空值与危险字符）。
 *
 * 校验失败不抛异常（避免中断页面渲染），而是通过返回错误清单让调用方决定处理方式。
 *
 * @package MornRain\SchemaForge
 */

declare(strict_types=1);

namespace MornRain\SchemaForge;

/**
 * 校验器。
 */
class Validator
{
    /** 错误级别：提示 */
    public const LEVEL_NOTICE = 'notice';

    /** 错误级别：警告 */
    public const LEVEL_WARNING = 'warning';

    /** 错误级别：错误 */
    public const LEVEL_ERROR = 'error';

    /** @var bool 严格模式下警告也视为失败 */
    protected $strict = false;

    /** @var array<int,array{level:string,type:string,message:string,path:string}> 已记录的问题 */
    protected $issues = [];

    /**
     * 各类型的必填属性表。
     *
     * @return array<string,array<int,string>>
     */
    public static function requiredProperties(): array
    {
        return [
            Type::ARTICLE      => ['headline', 'datePublished'],
            Type::BLOG_POSTING => ['headline', 'datePublished'],
            Type::NEWS_ARTICLE => ['headline', 'datePublished'],
            Type::WEB_PAGE     => ['name', 'url'],
            Type::WEB_SITE     => ['name', 'url'],
            Type::ORGANIZATION => ['name', 'url'],
            Type::PERSON       => ['name'],
            Type::LOCAL_BUSINESS => ['name', 'address'],
            Type::PRODUCT      => ['name'],
            Type::OFFER        => ['price', 'priceCurrency'],
            Type::REVIEW       => ['reviewRating'],
            Type::AGGREGATE_RATING => ['ratingValue', 'ratingCount'],
            Type::RECIPE       => ['name', 'recipeIngredient', 'recipeInstructions'],
            Type::VIDEO_OBJECT => ['name', 'description', 'thumbnailUrl', 'uploadDate'],
            Type::SOFTWARE_APPLICATION => ['name', 'applicationCategory', 'operatingSystem'],
            Type::EVENT        => ['name', 'startDate', 'location'],
            Type::HOW_TO       => ['name', 'step'],
            Type::BREADCRUMB_LIST => ['itemListElement'],
            Type::ITEM_LIST    => ['itemListElement'],
            Type::FAQPAGE      => ['mainEntity'],
            Type::QAPAGE       => ['mainEntity'],
            Type::IMAGE_OBJECT => ['contentUrl'],
        ];
    }

    /**
     * 属性名 → 期望的数据类型。
     *
     * @return array<string,string>
     */
    public static function propertyTypes(): array
    {
        return [
            'url'                => 'url',
            'sameAs'             => 'url',
            'contentUrl'         => 'url',
            'embedUrl'           => 'url',
            'thumbnailUrl'       => 'url|url',
            'image'              => 'url|imageObject',
            'logo'               => 'url|imageObject',
            'targetUrl'          => 'url',
            'mainEntityOfPage'   => 'url',
            'datePublished'      => 'date',
            'dateModified'       => 'date',
            'dateCreated'        => 'date',
            'uploadDate'         => 'date',
            'startDate'          => 'date',
            'endDate'            => 'date',
            'expires'            => 'date',
            'availabilityStarts' => 'date',
            'price'              => 'number',
            'lowPrice'           => 'number',
            'highPrice'          => 'number',
            'ratingValue'        => 'number',
            'ratingCount'        => 'integer',
            'reviewCount'        => 'integer',
            'interactionCount'   => 'integer',
            'duration'           => 'duration',
            'timeRequired'       => 'duration',
            'prepTime'           => 'duration',
            'cookTime'           => 'duration',
            'totalTime'          => 'duration',
            'recipeYield'        => 'text',
            'inLanguage'         => 'language',
            'articleBody'        => 'text',
            'description'        => 'text',
            'name'               => 'text',
            'headline'           => 'text',
            'priceCurrency'      => 'currency',
            'availability'       => 'availability',
            'itemCondition'      => 'condition',
            'eventAttendanceMode' => 'attendanceMode',
            'eventStatus'        => 'eventStatus',
        ];
    }

    /**
     * 严格模式：把警告升级为错误。
     */
    public function strict(bool $enabled = true): self
    {
        $this->strict = $enabled;
        $this->issues = [];

        return $this;
    }

    /**
     * 校验一个完整的 Schema 节点。
     *
     * @param array<string,mixed> $data     待校验数据。
     * @param string              $type     期望的类型（用于必填表查表）。
     * @return array<int,array{level:string,type:string,message:string,path:string}>
     */
    public function validate(array $data, string $type = ''): array
    {
        $this->issues = [];

        if ($data === []) {
            $this->add(self::LEVEL_ERROR, $type, 'Schema 节点为空，无法校验。', '$');

            return $this->issues;
        }

        $resolvedType = $type !== '' ? $type : $this->firstType($data);
        if ($resolvedType === '') {
            $this->add(self::LEVEL_ERROR, $type, '缺少 @type，无法确定 Schema 类型。', '@type');
        } elseif (Type::exists($resolvedType)) {
            $this->validateRequired($data, $resolvedType);
        } else {
            $this->add(
                self::LEVEL_WARNING,
                $resolvedType,
                sprintf('类型「%s」不在本库已知类型表中，必填校验已跳过。', $resolvedType),
                '@type'
            );
        }

        $this->validateProperties($data);
        $this->validateContext($data);

        return $this->issues;
    }

    /**
     * 仅做输出前自检：确保可以安全地 json_encode 并嵌入 <script>。
     *
     * @param array<string,mixed> $data 待输出数据。
     * @return array<int,array{level:string,type:string,message:string,path:string}>
     */
    public function preflight(array $data): array
    {
        $this->issues = [];

        if ($data === []) {
            $this->add(self::LEVEL_ERROR, '', '数据为空，输出将得到空的 JSON-LD 块。', '$');

            return $this->issues;
        }

        $this->validateContext($data);

        // 顶层 @type 缺失是最常见的低级错误，这里再确认一次。
        if ($this->firstType($data) === '') {
            $this->add(self::LEVEL_ERROR, '', '顶层节点缺少 @type。', '@type');
        }

        // 递归检查空字符串、NaN、INF 等无法安全编码的值。
        $this->scanEncodable($data, '$', 0);

        return $this->issues;
    }

    /**
     * 校验是否全部通过（不含警告）。
     *
     * @param array<int,array{level:string,type:string,message:string,path:string}> $issues 校验结果。
     */
    public function passes(array $issues): bool
    {
        foreach ($issues as $issue) {
            if ($issue['level'] === self::LEVEL_ERROR) {
                return false;
            }
            if ($this->strict && $issue['level'] === self::LEVEL_WARNING) {
                return false;
            }
        }

        return true;
    }

    /**
     * 人类可读的校验摘要。
     *
     * @param array<int,array{level:string,type:string,message:string,path:string}> $issues 校验结果。
     */
    public function summary(array $issues): string
    {
        if ($issues === []) {
            return '校验通过，未发现问题。';
        }

        $counts = [self::LEVEL_ERROR => 0, self::LEVEL_WARNING => 0, self::LEVEL_NOTICE => 0];
        foreach ($issues as $issue) {
            $counts[$issue['level']] = ($counts[$issue['level']] ?? 0) + 1;
        }

        $lines = [
            sprintf('共 %d 项：错误 %d，警告 %d，提示 %d。', count($issues), $counts[self::LEVEL_ERROR], $counts[self::LEVEL_WARNING], $counts[self::LEVEL_NOTICE]),
        ];
        foreach ($issues as $issue) {
            $lines[] = sprintf('  [%s] %s → %s', strtoupper($issue['level']), $issue['path'], $issue['message']);
        }

        return implode(PHP_EOL, $lines);
    }

    /**
     * 必填属性校验。
     *
     * @param array<string,mixed> $data Schema 数据。
     * @param string              $type 类型名。
     */
    protected function validateRequired(array $data, string $type): void
    {
        $required = self::requiredProperties();
        if (!isset($required[$type])) {
            return;
        }

        foreach ($required[$type] as $property) {
            $value = $data[$property] ?? null;
            if ($value === null || $value === '' || $value === []) {
                $this->add(
                    self::LEVEL_WARNING,
                    $type,
                    sprintf('「%s」类型缺少必填属性 %s。', $type, $property),
                    '$.' . $property
                );
            }
        }
    }

    /**
     * 逐个属性做类型校验。
     *
     * @param array<string,mixed> $data Schema 数据。
     */
    protected function validateProperties(array $data): void
    {
        $map = self::propertyTypes();
        foreach ($data as $key => $value) {
            if (!is_string($key) || strpos($key, '@') === 0) {
                continue;
            }
            if (!isset($map[$key])) {
                continue;
            }
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            // 仅校验标量：嵌套节点（数组）的内部结构由 SchemaNode 负责。
            if (!is_array($value)) {
                $this->validateValue($key, $map[$key], $value, '$.' . $key);
            }
        }
    }

    /**
     * 单个值按期望类型校验。
     *
     * @param string $property 属性名。
     * @param string $expected 期望类型（'|' 分隔表示二者之一）。
     * @param mixed  $value    实际值。
     * @param string $path     错误定位路径。
     */
    protected function validateValue(string $property, string $expected, $value, string $path): void
    {
        if (!is_scalar($value)) {
            return;
        }

        $accepted = explode('|', $expected);
        foreach ($accepted as $type) {
            if ($this->matchType($type, (string) $value)) {
                return;
            }
        }

        $this->add(
            self::LEVEL_NOTICE,
            '',
            sprintf(
                '属性 %s 的值「%s」不符合 %s 形式。',
                $property,
                is_bool($value) ? ($value ? 'true' : 'false') : (string) $value,
                $expected
            ),
            $path
        );
    }

    /**
     * 判断单个值是否满足指定类型。
     */
    protected function matchType(string $type, string $value): bool
    {
        switch ($type) {
            case 'url':
                return $this->isUrl($value);
            case 'url|imageObject':
                return $this->isUrl($value) || $value === '';
            case 'imageObject':
                return true; // 嵌套对象由结构校验负责
            case 'date':
                return $this->isIso8601($value);
            case 'number':
                return is_numeric($value);
            case 'integer':
                return preg_match('/^-?\d+$/', trim($value)) === 1;
            case 'duration':
                return preg_match('/^P(?!$)(\d+Y)?(\d+M)?(\d+W)?(\d+D)?(T(?=\d)(\d+H)?(\d+M)?(\d+S)?)?$/', $value) === 1;
            case 'language':
                return preg_match('/^[a-zA-Z]{2,3}(-[a-zA-Z0-9]{2,8})*$/', $value) === 1;
            case 'currency':
                return preg_match('/^[A-Z]{3}$/', $value) === 1;
            case 'availability':
                return in_array($value, Enum::availability(), true);
            case 'condition':
                return in_array($value, Enum::condition(), true);
            case 'attendanceMode':
                return in_array($value, Enum::attendanceMode(), true);
            case 'eventStatus':
                return in_array($value, Enum::eventStatus(), true);
            case 'text':
                return mb_strlen($value) <= 100000;
            default:
                return true;
        }
    }

    /**
     * @context 校验。
     *
     * @param array<string,mixed> $data Schema 数据。
     */
    protected function validateContext(array $data): void
    {
        if (!array_key_exists('@context', $data)) {
            $this->add(self::LEVEL_ERROR, '', '缺少 @context，搜索引擎将无法解析该结构化数据。', '@context');

            return;
        }

        $context = $data['@context'];
        if ($context !== 'https://schema.org' && $context !== 'http://schema.org') {
            $this->add(
                self::LEVEL_WARNING,
                '',
                '@context 建议使用 https://schema.org，当前值可能不被识别。',
                '@context'
            );
        }
    }

    /**
     * 递归检查可编码性与空值。
     *
     * @param array<mixed> $data 待检查数据。
     * @param string       $path 当前路径。
     * @param int          $depth 当前深度。
     */
    protected function scanEncodable(array $data, string $path, int $depth): void
    {
        if ($depth > 32) {
            $this->add(self::LEVEL_ERROR, '', '数据嵌套层级过深（>32），疑似存在循环引用。', $path);

            return;
        }

        foreach ($data as $key => $value) {
            $currentPath = $path . '.' . (is_string($key) ? $key : (string) $key);

            if (is_float($value) && (is_nan($value) || is_infinite($value))) {
                $this->add(self::LEVEL_ERROR, '', '存在 NaN 或 INF 浮点值，json_encode 会失败。', $currentPath);
                continue;
            }

            if (is_object($value) || is_resource($value)) {
                $this->add(self::LEVEL_ERROR, '', '存在不可序列化的对象或资源。', $currentPath);
                continue;
            }

            if (is_string($value) && trim($value) === '') {
                $this->add(self::LEVEL_NOTICE, '', '存在空字符串，建议移除以精简输出。', $currentPath);
                continue;
            }

            if (is_array($value)) {
                $this->scanEncodable($value, $currentPath, $depth + 1);
            }
        }
    }

    /**
     * 从数据中取出第一个 @type。
     *
     * @param array<string,mixed> $data Schema 数据。
     */
    protected function firstType(array $data): string
    {
        $type = $data['@type'] ?? '';
        if (is_string($type)) {
            return trim($type);
        }
        if (is_array($type)) {
            foreach ($type as $item) {
                if (is_string($item) && $item !== '') {
                    return $item;
                }
            }
        }

        return '';
    }

    /**
     * URL 合法性：仅允许 http/https，protocol-relative 补全。
     */
    protected function isUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }
        if (strpos($url, '//') === 0) {
            return true;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if ($scheme !== 'http' && $scheme !== 'https') {
            return false;
        }

        return (string) parse_url($url, PHP_URL_HOST) !== '';
    }

    /**
     * ISO 8601 日期 / 日期时间校验。
     */
    protected function isIso8601(string $value): bool
    {
        $pattern = '/^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+\-]\d{2}:?\d{2})?)?$/';

        return preg_match($pattern, trim($value)) === 1;
    }

    /**
     * 记录一条问题。
     */
    protected function add(string $level, string $type, string $message, string $path): void
    {
        $this->issues[] = [
            'level'   => $level,
            'type'    => $type,
            'message' => $message,
            'path'    => $path,
        ];
    }
}
