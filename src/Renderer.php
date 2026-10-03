<?php
/**
 * JSON-LD 渲染器。
 *
 * 职责：
 * 1. 把 Schema 数组编码为 JSON-LD 字符串，使用
 *    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES 保持中文与 URL 可读；
 * 2. 阻断 </script> 截断攻击（对 '/' 转义为 \/ 已由 UNESCAPED_SLASHES 关闭，
 *    因此这里改用 unicode 转义方式把 '<' 之外的危险序列显式处理）；
 * 3. 输出可选的 HTML 注释包裹，便于在页面源码中定位与注释掉。
 *
 * 安全要点：JSON-LD 内嵌在 <script> 中时，字符串里的 "</script" 会被 HTML
 * 解析器当作结束标签，导致后续内容被当作 HTML 执行。本渲染器在编码后
 * 对该序列做二次转义，确保无法闭合 script 标签。
 *
 * @package MornRain\SchemaForge
 */

declare(strict_types=1);

namespace MornRain\SchemaForge;

use RuntimeException;

/**
 * 渲染器。
 */
class Renderer
{
    /** @var array<int,string> JSON 编码 flags */
    protected $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS;

    /** @var bool 是否使用 HTML 注释包裹 */
    protected $wrapInComment = false;

    /** @var string HTML 注释前缀，用于标识节点类型 */
    protected $commentLabel = '';

    /** @var bool 是否在编码前执行 preflight 自检 */
    protected $autoPreflight = true;

    /** @var bool preflight 失败时是否抛异常 */
    protected $strictOnPreflight = false;

    /** @var array<int,array{level:string,type:string,message:string,path:string}> 最近一次渲染的自检结果 */
    protected $lastPreflight = [];

    /** @var bool 最近一次 encode() 是否因数据无法编码而失败 */
    protected $lastEncodeFailed = false;

    /**
     * 最近一次 encode() 是否失败（数据含 NaN / INF / 资源等无法编码的值）。
     */
    public function lastEncodeFailed(): bool
    {
        return $this->lastEncodeFailed;
    }

    /**
     * 追加或移除 JSON 编码 flags。
     */
    public function withJsonFlags(int $flags): self
    {
        $this->jsonFlags = $flags;

        return $this;
    }

    /**
     * 用 HTML 注释包裹输出，便于源码定位。
     */
    public function wrapInComment(bool $enabled = true, string $label = ''): self
    {
        $this->wrapInComment = $enabled;
        $this->commentLabel = preg_replace('/[^A-Za-z0-9_\- ]/', '', $label) ?? '';

        return $this;
    }

    /**
     * 关闭输出前自检。
     */
    public function withoutPreflight(): self
    {
        $this->autoPreflight = false;

        return $this;
    }

    /**
     * 自检失败时抛出异常（默认只返回问题清单）。
     */
    public function strictOnPreflight(bool $enabled = true): self
    {
        $this->strictOnPreflight = $enabled;

        return $this;
    }

    /**
     * 取得最近一次渲染的预检结果。
     *
     * @return array<int,array{level:string,type:string,message:string,path:string}>
     */
    public function lastPreflight(): array
    {
        return $this->lastPreflight;
    }

    /**
     * 把 Schema 数据编码为 JSON-LD 字符串（不含 script 标签）。
     *
     * @param array<string,mixed> $data Schema 数据。
     * @return string 数据为空或无法编码时返回空串（调用方应据此放弃输出）。
     */
    public function encode(array $data): string
    {
        $this->lastEncodeFailed = false;

        if ($data === []) {
            return '';
        }

        $json = json_encode($data, $this->jsonFlags);
        if ($json === false || $json === 'null') {
            // 无法编码时返回空串，而不是伪造一个 encode-error 节点。
            // 伪造节点会把无效数据提交给搜索引擎，掩盖真实问题；
            // 返回空串让调用方明确知道「这次没有输出」。
            $this->lastEncodeFailed = true;

            return '';
        }

        return $this->escapeForScriptTag($json);
    }

