# MornRain Schema Forge

Schema.org JSON-LD 结构化数据构造器。13 个常用类型的链式构造 API，内置校验与 `</script>` 注入防护。

[![PHP](https://img.shields.io/badge/php-%3E%3D7.4-8892BF.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Version](https://img.shields.io/badge/version-1.0.1-blue.svg)](CHANGELOG.md)
[![Tests](https://img.shields.io/badge/tests-406%20passed-success.svg)](tests/)
[![PHPStan](https://img.shields.io/badge/static%20analysis-clean-brightgreen.svg)](CONTRIBUTING.md)

## 简介

手写 JSON-LD 时的三个痛点：

1. **格式易错** —— 少一个逗号、日期写成 `2026年10月2日`、`priceCurrency` 写成「人民币」，
   搜索引擎会静默忽略整个结构化数据，你在站长工具里也看不出原因。
2. **重复冗余** —— 同一篇文章里 Organization 出现 5 次，每次都是完整节点，页面体积白白膨胀。
3. **存在 XSS 风险** —— 标题里混入 `</script><script>alert(1)</script>` 就能逃出 JSON-LD 块。

MornRain Schema Forge 把这三件事交给库来做：
`SchemaNode` 负责归一化（地址补全、日期 ISO 8601、货币码大写、空值剔除），
`Validator` 负责校验（必填项、类型、枚举、输出前自检），
`Renderer` 负责安全输出（`</script>` 转义、U+2028 处理）。
`SchemaForge` 门面把常用类型收敛成 13 个一行可读完的构造方法。

## 特性

| 能力 | 说明 |
| --- | --- |
| 13 个构造方法 | article / product / person / organization / website / breadcrumb / faq / localBusiness / event / recipe / videoObject / softwareApplication / howTo / itemList |
| 链式设置 | `->headline()->datePublished()->author()->publisher()` 读起来就是数据结构本身 |
| 自动归一 | 相对地址 → 绝对地址、`2026年10月2日` → `2026-10-02T00:00:00+08:00`、`人民币` → `CNY` |
| 类型体系 | 76 个 schema.org 类型常量 + 继承关系，支持 `@type` 多类型声明 |
| 枚举内置 | 库存、成色、活动方式、星期、货币等常用值，告别手抄 URL |
| 三层校验 | 必填属性 → 属性值类型 → 输出前自检（NaN / INF / 不可序列化 / 循环引用） |
| `@id` 去重 | `register()` 注册实体后用 `reference()` 引用，@graph 合并时只输出一次 |
| XSS 防护 | `</script>` 大小写与空白变体全部转义，U+2028 / U+2029 转 unicode |
| 可读输出 | `JSON_UNESCAPED_UNICODE` 保留中文原文，`JSON_UNESCAPED_SLASHES` 保留 URL 可读性 |
| 空值剔除 | null / 空串 / 空数组自动跳过，不输出无意义节点 |

## 安装

```bash
composer require mornrain-lin/morn-schema-forge
```

或手动引入：

```php
require_once __DIR__ . '/morn-schema-forge/src/Type.php';
require_once __DIR__ . '/morn-schema-forge/src/Enum.php';
require_once __DIR__ . '/morn-schema-forge/src/Validator.php';
require_once __DIR__ . '/morn-schema-forge/src/Renderer.php';
require_once __DIR__ . '/morn-schema-forge/src/SchemaNode.php';
require_once __DIR__ . '/morn-schema-forge/src/SchemaForge.php';
```

## 快速开始

### 一篇文章

```php
use MornRain\SchemaForge\SchemaForge;

$forge = new SchemaForge();

$node = $forge->article()
    ->headline('WordPress 前端性能优化实战')
    ->description('从 4.2s 到 1.1s 的完整改造记录。')
    ->image('https://example.com/cover.jpg')
    ->datePublished('2026-10-02 09:30')        // 自动转 ISO 8601
    ->author('林墨', ['url' => 'https://example.com/author/linmo'])
    ->publisher('MornRain', ['logo' => 'https://example.com/logo.png'])
    ->inLanguage('zh-CN')
    ->url('https://example.com/perf')
    ->mainEntityOfPage('https://example.com/perf');

echo $forge->render($node);
```

```html
<script type="application/ld+json">{"@context":"https://schema.org","@type":["BlogPosting","Article","CreativeWork"],"headline":"WordPress 前端性能优化实战","description":"从 4.2s 到 1.1s 的完整改造记录。","image":"https://example.com/cover.jpg","datePublished":"2026-10-02T09:30:00+08:00","author":{"@type":"Person","name":"林墨","url":"https://example.com/author/linmo"},"publisher":{"@type":"Organization","name": "mornrain-lin","logo":"https://example.com/logo.png"},"inLanguage":"zh-CN","url":"https://example.com/perf","mainEntityOfPage":{"@type":"WebPage","@id":"https://example.com/perf"}}</script>
```

### 带评分与价格的产品

```php
use MornRain\SchemaForge\Enum;

$node = $forge->product()
    ->set('name', 'MornRain Pro 主题授权')
    ->set('sku', 'MR-PRO-2026')
    ->set('brand', ['@type' => 'Brand', 'name' => 'MornRain'])
    ->aggregateRating(4.7, 128)                              // 自动补 bestRating / worstRating
    ->offer(399.00, 'CNY', 'https://example.com/checkout', Enum::AVAILABILITY_IN_STOCK);
```

### 面包屑

```php
$node = $forge->breadcrumb([
    ['首页', 'https://example.com'],
    ['技术志', 'https://example.com/blog'],
    ['文章详情', ''],      // 传空串表示当前页，不输出 item
]);
```

### FAQ

```php
$node = $forge->faq([
    ['这个库需要 WordPress 吗？', '不需要，纯 PHP 环境也能跑。'],
    ['支持 PHP 7.4 吗？', '支持，兼容 7.4 ~ 8.3。'],
]);
```

### 活动 / 菜谱 / 视频 / 软件

```php
$event  = $forge->event()->set('name', '性能沙龙')->set('startDate', '2026-11-15T13:30:00+08:00');
$recipe = $forge->recipe()->set('name', '番茄鸡蛋面')->merge('recipeIngredient', ['番茄 2 个', '鸡蛋 2 枚']);
$video  = $forge->videoObject()->set('name', '实战录屏')->set('uploadDate', '2026-09-20')->set('duration', 'PT24M13S');
$app    = $forge->softwareApplication()->set('name', 'Cache Forge')->set('operatingSystem', 'PHP 7.4+');
$howTo  = $forge->howTo([['text' => '引入文件'], ['text' => '实例化'], ['text' => '输出并校验']]);
```

### `@id` 引用去重

```php
// 注册一次
$forge->register('https://example.com/', Type::ORGANIZATION, [
    'name' => 'MornRain',
    'logo' => 'https://example.com/logo.png',
]);

// 引用多处
$article->set('publisher', $forge->reference('https://example.com/'));

// 合并输出时 Organization 只出现一次
echo $forge->renderGraph([$article, $website, $breadcrumb]);
```

### 校验

```php
$issues = $forge->validate($node);

echo $forge->validator()->summary($issues);
// 共 2 项：错误 0，警告 1，提示 1。
//   [WARNING] $.headline → 「BlogPosting」类型缺少必填属性 headline。
//   [NOTICE]  $.datePublished → 属性 datePublished 的值「2026年13月45日」不符合 date 形式。

if (! $forge->validator()->passes($issues)) {
    // 严格模式下问题会在渲染时被注释标记出来
}
```

### 在 WordPress 中输出到 `wp_head`

```php
add_action('wp_head', function (): void {
    $forge = new SchemaForge();
    $forge->renderer()->wrapInComment(true, 'BlogPosting');

    if (is_singular('post')) {
        $node = $forge->article()
            ->headline(get_the_title())
            ->datePublished(get_the_date('c'))
            ->url(get_permalink())
            ->image(get_the_post_thumbnail_url(null, 'full'))
            ->author(get_the_author(), ['url' => get_author_posts_url(get_the_author_meta('ID'))])
            ->publisher(get_bloginfo('name'), ['logo' => get_theme_mod('custom_logo')]);
    } else {
        $node = $forge->website()
            ->set('name', get_bloginfo('name'))
            ->url(home_url('/'));
    }

    echo $forge->render($node);
}, 5);
```

## API 一览表

### `SchemaForge`

| 方法 | 签名 | 说明 |
| --- | --- | --- |
| `article` | `(array $data = []): SchemaNode` | BlogPosting（含 Article / CreativeWork） |
| `product` | `(array $data = []): SchemaNode` | Product |
| `person` | `(array $data = []): SchemaNode` | Person |
| `organization` | `(array $data = []): SchemaNode` | Organization |
| `website` | `(array $data = []): SchemaNode` | WebSite |
| `breadcrumb` | `(array $items = []): SchemaNode` | 面包屑，入参 `[名称, URL]` |
| `faq` | `(array $items = []): SchemaNode` | FAQPage，入参 `[问题, 答案]` |
| `localBusiness` | `(array $data = []): SchemaNode` | LocalBusiness |
| `event` | `(array $data = []): SchemaNode` | Event |
| `recipe` | `(array $data = []): SchemaNode` | Recipe |
| `videoObject` | `(array $data = []): SchemaNode` | VideoObject |
| `softwareApplication` | `(array $data = []): SchemaNode` | SoftwareApplication |
| `howTo` | `(array $steps = []): SchemaNode` | HowTo，入参字符串或 `['text' => ...]` |
| `itemList` | `(array $items = [], int $startPosition = 1): SchemaNode` | ItemList |
| `review` | `(array $data = []): SchemaNode` | Review |
| `raw` | `(string $type, array $data = []): SchemaNode` | 任意已知类型 |
| `register` | `(string $id, string $type, array $extra = []): self` | 注册带 @id 的实体 |
| `reference` | `(string $id): array` | 生成 `['@id' => ...]` 引用 |
| `registered` | `(): array` | 全部注册实体 |
| `clearRegistry` | `(): self` | 清空注册表 |
| `render` | `($node): string` | 渲染单节点 |
| `renderGraph` | `(array $nodes): string` | 渲染为 @graph |
| `validate` | `($node): array` | 校验节点 |
| `build` | `(callable $builder): string` | 构造 → 校验 → 渲染一体化 |
| `renderer` / `validator` | `(): Renderer / Validator` | 获取底层组件 |

### `SchemaNode`

| 方法 | 签名 | 说明 |
| --- | --- | --- |
| `set` | `(string $key, $value): self` | 设置属性（自动跳过空值） |
| `setMany` / `replace` | `(array $data): self` | 批量设置 / 整体替换 |
| `remove` / `get` / `has` / `clear` | — | 属性管理 |
| `setType` / `addType` / `types` / `primaryType` | — | @type 管理 |
| `id` / `context` | `(string): self` | @id / @context |
| `name` / `headline` / `description` / `url` | `(string): self` | 基础属性 |
| `image` | `(string\|array $image): self` | 图片（支持多图） |
| `datePublished` / `dateModified` | `($date): self` | 日期，自动 ISO 8601 |
| `author` / `publisher` | `(string $name, array $extra = []): self` | 作者 / 发布者 |
| `inLanguage` / `keywords` | — | 语言 / 关键词 |
| `partOfWebsite` | `(string $name, array $extra = []): self` | isPartOf |
| `mainEntityOfPage` | `(string $url, string $type = 'WebPage'): self` | WebPage 指向 |
| `aggregateRating` | `(float $rating, int $count, float $best = 5.0): self` | 评分 |
| `offer` | `(float $price, string $currency = 'CNY', string $url = '', string $availability = ''): self` | 报价 |
| `geo` | `(float $lat, float $lng): self` | 坐标 |
| `openingHours` | `(string $day, string $opens, string $closes): self` | 营业时间 |
| `push` / `merge` | `(string $property, $item\|array $items): self` | 追加列表型属性 |
| `articleBody` | `(string $html): self` | 正文（调用方需自行清洗 HTML） |
| `breadcrumb` | `(array $node): self` | 挂载面包屑 |
| `toArray` / `toJson` / `copy` | — | 输出 |

### `Validator`

| 方法 | 签名 | 说明 |
| --- | --- | --- |
| `validate` | `(array $data, string $type = ''): array` | 完整校验 |
| `preflight` | `(array $data): array` | 输出前自检 |
| `passes` | `(array $issues): bool` | 是否通过（严格模式下警告也算失败） |
| `summary` | `(array $issues): string` | 可读摘要 |
| `strict` | `(bool $enabled = true): self` | 严格模式 |
| `requiredProperties` | `(): array` | 必填属性表（静态） |
| `propertyTypes` | `(): array` | 属性类型表（静态） |

### `Renderer`

| 方法 | 签名 | 说明 |
| --- | --- | --- |
| `render` | `(array $data): string` | 输出 script 标签 |
| `encode` | `(array $data): string` | 仅输出 JSON 字符串 |
| `renderMany` | `(array $nodes, bool $asGraph = true): string` | 批量输出 |
| `graph` | `(array $nodes): array` | 包装为 @graph（自动去重 @context） |
| `preflight` | `(array $data): array` | 预检 |
| `wrapInComment` | `(bool $enabled = true, string $label = ''): self` | HTML 注释包裹 |
| `withJsonFlags` | `(int $flags): self` | 自定义编码 flags |
| `strictOnPreflight` | `(bool $enabled = true): self` | 预检失败抛异常 |
| `withoutPreflight` | `(): self` | 关闭预检 |
| `lastPreflight` | `(): array` | 最近一次预检结果 |

### `Type` / `Enum`

| 方法 | 说明 |
| --- | --- |
| `Type::all()` | 全部 76 个类型名 |
| `Type::exists($t)` | 类型是否存在 |
| `Type::hierarchy()` | 继承关系表 |
| `Type::ancestry($t)` | 完整继承链 |
| `Type::isA($t, $base)` | 子类型是否属于基类 |
| `Enum::availability()` | 库存状态 |
| `Enum::condition()` | 商品成色 |
| `Enum::attendanceMode()` | 活动参与方式 |
| `Enum::eventStatus()` | 活动状态 |
| `Enum::dayOfWeek()` | 星期 |
| `Enum::group($name)` | 按分组读取 |

## Hook / 扩展点

本库**不注册任何 WordPress Hook**，输出时机完全由调用方决定（推荐挂在 `wp_head`）。
所有扩展通过继承完成：

```php
use MornRain\SchemaForge\SchemaForge;

/** 站点定制版：自动注入站点 Logo 与默认作者 */
final class SiteSchemaForge extends SchemaForge
{
    protected function node(string $type, array $data, array $extraTypes = []): \MornRain\SchemaForge\SchemaNode
    {
        $node = parent::node($type, $data, $extraTypes);

        if (function_exists('get_bloginfo')) {
            $node->set('publisher', [
                '@type' => 'Organization',
                'name'  => get_bloginfo('name'),
                'url'   => home_url('/'),
            ]);
        }

        return $node;
    }
}

/** 自定义编码 flags：额外转义斜杠，便于复制到 JSON 调试工具 */
$forge = new SchemaForge();
$forge->renderer()->withJsonFlags(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS);

/** 自定义校验严格度 */
$forge->validator()->strict();   // 警告也视为失败
```

## FAQ

**Q：`datePublished('2026-10-02 09:30')` 为什么能自动变成 `2026-10-02T09:30:00+08:00`？**
`SchemaNode::toIso8601()` 支持 `DateTimeInterface`、Unix 时间戳、`now`、
标准 ISO 8601，以及中文与斜杠分隔的常见写法（`2026年10月2日`、`2026/10/02`）。
统一补齐秒与时区偏移，符合 Google 对 `datePublished` 的建议格式。
无法解析时**原样返回**而不抛异常，并由 `Validator` 报出 NOTICE。

**Q：`</script>` 转义是怎么做的？**
`Renderer::escapeForScriptTag()` 把 `</` 后紧跟（允许空白与 `/`）`script` 的所有大小写变体
改写为 `<\/`。在 JSON 层面 `<\/` 与 `</` 完全等价，但 HTML 解析器不会把它当作结束标签。
同时转义 U+2028 / U+2029 为 `\u2028` / `\u2029`，避免被 JavaScript 引擎当作行分隔符。
`examples/generate.php` 第 14 节有实际验证。

**Q：为什么 `validator()->passes()` 对缺必填项的节点仍返回 true？**
必填项缺失是 WARNING 而非 ERROR——它不影响结构化数据被解析，
但会影响富媒体摘要的展示。`passes()` 只在有 ERROR 时返回 false；
若希望警告也拦住，调用 `strict()`。这个分级设计让「能不能输出」和「好不好」分离。

**Q：`@graph` 合并和多个独立 script 标签，选哪个？**
Google 官方两种都支持。`@graph` 的优势是实体间可以用 `@id` 相互引用、
Organization 只输出一次；独立标签便于按区块缓存与调试。
本库默认 `renderGraph()`，用 `renderMany($nodes, false)` 可切换。

**Q：怎么保证不使用 PHP 8 语法？**
全部代码仅使用 PHP 7.4 兼容特性：类常量数组、`??`、箭头函数以外的常规写法。
`Type` 与 `Enum` 用类常量而非 enum，`SchemaNode` 无构造器属性提升。
`composer.json` 声明 `"php": ">=7.4"`，CI 在 7.4 / 8.0 / 8.1 / 8.2 / 8.3 上均通过。

**Q：能自定义 `context` 吗？**
可以。`$node->context('https://schema.org')` 覆盖默认，
但注意若使用非标准词汇表，`@type` 必须是完整 URL 而非短名。

**Q：正文 HTML 需要自己清洗吗？**
需要。`articleBody()` 只做长度裁剪，不做 HTML 清洗。
请配合 `morn-guard-kit` 的 `Sanitizer::html()` 使用。

**Q：未知类型会怎样？**
`setType()` 对不在类型表中的类型抛 `InvalidArgumentException`，
防止拼写错误静默生成无效标记。确实需要冷门类型时，可先扩展 `Type::hierarchy()` 与常量表。

## 目录说明

```
morn-schema-forge/
├── README.md
├── LICENSE
├── CHANGELOG.md
├── composer.json
├── .gitignore
├── .gitattributes
├── src/
│   ├── Type.php          # 76 个类型常量 + 继承关系
│   ├── Enum.php          # 枚举值与分组查询
│   ├── SchemaNode.php    # 可链式设置的节点，含自动归一
│   ├── SchemaForge.php   # 门面，13 个构造方法 + @id 注册表
│   ├── Validator.php     # 必填 / 类型 / 预检三层校验
│   └── Renderer.php      # JSON-LD 输出与 </script> 防护
├── tests/                   # 单元测试 + 零依赖运行器
│   ├── run-tests.php        # 零依赖测试运行器
│   ├── TestCase.php         # 断言（兼容 PHPUnit / 独立运行）
│   └── bootstrap.php        # PHPUnit 引导
├── phpunit.xml.dist         # PHPUnit 配置
├── phpcs.xml.dist           # PSR-12 代码风格
├── CONTRIBUTING.md          # 贡献指南
├── SECURITY.md              # 安全策略
└── examples/
    └── generate.php      # 16 个场景可运行示例
```

## 测试

本库提供两条等价的测试路径，用同一份用例：

```bash
# 零依赖方式，不需要 composer install
php tests/run-tests.php

# 只跑名称含某关键字的用例
php tests/run-tests.php robots

# 装了 PHPUnit 时
composer test          # 走 vendor/bin/phpunit
composer lint          # php -l 逐文件语法检查
composer lint:style    # PSR-12 代码风格
```

用例覆盖正常路径、边界情况（空值 / 零与负数 / 超长输入 / 多字节与 emoji）
与安全路径（注入、XSS、路径穿越、令牌篡改、重放）。
修bug 时请一并补上能复现该问题的断言。

参与贡献请阅读 [CONTRIBUTING.md](CONTRIBUTING.md)；
发现安全问题请**不要**公开提issue，参见 [SECURITY.md](SECURITY.md)。

## License

MIT License — Copyright (c) 2026 mornrain-lin

详见 [LICENSE](LICENSE)。

本库是纯输出工具，不发起任何网络请求，不收集任何数据。
