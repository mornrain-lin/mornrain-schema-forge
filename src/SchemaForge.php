<?php
/**
 * SchemaForge 门面。
 *
 * 提供 13 个快捷构造方法（article / product / person / organization / website /
 * breadcrumb / faq / localBusiness / event / recipe / videoObject /
 * softwareApplication / howTo / itemList），每个都返回一个可链式设置的
 * SchemaNode，最后交由 Renderer 输出 JSON-LD。
 *
 * 设计原则：
 * - 链式调用不产生中间垃圾状态，每个节点都是独立对象；
 * - 只做「结构正确性」的兜底（必填补齐、@type 规范化、枚举值归一），
 *   不替调用方编造业务数据（如价格、评分）；
 * - 校验与渲染分离，便于单测与二次封装。
 *
 * @package MornRain\SchemaForge
 */

declare(strict_types=1);

namespace MornRain\SchemaForge;

use InvalidArgumentException;

/**
 * SchemaForge 门面。
 */
class SchemaForge
{
    /** @var Renderer 渲染器 */
    protected $renderer;

    /** @var Validator 校验器 */
    protected $validator;

    /** @var string 默认 @context */
    protected $context = 'https://schema.org';

    /** @var array<string,array<string,mixed>> 常用实体的 @id 注册表，用于站内引用去重 */
    protected $registry = [];

    /**
     * 构造函数。
     *
     * @param Renderer|null  $renderer 自定义渲染器。
     * @param Validator|null $validator 自定义校验器。
     */
    public function __construct(?Renderer $renderer = null, ?Validator $validator = null)
    {
        $this->renderer  = $renderer ?? new Renderer();
        $this->validator = $validator ?? new Validator();
    }

    /**
     * 取得渲染器。
     */
    public function renderer(): Renderer
    {
        return $this->renderer;
    }

    /**
     * 取得校验器。
     */
    public function validator(): Validator
    {
        return $this->validator;
    }

    /**
     * 注册一个带 @id 的实体，后续可用 {@see reference()} 引用，避免重复输出。
     *
     * @param string $id       站内唯一标识（通常是完整 URL）。
     * @param string $type     schema.org 类型。
     * @param array<string,mixed> $extra 额外属性。
     */
    public function register(string $id, string $type, array $extra = []): self
    {
        $id = trim($id);
        if ($id === '') {
            throw new InvalidArgumentException('@id 不能为空');
        }
        if (!Type::exists($type)) {
            throw new InvalidArgumentException('未知的 schema.org 类型：' . $type);
        }

        $this->registry[$id] = array_merge(['@id' => $id, '@type' => $type], $extra);

        return $this;
    }

    /**
     * 生成对已注册实体的引用（输出仅含 @id 的最小节点）。
     *
     * @param string $id 注册时使用的标识。
     */
    public function reference(string $id): array
    {
        if (!isset($this->registry[$id])) {
            throw new InvalidArgumentException('未注册的 @id：' . $id);
        }

        return ['@id' => $id];
    }

    /**
     * 取得全部已注册实体。
     *
     * @return array<string,array<string,mixed>>
     */
    public function registered(): array
    {
        return $this->registry;
    }

    /**
     * 清空注册表。
     */
    public function clearRegistry(): self
    {
        $this->registry = [];

        return $this;
    }

    /* ================================================================
     *  构造方法
     * ================================================================ */

    /**
     * 文章 / 博客文章。
     *
     * @param array<string,mixed> $data 文章数据。
     */
    public function article(array $data = []): SchemaNode
    {
        return $this->node(Type::BLOG_POSTING, $data, [Type::ARTICLE, Type::CREATIVE_WORK]);
    }

    /**
     * 产品。
     *
     * @param array<string,mixed> $data 产品数据。
     */
    public function product(array $data = []): SchemaNode
    {
        return $this->node(Type::PRODUCT, $data);
    }

    /**
     * 人物。
     *
     * @param array<string,mixed> $data 人物数据。
     */
    public function person(array $data = []): SchemaNode
    {
        return $this->node(Type::PERSON, $data);
    }

    /**
     * 组织。
     *
     * @param array<string,mixed> $data 组织数据。
     */
    public function organization(array $data = []): SchemaNode
    {
        return $this->node(Type::ORGANIZATION, $data);
    }

