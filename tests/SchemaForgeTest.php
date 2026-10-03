<?php
/**
 * Schema 构造与渲染测试。
 *
 * @package MornRain\SchemaForge\Tests
 */

declare(strict_types=1);

namespace MornRain\SchemaForge\Tests;

use InvalidArgumentException;
use MornRain\SchemaForge\Enum;
use MornRain\SchemaForge\Renderer;
use MornRain\SchemaForge\SchemaForge;
use MornRain\SchemaForge\SchemaNode;
use MornRain\SchemaForge\Type;
use MornRain\SchemaForge\Validator;

/**
 * SchemaForge 测试。
 */
class SchemaForgeTest extends TestCase
{
    /** @var SchemaForge */
    private $f;

    protected function setUp(): void
    {
        $this->f = new SchemaForge();
    }

    /* ---------- 基本构造 ---------- */

    public function testArticleProducesValidShape(): void
    {
        $node = $this->f->article([
            'headline'       => '标题',
            'datePublished'  => '2026-01-02',
            'author'         => ['@type' => 'Person', 'name' => '林墨'],
        ]);
        $data = $node->toArray();

        self::assertSame('https://schema.org', $data['@context']);
        self::assertContains('BlogPosting', $node->types());
        self::assertContains('Article', $node->types(), 'article() 应附加 Article 类型');
        self::assertSame('标题', $data['headline']);
    }

    public function testAllBuilderMethodsReturnNodes(): void
    {
        $builders = [
            'article', 'product', 'person', 'organization', 'website',
            'event', 'recipe', 'videoObject', 'softwareApplication', 'review',
        ];

        foreach ($builders as $name) {
            $node = $this->f->{$name}(['name' => 'x']);
            self::assertInstanceOf(SchemaNode::class, $node, "{$name}() 应返回 SchemaNode");
            self::assertTrue($node->primaryType() !== '', "{$name}() 应设置 @type");
        }
    }

    public function testStructuredBuilders(): void
    {
        $faq = $this->f->faq([['Q?', 'A.'], ['Q2?', 'A2.']])->toArray();
        self::assertCount(2, $faq['mainEntity']);

        $crumb = $this->f->breadcrumb([['首页', '/'], ['当前页', '']])->toArray();
        self::assertCount(2, $crumb['itemListElement']);
        self::assertArrayNotHasKey('item', $crumb['itemListElement'][1], '末项 URL 为空时不应输出 item');

        $howto = $this->f->howTo(['第一步', '第二步'])->toArray();
        self::assertCount(2, $howto['step']);

        $list = $this->f->itemList(['a', 'b', 'c'], 3)->toArray();
        self::assertSame(3, $list['itemListElement'][0]['position'], 'startPosition 应生效');
    }

    public function testEmptyStructuredInputOmitsListKey(): void
    {
        // set() 会跳过空数组，因此空列表时键本身不应存在
        self::assertArrayNotHasKey('mainEntity', $this->f->faq([])->toArray());
        self::assertArrayNotHasKey('itemListElement', $this->f->breadcrumb([])->toArray());
        self::assertArrayNotHasKey('itemListElement', $this->f->itemList([])->toArray());
    }

    public function testStructuredInputKeepsListWhenPresent(): void
    {
        self::assertCount(1, $this->f->faq([['Q', 'A']])->toArray()['mainEntity']);
        self::assertCount(1, $this->f->breadcrumb([['H', '/']])->toArray()['itemListElement']);
    }

    public function testMalformedStructuredInputIsSkipped(): void
    {
        $faq = $this->f->faq([['Q?', 'A.'], ['no-answer'], '', ['', 'A']])->toArray();
        self::assertCount(1, $faq['mainEntity'], '缺项或空值的条目应被跳过');
    }

    /* ---------- SchemaNode ---------- */

    public function testNodeSkipsEmptyValues(): void
    {
        $node = (new SchemaNode())->setType(Type::PERSON);

        $node->set('a', null)->set('b', '')->set('c', '   ')->set('d', []);
        self::assertFalse($node->has('a'));
        self::assertFalse($node->has('b'));
        self::assertFalse($node->has('c'));
        self::assertFalse($node->has('d'));

        $node->set('ok', 'v');
        self::assertTrue($node->has('ok'));
    }

    public function testSetTypeRejectsUnknownType(): void
    {
        $node = new SchemaNode();
        self::assertThrows(InvalidArgumentException::class, static function () use ($node): void {
            $node->setType('NotARealType');
        });
        self::assertThrows(InvalidArgumentException::class, static function () use ($node): void {
            $node->setType('');
        });
    }

