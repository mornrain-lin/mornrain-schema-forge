# Changelog

本项目遵循 [语义化版本 2.0.0](https://semver.org/lang/zh-CN/)。

## [1.0.1] - 2026-10-03

### Fixed

- **JSON-LD 数据被破坏**：`Renderer::escapeForScriptTag()` 会把数据中的
  `<!--` / `-->` 改写为 `<` / `>`。但 `name`、`description` 等字段完全可能包含
  这些序列，改写会静默篡改输出内容——为了「防御」而破坏正确性。
  现移除该改写：`</script>` 的闭合风险已由 `</` → `<\/` 完全覆盖。
- **校验失败时静默输出伪造节点**：`encode()` 遇到无法编码的数据（如 `NAN`）时，
  会伪造一个 `{"@type":"Thing","name":"encode-error"}` 节点提交给搜索引擎，
  掩盖真实问题。现改为返回空串，并新增 `lastEncodeFailed()` 让调用方可判定。
- **`mainEntityOfPage()` 未校验 `@type`**：非法类型会原样进入 JSON-LD，
  导致搜索引擎丢弃整个节点。现与 `setType()` 保持一致的校验。
- **`geo()` 接受 `NAN` / `INF` 与越界坐标**：`NAN` 会让 `json_encode` 整体失败，
  节点被输出为空；越界值（如把毫度当度传）会产生无效数据。现直接抛
  `InvalidArgumentException`。
- **`aggregateRating()` 接受 `NAN`**：同样会导致整个节点无法编码，现已拒绝。
- `SchemaForge::build()` 的校验提示注释从 `<script>` **之前**移到**之后**，
  避免被误认为脚本内容的一部分。

### Added

- `tests/`：48 个用例 / 188 条断言，覆盖 13 个构造方法、链式设置、
  `</script>` 转义、必填校验、不可编码值检测、类型继承与注册表。
- `tests/run-tests.php`：零依赖测试运行器。
- `phpunit.xml.dist`、`phpcs.xml.dist`（PSR-12）、`CONTRIBUTING.md`、`SECURITY.md`。

## [1.0.0] - 2026-10-02

### 新增

- `Type`：76 个 schema.org 类型常量 + 继承关系表
  - `hierarchy()` 返回父子类型映射
  - `all()` / `exists()` 类型表查询
  - `ancestry()` 广度优先展开完整继承链
  - `isA()` 判定子类型是否属于目标基类
- `Enum`：库存、成色、活动参与方式、活动状态、星期、货币等枚举值，
  以及对应的 `group()` 分组查询
- `SchemaNode`：可链式设置的 Schema 节点
  - 通用式 `set()` / `setMany()` / `replace()` / `remove()` / `push()` / `merge()`
  - 领域语义式 `headline()` `datePublished()` `author()` `publisher()`
    `aggregateRating()` `offer()` `geo()` `openingHours()` `keywords()` 等
  - 自动归一：相对地址补全、日期转 ISO 8601、货币码大写
  - `setType()` 对未知类型抛 `InvalidArgumentException`
  - 空值（null / 空串 / 空数组）自动跳过，避免输出空节点
- `SchemaForge`：门面类，13 个构造方法
  - `article()` `product()` `person()` `organization()` `website()`
    `breadcrumb()` `faq()` `localBusiness()` `event()` `recipe()`
    `videoObject()` `softwareApplication()` `howTo()` `itemList()`
  - 附加 `review()` 与 `raw()` 用于未覆盖类型
  - 实体注册表 `register()` / `reference()` / `registered()` / `clearRegistry()`，
    实现 `@id` 引用去重
  - `render()` / `renderGraph()` / `validate()` / `build()` 快捷输出
- `Validator`：三层校验
  - 必填属性表（20 个类型）
  - 属性值类型校验（URL / ISO8601 / 数值 / 时长 / 语言标记 / 货币 / 枚举）
  - `preflight()` 输出前自检，检出 NaN、INF、不可序列化对象、过深嵌套、空串
  - `passes()` / `summary()` 结果判定与可读摘要
  - 三级问题级别：error / warning / notice
- `Renderer`：JSON-LD 输出
  - `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS`
  - `escapeForScriptTag()` 阻断 `</script>` 注入（含大小写与空白变体），
    并转义 U+2028 / U+2029
  - `render()` / `renderMany()` / `graph()` / `encode()`
  - `wrapInComment()` HTML 注释包裹，便于源码定位
  - `strictOnPreflight()` 预检失败时抛异常
- `examples/generate.php`：16 个场景的可运行示例，含 </script> 注入验证

[1.0.0]: https://github.com/MornRain/morn-schema-forge/releases/tag/v1.0.0