    /**
     * 网站。
     *
     * @param array<string,mixed> $data 网站数据。
     */
    public function website(array $data = []): SchemaNode
    {
        return $this->node(Type::WEB_SITE, $data, [Type::CREATIVE_WORK]);
    }

    /**
     * 面包屑导航。
     *
     * @param array<int,array{0:string,1:string}> $items [名称, URL] 列表，URL 传 '' 表示当前页。
     */
    public function breadcrumb(array $items = []): SchemaNode
    {
        $node = $this->node(Type::BREADCRUMB_LIST, []);
        $list = [];

        $position = 1;
        foreach ($items as $item) {
            if (!is_array($item) || count($item) < 2) {
                continue;
            }
            $name = trim((string) $item[0]);
            $url  = trim((string) $item[1]);
            if ($name === '') {
                continue;
            }

            $entry = [
                '@type'    => Type::LIST_ITEM,
                'position' => $position,
                'name'     => $name,
            ];
            if ($url !== '') {
                $entry['item'] = $url;
            }

            $list[] = $entry;
            $position++;
        }

        return $node->set('itemListElement', $list);
    }

    /**
     * FAQ 常见问题。
     *
     * @param array<int,array{0:string,1:string}> $items [问题, 答案] 列表。
     */
    public function faq(array $items = []): SchemaNode
    {
        $node = $this->node(Type::FAQPAGE, []);
        $list = [];

        foreach ($items as $item) {
            if (!is_array($item) || count($item) < 2) {
                continue;
            }
            $question = trim((string) $item[0]);
            $answer   = trim((string) $item[1]);
            if ($question === '' || $answer === '') {
                continue;
            }

            $list[] = [
                '@type'          => Type::QUESTION,
                'name'           => $question,
                'acceptedAnswer' => [
                    '@type' => Type::ANSWER,
                    'text'  => $answer,
                ],
            ];
        }

        return $node->set('mainEntity', $list);
    }

    /**
     * 本地商家。
     *
     * @param array<string,mixed> $data 商家数据。
     */
    public function localBusiness(array $data = []): SchemaNode
    {
        return $this->node(Type::LOCAL_BUSINESS, $data, [Type::ORGANIZATION, Type::PLACE]);
    }

    /**
     * 活动。
     *
     * @param array<string,mixed> $data 活动数据。
     */
    public function event(array $data = []): SchemaNode
    {
        return $this->node(Type::EVENT, $data);
    }

    /**
     * 菜谱。
     *
     * @param array<string,mixed> $data 菜谱数据。
     */
    public function recipe(array $data = []): SchemaNode
    {
        return $this->node(Type::RECIPE, $data);
    }

    /**
     * 视频。
     *
     * @param array<string,mixed> $data 视频数据。
     */
    public function videoObject(array $data = []): SchemaNode
    {
        return $this->node(Type::VIDEO_OBJECT, $data, [Type::MEDIA_OBJECT]);
    }

    /**
     * 软件应用。
     *
     * @param array<string,mixed> $data 应用数据。
     */
    public function softwareApplication(array $data = []): SchemaNode
    {
        return $this->node(Type::SOFTWARE_APPLICATION, $data);
    }

    /**
     * 分步教程。
     *
     * @param array<int,array<string,mixed>> $steps 步骤列表。
     */
    public function howTo(array $steps = []): SchemaNode
    {
        $node = $this->node(Type::HOW_TO, []);
        $list = [];

        $position = 1;
        foreach ($steps as $step) {
            if (is_string($step)) {
                $step = ['text' => $step];
            }
            if (!is_array($step)) {
                continue;
            }
            $text = trim((string) ($step['text'] ?? $step['name'] ?? ''));
            if ($text === '') {
                continue;
            }

            $entry = [
                '@type'    => Type::HOW_TO_STEP,
                'position' => $position,
                'text'     => $text,
            ];
            if (isset($step['name']) && is_string($step['name']) && trim($step['name']) !== '') {
                $entry['name'] = trim($step['name']);
            }
            if (isset($step['url']) && is_string($step['url']) && trim($step['url']) !== '') {
                $entry['url'] = trim($step['url']);
            }
            if (isset($step['image']) && is_string($step['image'])) {
                $entry['image'] = trim($step['image']);
            }

            $list[] = $entry;
            $position++;
        }

        return $node->set('step', $list);
    }