    public function testAddTypeIgnoresUnknownAndDuplicates(): void
    {
        $node = (new SchemaNode())->setType(Type::ARTICLE);
        $node->addType(Type::ARTICLE)->addType('NotAType')->addType(Type::CREATIVE_WORK);

        self::assertCount(2, $node->types());
        self::assertContains(Type::CREATIVE_WORK, $node->types());
    }

    public function testDateNormalisation(): void
    {
        $node = (new SchemaNode())->setType(Type::ARTICLE);

        self::assertSame('2026-01-02T00:00:00' . date('P'), $node->datePublished('2026-01-02')->get('datePublished'));
        self::assertStringContains('T', (string) $node->datePublished('2026-01-02 10:30')->get('datePublished'));
        self::assertStringContains('T', (string) $node->datePublished('2026/01/02')->get('datePublished'));
        // 非法输入应保留原样，由 Validator 报错
        self::assertSame('not-a-date', $node->datePublished('not-a-date')->get('datePublished'));
    }

    public function testOfferClampsAndNormalises(): void
    {
        $node = (new SchemaNode())->setType(Type::PRODUCT);
        $offer = $node->offer(-10.0, 'usd', '', Enum::AVAILABILITY_IN_STOCK)->get('offers');

        self::assertSame(0.0, $offer['price'], '负价格应被夹到 0');
        self::assertSame('USD', $offer['priceCurrency']);
        self::assertSame(Enum::AVAILABILITY_IN_STOCK, $offer['availability']);
    }

    public function testOfferRejectsInvalidCurrencyLength(): void
    {
        $node = (new SchemaNode())->setType(Type::PRODUCT);
        $offer = $node->offer(10.0, 'toolong')->get('offers');

        self::assertSame('CNY', $offer['priceCurrency'], '非法币种应回退默认值');
    }

    public function testGeoRejectsInvalidCoordinates(): void
    {
        $node = (new SchemaNode())->setType(Type::LOCAL_BUSINESS);

        // NaN 会让 json_encode 整体失败，必须提前拒绝
        self::assertThrows(InvalidArgumentException::class, static function () use ($node): void {
            $node->geo(NAN, 0.0);
        });
        self::assertThrows(InvalidArgumentException::class, static function () use ($node): void {
            $node->geo(0.0, INF);
        });
        self::assertThrows(InvalidArgumentException::class, static function () use ($node): void {
            $node->geo(91.0, 0.0);
        });
        self::assertThrows(InvalidArgumentException::class, static function () use ($node): void {
            $node->geo(0.0, 181.0);
        });
    }

    public function testAggregateRatingRejectsNan(): void
    {
        $node = (new SchemaNode())->setType(Type::PRODUCT);
        self::assertThrows(InvalidArgumentException::class, static function () use ($node): void {
            $node->aggregateRating(NAN, 5);
        });
    }

    public function testAggregateRatingClampsToBest(): void
    {
        $node = (new SchemaNode())->setType(Type::PRODUCT);
        $rating = $node->aggregateRating(99.0, 10, 5.0)->get('aggregateRating');

        self::assertSame(5.0, $rating['ratingValue']);
    }

    public function testMainEntityOfPageRejectsUnknownType(): void
    {
        $node = (new SchemaNode())->setType(Type::WEB_PAGE);
        self::assertThrows(InvalidArgumentException::class, static function () use ($node): void {
            $node->mainEntityOfPage('/a', 'NotAType');
        });
    }

    public function testPushAndMerge(): void
    {
        $node = (new SchemaNode())->setType(Type::RECIPE);
        $node->push('recipeIngredient', '面粉')->push('recipeIngredient', '水')->merge('recipeIngredient', ['盐', '']);

        self::assertCount(3, $node->get('recipeIngredient'), '空项应被 push 忽略');
    }

    public function testCopyIsIndependent(): void
    {
        $node = (new SchemaNode())->setType(Type::PERSON)->name('原名');
        $copy = $node->copy()->name('新名');

        self::assertSame('原名', $node->get('name'), 'copy 后修改不应影响原节点');
        self::assertSame('新名', $copy->get('name'));
    }

    public function testRemoveAndClear(): void
    {
        $node = (new SchemaNode())->setType(Type::PERSON)->name('x')->set('k', 'v');
        $node->remove('k');
        self::assertFalse($node->has('k'));

        $node->clear();
        self::assertFalse($node->has('name'));
        self::assertSame(Type::PERSON, $node->primaryType(), 'clear 不应清除 @type');
    }

    public function testMultibyteContentPreserved(): void
    {
        $node = (new SchemaNode())->setType(Type::ARTICLE)->name('中文标题 🎉')->headline('副标题');
        $json = $node->toJson();

        self::assertStringContains('中文标题', $json);
        self::assertStringContains('🎉', $json);
    }

