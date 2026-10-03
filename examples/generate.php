<?php
/**
 * morn-schema-forge 使用示例。
 *
 * 运行方式（CLI）：
 *   php examples/generate.php
 *
 * 覆盖全部 13 个构造方法、实体注册与 @graph 合并、校验摘要、
 * 以及 </script> 注入防护的验证。本示例不依赖 WordPress。
 */

declare(strict_types=1);

require __DIR__ . '/../src/Type.php';
require __DIR__ . '/../src/Enum.php';
require __DIR__ . '/../src/Validator.php';
require __DIR__ . '/../src/Renderer.php';
require __DIR__ . '/../src/SchemaNode.php';
require __DIR__ . '/../src/SchemaForge.php';

use MornRain\SchemaForge\Enum;
use MornRain\SchemaForge\SchemaForge;
use MornRain\SchemaForge\Type;

$forge = new SchemaForge();

function section(string $title): void
{
    echo PHP_EOL . '=== ' . $title . ' ===' . PHP_EOL;
}

function dumpNode(string $label, $node, SchemaForge $forge): void
{
    echo '-- ' . $label . ' --' . PHP_EOL;
    echo $forge->render($node) . PHP_EOL;
}

/* ------------------------------------------------------------------ */
section('1. 类型系统');

echo '已知类型数量: ' . count(Type::all()) . PHP_EOL;
echo 'NewsArticle 是否属于 CreativeWork: '
    . var_export(Type::isA(Type::NEWS_ARTICLE, Type::CREATIVE_WORK), true) . PHP_EOL;
echo 'LocalBusiness 的继承链: ' . implode(' < ', Type::ancestry(Type::LOCAL_BUSINESS)) . PHP_EOL;

/* ------------------------------------------------------------------ */
section('2. article() —— 中文内容与 UNESCAPED_UNICODE');

$article = $forge->article()
    ->headline('WordPress 前端性能优化实战：从 4.2s 到 1.1s')
    ->description('本文记录了一次完整的前端性能优化过程，包括资源分级、缓存策略与关键渲染路径的改造。')
    ->image('https://example.com/wp-content/uploads/2026/10/perf-cover.jpg')
    ->datePublished('2026-10-02 09:30')
    ->dateModified('2026-10-02 18:00')
    ->author('林墨', ['url' => 'https://example.com/author/linmo', 'jobTitle' => '前端工程师'])
    ->publisher('MornRain', ['logo' => ['@type' => 'ImageObject', 'url' => 'https://example.com/logo.png']])
    ->inLanguage('zh-CN')
    ->url('https://example.com/2026/10/wordpress-performance')
    ->mainEntityOfPage('https://example.com/2026/10/wordpress-performance')
    ->partOfWebsite('MornRain 技术志')
    ->keywords(['WordPress', '性能优化', 'Core Web Vitals']);

dumpNode('BlogPosting', $article, $forge);

/* ------------------------------------------------------------------ */
section('3. product() —— 价格、评分、库存');

$product = $forge->product()
    ->set('name', 'MornRain Pro 主题授权')
    ->set('description', '面向中小站的轻量企业主题，含 12 套行业模板与终身更新。')
    ->set('sku', 'MR-PRO-2026')
    ->set('mpn', 'MR-PRO-2026-BLK')
    ->set('brand', ['@type' => 'Brand', 'name' => 'MornRain'])
    ->image([
        ['@type' => 'ImageObject', 'url' => 'https://example.com/p/1.jpg'],
        ['@type' => 'ImageObject', 'url' => 'https://example.com/p/2.jpg'],
    ])
    ->aggregateRating(4.7, 128, 5.0)
    ->offer(399.00, 'CNY', 'https://example.com/checkout', Enum::AVAILABILITY_IN_STOCK)
    ->set('offers', [
        '@type'         => 'Offer',
        'price'         => 299.00,
        'priceCurrency' => 'CNY',
        'availability'  => Enum::AVAILABILITY_PRE_ORDER,
        'itemCondition' => Enum::CONDITION_NEW,
        'priceValidUntil' => '2026-12-31',
        'shippingDetails' => [
            '@type' => 'OfferShippingDetails',
            'shippingRate' => ['@type' => 'MonetaryAmount', 'value' => '0', 'currency' => 'CNY'],
        ],
    ]);

dumpNode('Product', $product, $forge);

/* ------------------------------------------------------------------ */
section('4. person() / organization() / website()');

