# hitokoto

基于 [hitokoto-osc/sentences-bundle](https://github.com/hitokoto-osc/sentences-bundle) 的轻量 PHP 一言接口，适合自部署使用。

当前本地语句包版本见 `sentences/version.json`。

## 参数

### 句子类型

`c` 和 `type` 都可以指定类型，支持单个或多个字符，例如 `?c=a`、`?type=ai`。

| 参数 | 类型 |
| ---- | ---- |
| a | 动画 |
| b | 漫画 |
| c | 游戏 |
| d | 文学 |
| e | 原创 |
| f | 来自网络 |
| g | 其他 |
| h | 影视 |
| i | 诗词 |
| j | 网易云 |
| k | 哲学 |
| l | 抖机灵 |

### 输出格式

`do` 和 `encode` 都可以指定输出格式，默认 `json`。

| 参数 | 说明 |
| ---- | ---- |
| json | 返回 JSON |
| text | 只返回一言正文 |

### 其他参数

| 参数 | 说明 |
| ---- | ---- |
| min_length | 最小句子长度 |
| max_length | 最大句子长度 |
| charset | 响应编码，目前支持 `utf-8` |
| callback | JSONP 回调函数名，仅 JSON 输出可用 |
| health | 健康检查，传 `1` 返回语句包版本、分类数量和文件状态 |

## 示例

```txt
/index.php
/index.php?do=text
/index.php?c=a&encode=json
/index.php?type=ai&min_length=5&max_length=30
/index.php?encode=json&callback=handleHitokoto
/index.php?health=1
```

JSON 返回示例：

```json
{
  "id": 7285,
  "uuid": "0e4d2234-2862-4acc-80ff-cfde681123c6",
  "hitokoto": "灭六国者，非秦也，六国也。",
  "type": "i",
  "from": "阿房宫赋",
  "from_who": "杜牧",
  "creator": "朱佳熹",
  "creator_uid": 9963,
  "reviewer": 9975,
  "commit_from": "web",
  "created_at": "1627917866",
  "length": 13
}
```

错误返回示例：

```json
{
  "error": "Invalid category.",
  "status": 400
}
```

## Docker

```bash
docker compose up -d
```

默认映射到 `http://127.0.0.1:8081`。

## 更新语句包

```powershell
.\scripts\update-sentences.ps1
```

脚本会下载官方 `version.json` 和 `a-l` 分类文件，全部 JSON 校验通过后再替换本地文件，并生成 `sentences/manifest.json`。

`manifest.json` 会记录每个分类的句子数，接口会优先使用它来完成健康检查和多分类加权随机。

## 测试

```bash
php tests/run.php
```

## 授权

本项目沿用原项目授权，见 [LICENSE](LICENSE)。使用、分发或修改时请遵守相关开源许可证要求。