    /* ---------- Renderer ---------- */

    public function testRenderWrapsInScriptTag(): void
    {
        $html = $this->f->render($this->f->person(['name' => '林墨']));

        self::assertStringContains('<script type="application/ld+json">', $html);
        self::assertStringContains('</script>', $html);
    }

    public function testRenderEscapesScriptClosingTag(): void
    {
        $payload = '正文 </script><script>alert(1)</script>';
        $html    = $this->f->render($this->f->article([
            'headline'      => '标题',
            'datePublished' => '2026-01-01',
            'description'   => $payload,
        ]));

        // 不得出现能闭合 script 的原始序列
        self::assertSame(1, substr_count($html, '</script>'), '只允许结尾那一个闭合标签');
        self::assertStringContains('<\\/script>', $html);
    }

    public function testRenderPreservesHtmlCommentSequencesInData(): void
    {
        // 曾经把 --> 改写成 >，会破坏 JSON-LD 中的合法文本
        $html = $this->f->render($this->f->person(['name' => 'A --> B <!-- C']));

        self::assertStringContains('A --> B <!-- C', $html, '数据中的注释序列必须原样保留');
    }

    public function testRenderEmptyDataProducesNothing(): void
    {
        self::assertSame('', (new Renderer())->render([]));
    }

    public function testEncodeFailureReturnsEmptyInsteadOfFakeNode(): void
    {
        $renderer = new Renderer();
        // NAN 无法被 json_encode
        $out = $renderer->encode(['@type' => 'Thing', 'v' => NAN]);

        self::assertSame('', $out, '编码失败应返回空串，而不是伪造 encode-error 节点');
        self::assertTrue($renderer->lastEncodeFailed());
    }

    public function testRenderGraphProducesGraphContainer(): void
    {
        $html = $this->f->renderGraph([
            $this->f->person(['name' => 'A']),
            $this->f->organization(['name' => 'B']),
        ]);

        self::assertStringContains('"@graph"', $html);
        self::assertStringContains('"A"', $html);
    }

    public function testRenderManyAsSeparateScripts(): void
    {
        $html = (new Renderer())->renderMany([
            ['@context' => 'https://schema.org', '@type' => 'Person', 'name' => 'A'],
            ['@context' => 'https://schema.org', '@type' => 'Person', 'name' => 'B'],
        ], false);

        self::assertSame(2, substr_count($html, '<script'));
    }

    public function testWrapInComment(): void
    {
        $renderer = (new Renderer())->wrapInComment(true, 'Person');
        $html     = $renderer->render(['@context' => 'https://schema.org', '@type' => 'Person']);

        self::assertStringContains('<!-- JSON-LD Person -->', $html);
    }

    /* ---------- Validator ---------- */

    public function testValidatorDetectsMissingRequired(): void
    {
        $issues   = $this->f->validate($this->f->article([]));
        $messages = array_column($issues, 'message');

        $joined = implode("\n", $messages);
        self::assertStringContains('headline', $joined);
        self::assertStringContains('datePublished', $joined);
    }

    public function testValidatorDetectsMissingContext(): void
    {
        $issues = (new Validator())->validate(['@type' => 'Person', 'name' => 'x']);
        $paths  = array_column($issues, 'path');

        self::assertContains('@context', $paths);
    }

    public function testValidatorFlagsUnencodableValues(): void
    {
        $issues = (new Validator())->preflight([
            '@context' => 'https://schema.org',
            '@type'    => 'Thing',
            'bad'      => NAN,
        ]);
        $messages = implode("\n", array_column($issues, 'message'));

        self::assertStringContains('NaN', $messages);
    }

    public function testValidatorFlagsObjects(): void
    {
        $issues = (new Validator())->preflight([
            '@context' => 'https://schema.org',
            '@type'    => 'Thing',
            'obj'      => new \stdClass(),
        ]);

        self::assertTrue(count($issues) > 0, '对象应被判为不可序列化');
    }

    public function testValidatorChecksDeepNesting(): void
    {
        $deep = 'leaf';
        for ($i = 0; $i < 40; $i++) {
            $deep = ['n' => $deep];
        }

        $issues = (new Validator())->preflight([
            '@context' => 'https://schema.org',
            '@type'    => 'Thing',
            'deep'     => $deep,
        ]);
        $messages = implode("\n", array_column($issues, 'message'));

        self::assertStringContains('嵌套层级过深', $messages);
    }

    public function testPassesIgnoresWarningsByDefault(): void
    {
        $validator = new Validator();

        // 仅有警告（缺必填字段）时，非严格模式应判定通过
        $warnings = $validator->validate([
            '@context' => 'https://schema.org',
            '@type'    => Type::ARTICLE,
        ]);
        self::assertTrue($validator->passes($warnings), '仅警告不应判定为失败');

        $validator->strict();
        self::assertFalse($validator->passes($warnings), '严格模式下警告应算失败');
    }