$person = $forge->person()
    ->set('name', '林墨')
    ->set('jobTitle', '前端工程师 / 性能顾问')
    ->set('url', 'https://example.com/author/linmo')
    ->set('worksFor', ['@type' => 'Organization', 'name' => 'MornRain'])
    ->set('knowsAbout', ['性能优化', 'PHP', 'WordPress'])
    ->set('sameAs', ['https://github.com/example', 'https://twitter.com/example']);

dumpNode('Person', $person, $forge);

$organization = $forge->organization()
    ->set('name', 'MornRain')
    ->set('url', 'https://example.com')
    ->set('logo', 'https://example.com/logo.png')
    ->set('foundingDate', '2024-03-01')
    ->set('email', 'hello@example.com')
    ->set('contactPoint', [
        '@type'             => 'ContactPoint',
        'contactType'       => 'customer support',
        'email'             => 'support@example.com',
        'availableLanguage' => ['zh-CN', 'en'],
    ]);

dumpNode('Organization', $organization, $forge);

$website = $forge->website()
    ->set('name', 'MornRain 技术志')
    ->set('url', 'https://example.com')
    ->set('inLanguage', 'zh-CN')
    ->set('potentialAction', [
        '@type'       => 'SearchAction',
        'target'      => ['@type' => 'EntryPoint', 'urlTemplate' => 'https://example.com/?s={search_term_string}'],
        'query-input' => 'required name=search_term_string',
    ]);

dumpNode('WebSite', $website, $forge);

/* ------------------------------------------------------------------ */
section('5. breadcrumb()');

$breadcrumb = $forge->breadcrumb([
    ['首页', 'https://example.com'],
    ['技术志', 'https://example.com/blog'],
    ['性能优化', 'https://example.com/blog?cat=perf'],
    ['文章详情', ''],
]);

dumpNode('BreadcrumbList', $breadcrumb, $forge);

/* ------------------------------------------------------------------ */
section('6. faq()');

$faq = $forge->faq([
    ['这个库需要 WordPress 吗？', '不需要。它在 WordPress 中会自动复用 wp_create_nonce 等函数，在纯 PHP 环境下则使用内置的等价实现。'],
    ['会收集我的数据吗？', '不会。所有状态仅保存在本地站点的 Transient、对象缓存或临时目录中。'],
    ['支持 PHP 7.4 吗？', '支持。全部代码兼容 7.4 ~ 8.3，不使用 PHP 8 独有语法。'],
    ['空问题会被忽略吗？', '是的。问题或答案为空白的条目会在构造阶段被丢弃。'],
]);

dumpNode('FAQPage', $faq, $forge);

/* ------------------------------------------------------------------ */
section('7. localBusiness()');

$business = $forge->localBusiness()
    ->set('name', 'MornRain 工作室')
    ->set('image', 'https://example.com/shop.jpg')
    ->set('url', 'https://example.com')
    ->set('telephone', '+86-10-0000-0000')
    ->set('priceRange', '¥¥')
    ->set('address', [
        '@type'           => 'PostalAddress',
        'streetAddress'   => '建国路 88 号',
        'addressLocality' => '北京',
        'addressRegion'   => '北京',
        'postalCode'      => '100022',
        'addressCountry'  => 'CN',
    ])
    ->geo(39.9042, 116.4074)
    ->openingHours('Monday', '09:00', '18:00')
    ->set('openingHoursSpecification', [
        ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'Monday', 'opens' => '09:00', 'closes' => '18:00'],
        ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'Tuesday', 'opens' => '09:00', 'closes' => '18:00'],
        ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'Saturday', 'opens' => '10:00', 'closes' => '16:00'],
    ]);

dumpNode('LocalBusiness', $business, $forge);

/* ------------------------------------------------------------------ */
section('8. event()');

$event = $forge->event()
    ->set('name', 'WordPress 性能沙龙 · 第 6 期')
    ->set('startDate', '2026-11-15T13:30:00+08:00')
    ->set('endDate', '2026-11-15T17:00:00+08:00')
    ->set('eventAttendanceMode', Enum::ATTENDANCE_MIXED)
    ->set('eventStatus', Enum::EVENT_STATUS_SCHEDULED)
    ->set('description', '线下限额 80 人，线上同步开放。')
    ->set('location', [
        '@type' => 'Place',
        'name'  => 'MornRain 工作室',
        'address' => [
            '@type'           => 'PostalAddress',
            'streetAddress'   => '建国路 88 号',
            'addressLocality' => '北京',
        ],
    ])
    ->set('offers', [
        '@type'         => 'Offer',
        'price'         => '0',
        'priceCurrency' => 'CNY',
        'availability'  => Enum::AVAILABILITY_IN_STOCK,
        'url'           => 'https://example.com/events/6',
    ])
    ->set('performer', [
        '@type' => 'Person',
        'name'  => '林墨',
        'url'   => 'https://example.com/author/linmo',
    ]);