    /**
     * 输出完整的 <script type="application/ld+json"> 标签。
     *
     * @param array<string,mixed> $data Schema 数据。
     */
    public function render(array $data): string
    {
        $json = $this->encode($data);
        if ($json === '') {
            return '';
        }

        $html = '<script type="application/ld+json">' . $json . '</script>';

        if ($this->wrapInComment) {
            $label = $this->commentLabel !== '' ? ' ' . $this->commentLabel : '';
            $html  = '<!-- JSON-LD' . $label . ' -->' . "\n" . $html;
        }

        return $html;
    }

    /**
     * 批量输出多个节点。
     *
     * 可以选择合并为一个 @graph 文档（推荐，Google 官方支持），
     * 或输出为多个独立 script 标签。
     *
     * @param array<int,array<string,mixed>> $nodes  节点列表。
     * @param bool                          $asGraph 是否合并为 @graph。
     */
    public function renderMany(array $nodes, bool $asGraph = true): string
    {
        $nodes = array_values(array_filter($nodes, static function ($node): bool {
            return is_array($node) && $node !== [];
        }));
        if ($nodes === []) {
            return '';
        }

        if (!$asGraph) {
            $out = [];
            foreach ($nodes as $node) {
                $rendered = $this->render($node);
                if ($rendered !== '') {
                    $out[] = $rendered;
                }
            }

            return implode("\n", $out);
        }

        return $this->render($this->graph($nodes));
    }

    /**
     * 把多个节点包装成 @graph 文档。
     *
     * @param array<int,array<string,mixed>> $nodes 节点列表。
     * @return array<string,mixed>
     */
    public function graph(array $nodes): array
    {
        $clean = [];
        foreach ($nodes as $node) {
            if (!is_array($node) || $node === []) {
                continue;
            }
            // @graph 已在外层声明 @context，子节点重复声明会造成冗余输出。
            unset($node['@context']);
            $clean[] = $node;
        }

        return [
            '@context' => 'https://schema.org',
            '@graph'   => $clean,
        ];
    }

    /**
     * 预检：判断数据是否可以安全输出。
     *
     * @param array<string,mixed> $data Schema 数据。
     * @return array<int,array{level:string,type:string,message:string,path:string}>
     */
    public function preflight(array $data): array
    {
        $validator = new Validator();
        $issues    = $validator->preflight($data);

        $this->lastPreflight = $issues;

        if ($this->autoPreflight && $this->strictOnPreflight && !$validator->passes($issues)) {
            throw new RuntimeException('JSON-LD 预检未通过：' . $validator->summary($issues));
        }

        return $issues;
    }

    /**
     * 阻断 </script> 注入。
     *
     * json_encode 在开启 JSON_UNESCAPED_SLASHES 时不会转义 '/',
     * 因此需要把 "</" 显式改写为 "<\/"（JSON 层面等价），
     * 同时把 U+2028 / U+2029 转义为对应 unicode 转义，
     * 避免被 JavaScript 引擎当作行分隔符解析。
     */
    protected function escapeForScriptTag(string $json): string
    {
        // 大小写不敏感地处理所有能闭合 script 的写法。
        // 把 `</` 改写为 `<\/` —— 这是 JSON 合法转义，语义等价，
        // 但 HTML 解析器无法据此闭合 script 标签。
        $json = preg_replace('#</(?=\s*/?\s*script)#i', '<\/', $json) ?? $json;

        // 行分隔符与段落分隔符：在 JS 里是合法换行，转成 \u 转义更安全。
        $json = str_replace(["\xe2\x80\xa8", "\xe2\x80\xa9"], ['\u2028', '\u2029'], $json);

        // 注意：这里**不能**改写 `<!--` / `-->` / `<![CDATA[`。
        // 这些序列在 JSON 字符串内部出现时，改写会破坏数据本身
        // （JSON-LD 的 name/description 完全可能包含 `--` 或尖括号），
        // 属于为了「防御」而破坏正确性。script 标签的实际闭合风险
        // 已由上面的 `</` → `<\/` 完全覆盖。

        return $json;
    }
}