    public function testPassesFailsOnError(): void
    {
        $validator = new Validator();
        // 缺 @context 是 error 级别
        $issues = $validator->validate(['@type' => Type::PERSON, 'name' => 'x']);

        self::assertFalse($validator->passes($issues));
    }

    public function testSummaryFormat(): void
    {
        $validator = new Validator();
        self::assertStringContains('校验通过', $validator->summary([]));

        $issues = $validator->validate(['@type' => 'Person', 'name' => '']);
        self::assertStringContains('共', $validator->summary($issues));
    }

    public function testEmptyNodeIsError(): void
    {
        $issues = (new Validator())->validate([]);
        self::assertTrue(count($issues) > 0);
    }

    /* ---------- Type / Enum ---------- */

    public function testTypeHierarchyAndAncestry(): void
    {
        self::assertTrue(Type::exists(Type::BLOG_POSTING));
        self::assertFalse(Type::exists('NotAType'));

        self::assertTrue(Type::isA(Type::BLOG_POSTING, Type::ARTICLE));
        self::assertTrue(Type::isA(Type::LOCAL_BUSINESS, Type::ORGANIZATION));
        self::assertTrue(Type::isA(Type::BLOG_POSTING, Type::BLOG_POSTING), '自身应算作实例');
        self::assertFalse(Type::isA(Type::PERSON, Type::ARTICLE));
    }

    public function testTypeAllReturnsStrings(): void
    {
        foreach (Type::all() as $type) {
            self::assertTrue(is_string($type) && $type !== '', '类型表不应含空值');
        }
    }

    public function testEnumGroups(): void
    {
        self::assertContains(Enum::AVAILABILITY_IN_STOCK, Enum::group('availability'));
        self::assertCount(0, Enum::group('nonexistent'));
        self::assertContains(Enum::DAY_MONDAY, Enum::dayOfWeek());
    }

    /* ---------- 注册表 ---------- */

    public function testRegisterAndReference(): void
    {
        $this->f->register('https://e.com/#org', Type::ORGANIZATION, ['name' => 'Org']);
        $ref = $this->f->reference('https://e.com/#org');

        self::assertSame(['@id' => 'https://e.com/#org'], $ref);
        self::assertArrayHasKey('https://e.com/#org', $this->f->registered());
    }

    public function testReferenceRejectsUnregistered(): void
    {
        $this->f->register('https://e.com/#a', Type::PERSON);
        self::assertThrows(InvalidArgumentException::class, function (): void {
            $this->f->reference('https://e.com/#missing');
        });
    }

    public function testRegisterRejectsInvalidInput(): void
    {
        $f = $this->f;
        self::assertThrows(InvalidArgumentException::class, static function () use ($f): void {
            $f->register('', Type::PERSON);
        });
        self::assertThrows(InvalidArgumentException::class, static function () use ($f): void {
            $f->register('id', 'NotAType');
        });
    }

    public function testClearRegistry(): void
    {
        $this->f->register('x', Type::PERSON);
        $this->f->clearRegistry();

        self::assertCount(0, $this->f->registered());
    }

    /* ---------- build ---------- */

    public function testBuildOutputsScriptAndMarksIssues(): void
    {
        // 缺 @context 属于 error 级别 → 应输出 JSON-LD 并附带问题注释
        $html = $this->f->build(function (SchemaForge $f): array {
            return ['@type' => 'Person', 'name' => '林墨'];
        });

        self::assertStringContains('<script', $html);
        self::assertStringContains('validation issues', $html);
    }

    public function testBuildOmitsCommentWhenOnlyWarnings(): void
    {
        // 仅缺必填字段（warning）不算失败，不应输出问题注释
        $html = $this->f->build(function (SchemaForge $f): SchemaNode {
            return $f->article([]);
        });

        self::assertStringContains('<script', $html);
        self::assertStringNotContains('validation issues', $html);
    }

    public function testBuildCommentFollowsScriptTag(): void
    {
        $html = $this->f->build(function (SchemaForge $f): array {
            return ['@type' => 'Person'];
        });

        // 注释必须位于 script 之后，避免被当成脚本内容
        self::assertTrue(strpos($html, '</script>') < strpos($html, '<!--'));
    }

    public function testBuildCleanNodeHasNoWarningComment(): void
    {
        $html = $this->f->build(function (SchemaForge $f): SchemaNode {
            return $f->person(['name' => '林墨']);
        });

        self::assertStringNotContains('validation issues', $html);
    }
}