dumpNode('Event', $event, $forge);

/* ------------------------------------------------------------------ */
section('9. recipe()');

$recipe = $forge->recipe()
    ->set('name', '五分钟番茄鸡蛋面')
    ->set('description', '适合工作日快手菜的国民面食，酸甜开胃。')
    ->set('image', 'https://example.com/r/noodle.jpg')
    ->set('recipeYield', '1 人份')
    ->set('prepTime', 'PT5M')
    ->set('cookTime', 'PT8M')
    ->set('totalTime', 'PT13M')
    ->set('recipeCategory', '主食')
    ->set('recipeCuisine', '中餐')
    ->merge('recipeIngredient', ['番茄 2 个', '鸡蛋 2 枚', '挂面 100g', '盐 2g', '葱花 适量'])
    ->set('recipeInstructions', [
        ['@type' => 'HowToStep', 'position' => 1, 'text' => '番茄切块，鸡蛋打散备用。'],
        ['@type' => 'HowToStep', 'position' => 2, 'text' => '热油炒蛋至半凝固盛出。'],
        ['@type' => 'HowToStep', 'position' => 3, 'text' => '下番茄炒出汁，加水煮开。'],
        ['@type' => 'HowToStep', 'position' => 4, 'text' => '下面条至断生，倒回鸡蛋，调味撒葱花。'],
    ])
    ->set('nutrition', [
        '@type'           => 'NutritionInformation',
        'calories'        => '420 kcal',
        'proteinContent'  => '18 g',
        'carbohydrateContent' => '58 g',
        'fatContent'      => '12 g',
    ]);

dumpNode('Recipe', $recipe, $forge);

/* ------------------------------------------------------------------ */
section('10. videoObject()');

$video = $forge->videoObject()
    ->set('name', '性能优化实战录屏')
    ->set('description', '从 Lighthouse 报告到改造落地的完整录屏，时长 24 分钟。')
    ->set('thumbnailUrl', [
        'https://example.com/v/thumb-1.jpg',
        'https://example.com/v/thumb-2.jpg',
        'https://example.com/v/thumb-3.jpg',
    ])
    ->set('uploadDate', '2026-09-20T10:00:00+08:00')
    ->set('duration', 'PT24M13S')
    ->set('contentUrl', 'https://example.com/v/perf-master.mp4')
    ->set('embedUrl', 'https://example.com/embed/perf-master')
    ->set('inLanguage', 'zh-CN')
    ->set('isFamilyFriendly', true);

dumpNode('VideoObject', $video, $forge);

/* ------------------------------------------------------------------ */
section('11. softwareApplication() / howTo() / itemList()');

$app = $forge->softwareApplication()
    ->set('name', 'MornRain Cache Forge')
    ->set('applicationCategory', 'DeveloperApplication')
    ->set('applicationSubCategory', '缓存中间件')
    ->set('operatingSystem', '跨平台（PHP 7.4+）')
    ->set('softwareVersion', '1.0.0')
    ->set('description', '对象缓存 / 文件缓存 / Transient 多级缓存封装。')
    ->set('downloadUrl', 'https://github.com/MornRain/morn-cache-forge')
    ->set('license', 'https://opensource.org/licenses/MIT')
    ->set('author', ['@type' => 'Organization', 'name' => 'MornRain'])
    ->aggregateRating(4.5, 32, 5.0);

dumpNode('SoftwareApplication', $app, $forge);

$howTo = $forge->howTo([
    ['text' => '在 functions.php 中 require 本库文件。', 'name' => '引入文件'],
    ['text' => '调用 MornRain\\GuardKit\\RequestGuard 构造守卫实例。', 'name' => '实例化'],
    ['text' => '在表单中输出 nonceField()，提交时调用 verifyNonce() 校验。', 'name' => '输出并校验'],
    ['text' => '需要 nonceField() 的返回值为 false 时，判定为 CSRF 攻击并终止请求。', 'name' => '处理失败分支'],
]);

dumpNode('HowTo', $howTo, $forge);

$itemList = $forge->itemList([
    ['name' => 'PHP 7.4', 'url' => 'https://www.php.net/'],
    ['name' => 'WordPress 6.0', 'url' => 'https://wordpress.org/'],
    '纯文本条目（无链接）',
], 1);

dumpNode('ItemList', $itemList, $forge);