    /**
     * 列表页。
     *
     * @param array<int,mixed> $items 条目列表（字符串或数组）。
     * @param int              $startPosition 起始序号。
     */
    public function itemList(array $items = [], int $startPosition = 1): SchemaNode
    {
        $node = $this->node(Type::ITEM_LIST, []);
        $list = [];
        $position = max(1, $startPosition);

        foreach ($items as $item) {
            if (is_string($item)) {
                $item = ['name' => $item];
            }
            if (!is_array($item) || $item === []) {
                continue;
            }

            $entry = array_merge(['@type' => Type::LIST_ITEM, 'position' => $position], $item);
            $list[] = $entry;
            $position++;
        }

        return $node->set('itemListElement', $list);
    }

    /**
     * 评论。
     *
     * @param array<string,mixed> $data 评论数据。
     */
    public function review(array $data = []): SchemaNode
    {
        return $this->node(Type::REVIEW, $data);
    }

    /**
     * 通用节点，用于 SchemaNode 未覆盖的类型。
     *
     * @param string $type     schema.org 类型。
     * @param array<string,mixed> $data 数据。
     */
    public function raw(string $type, array $data = []): SchemaNode
    {
        return $this->node($type, $data);
    }

    /* ================================================================
     *  输出方法
     * ================================================================ */

    /**
     * 渲染单个节点。
     *
     * @param SchemaNode|array<string,mixed> $node 节点。
     */
    public function render($node): string
    {
        $data = $node instanceof SchemaNode ? $node->toArray() : $node;

        return $this->renderer->render($data);
    }

    /**
     * 渲染多个节点，合并为 @graph。
     *
     * @param array<int,SchemaNode|array<string,mixed>> $nodes 节点列表。
     */
    public function renderGraph(array $nodes): string
    {
        $data = [];
        foreach ($nodes as $node) {
            $resolved = $node instanceof SchemaNode ? $node->toArray() : $node;
            if ($resolved !== []) {
                $data[] = $resolved;
            }
        }

        return $this->renderer->renderMany($data, true);
    }

    /**
     * 校验节点。
     *
     * @param SchemaNode|array<string,mixed> $node 节点。
     * @return array<int,array{level:string,type:string,message:string,path:string}>
     */
    public function validate($node): array
    {
        $data = $node instanceof SchemaNode ? $node->toArray() : $node;

        return $this->validator->validate($data);
    }

    /**
     * 生成构建 → 校验 → 渲染的一体化快捷方法。
     *
     * 校验不通过时仍会输出 JSON-LD（避免页面因结构问题而失去富媒体结果），
     * 但会把问题数量以 HTML 注释形式附在 script 标签**之后**，便于排查。
     *
     * @param callable(SchemaForge):SchemaNode $builder 构造回调。
     */
    public function build(callable $builder): string
    {
        $node = $builder($this);
        $data = $node instanceof SchemaNode ? $node->toArray() : $node;

        $issues = $this->validator->validate($data);
        $html   = $this->renderer->render($data);

        if ($this->validator->passes($issues) || $html === '') {
            return $html;
        }

        // 注释只含纯数字，不掺入任何用户数据，因此不存在注入面。
        // 放在 script 之后而非之前，避免注释被误认为脚本内容的一部分。
        return $html . "\n" . '<!-- JSON-LD validation issues: ' . count($issues) . ' -->';
    }

    /**
     * 节点工厂。
     *
     * @param string $type         主类型。
     * @param array<string,mixed> $data       初始数据。
     * @param array<int,string>   $extraTypes 额外附加的 @type（用于多类型声明）。
     */
    protected function node(string $type, array $data, array $extraTypes = []): SchemaNode
    {
        $node = new SchemaNode($this->context);
        $node->setType($type, $extraTypes);

        foreach ($data as $key => $value) {
            if (strpos((string) $key, '@') === 0) {
                // 允许覆盖 @id / @context 等。
                $node->set((string) $key, $value);
                continue;
            }
            $node->set((string) $key, $value);
        }

        return $node;
    }
}