/* ------------------------------------------------------------------ */
section('12. 实体注册与 @id 引用去重');

$forge->register('https://example.com/', Type::ORGANIZATION, [
    'name'  => 'MornRain',
    'logo'  => 'https://example.com/logo.png',
    'url'   => 'https://example.com',
]);

$articleRef = $forge->article()
    ->headline('引用示例：用 @id 代替重复的出版者信息')
    ->datePublished('2026-10-02')
    ->set('publisher', $forge->reference('https://example.com/'))
    ->url('https://example.com/2026/10/by-id-reference');

$siteRef = $forge->website()
    ->set('name', 'MornRain 技术志')
    ->set('url', 'https://example.com')
    ->set('publisher', $forge->reference('https://example.com/'));

echo '-- 独立渲染的 Article（publisher 被压缩为 @id）--' . PHP_EOL;
echo $forge->render($articleRef) . PHP_EOL;

echo PHP_EOL . '-- 合并为 @graph（Organization 只输出一次）--' . PHP_EOL;
$registered = $forge->registered();
$graph = $forge->renderGraph([
    array_merge(['@id' => 'https://example.com/'], reset($registered)),
    $articleRef->toArray(),
    $siteRef->toArray(),
]);
echo $graph . PHP_EOL;

/* ------------------------------------------------------------------ */
section('13. 校验：完整节点 vs 缺字段节点');

echo '[合法 Article]' . PHP_EOL;
$issues = $forge->validate($article);
echo $forge->validator()->summary($issues) . PHP_EOL;

echo PHP_EOL . '[缺必填项的 Article]' . PHP_EOL;
$broken = $forge->article(['name' => '只有 name 的残缺文章']);
$issues = $forge->validate($broken);
echo $forge->validator()->summary($issues) . PHP_EOL;
echo '是否通过校验: ' . var_export($forge->validator()->passes($issues), true) . PHP_EOL;

echo PHP_EOL . '[类型错误的日期与价格]' . PHP_EOL;
$wrongTypes = $forge->article([
    'headline'       => '类型错误示例',
    'datePublished'  => '2026年13月45日',
    'offers'         => ['@type' => 'Offer', 'price' => '面议', 'priceCurrency' => '人民币'],
]);
echo $forge->validator()->summary($forge->validate($wrongTypes)) . PHP_EOL;

/* ------------------------------------------------------------------ */
section('14. XSS 防护：</script> 注入无法闭合标签');

$malicious = $forge->article([
    'headline' => '安全测试',
    'datePublished' => '2026-10-02',
    'description' => '恶意载荷：</script><script>alert(document.cookie)</script>',
    'author'    => ['@type' => 'Person', 'name' => '</SCRIPT ><img src=x onerror=alert(1)>'],
]);

$rendered = $forge->render($malicious);
echo $rendered . PHP_EOL . PHP_EOL;

// 断言：只检查 JSON 内容部分（外层 script 标签本身当然含 </script>）
$jsonOnly = preg_replace('#^<script[^>]*>|</script>$#', '', trim($rendered));
$hasRawClose = preg_match('#</\s*script#i', (string) $jsonOnly) === 1;
$escapedCount = preg_match_all('~<\\\\/~', (string) $jsonOnly);

echo 'JSON 内容中存在未转义的 </script: ' . ($hasRawClose ? '是（存在 XSS 风险）' : '否（安全）') . PHP_EOL;
echo '已转义为 <\/ 的序列数: ' . $escapedCount . PHP_EOL;
echo '回读 JSON 仍可正常解析: '
    . var_export(json_decode((string) $jsonOnly, true) !== null, true) . PHP_EOL;

/* ------------------------------------------------------------------ */
section('15. 输出选项：HTML 注释包裹与自定义 flags');

$commented = new SchemaForge();
$commented->renderer()->wrapInComment(true, 'BlogPosting');
echo $commented->render($article) . PHP_EOL;

/* ------------------------------------------------------------------ */
section('16. 运行时类型保护');

try {
    $forge->raw('ThisTypeDoesNotExist');
} catch (InvalidArgumentException $e) {
    echo '捕获到预期的异常: ' . $e->getMessage() . PHP_EOL;
}

try {
    $forge->register('https://example.com/unknown', 'NotARealType');
} catch (InvalidArgumentException $e) {
    echo '捕获到预期的异常: ' . $e->getMessage() . PHP_EOL;
}

try {
    $forge->reference('https://example.com/not-registered');
} catch (InvalidArgumentException $e) {
    echo '捕获到预期的异常: ' . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . 'SchemaForge 示例运行结束。' . PHP_EOL;
