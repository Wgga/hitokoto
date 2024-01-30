# hitokoto

## 说明

此处只进行存储供本人使用，感兴趣者请前往[原作者 lxyddice](https://github.com/lxyddice)的[hitokoto](https://github.com/lxyddice/hitokoto) Public

基于[https://github.com/hitokoto-osc/sentences-bundle](https://github.com/hitokoto-osc/sentences-bundle)的php版，快速部署

## 参数说明

#### 句子类型（参数）

| 参数 | 说明               |
| ---- | ------------------ |
| a    | 动画               |
| b    | 漫画               |
| c    | 游戏               |
| d    | 文学               |
| e    | 原创               |
| f    | 来自网络           |
| g    | 其他               |
| h    | 影视               |
| j    | 网易云             |
| k    | 哲学               |
| i    | 诗词               |
| l    | 抖机灵             |

#### 返回编码类型（参数）

| 参数 | 说明                                                  |
| ---- | ----------------------------------------------------- |
| text | 返回纯洁文本                                          |
| json | 返回格式化后的 JSON 文本                              |

#### 返回参数名称描述

| id          | 一言标识                                                     |
| ----------- | ------------------------------------------------------------ |
| hitokoto    | 一言正文。编码方式 unicode。使用 utf-8。                     |
| type        | 类型。请参考句子类型（参数）的表格                           |
| from        | 一言的出处                                                   |
| from_who    | 一言的作者                                                   |
| creator     | 添加者                                                       |
| creator_uid | 添加者用户标识                                               |
| reviewer    | 审核员标识                                                   |
| uuid        | 一言唯一标识；可以链接到 https://hitokoto.cn?uuid=[uuid] 查看这个一言的完整信息 |
| commit_from | 提交方式                                                     |
| created_at  | 添加时间                                                     |
| length      | 句子长度                                                     |

## 使用方法

纯文本：https://api.lxyddice.top/api/yiyan?do=text

返回示例：

```txt
灭六国者，非秦也，六国也。
```

json：https://api.lxyddice.top/api/yiyan

返回示例：

```json
{
    "id": 7285,
    "uuid": "0e4d2234-2862-4acc-80ff-cfde681123c6",
    "hitokoto": "灭六国者，非秦也，六国也。",
    "type": "i",
    "from": "阿房宫赋",
    "from_who": "杜牧",
    "creator": "朱佳熠",
    "creator_uid": 9963,
    "reviewer": 9975,
    "commit_from": "web",
    "created_at": "1627917866",
    "length": 13
}
```

## 本库遵循 AGPL 开源授权，您在使用本库时需要遵循 AGPL 授权的相关规定。这通常意味着：您在使用、分发、修改、扩充等涉及本库的操作时您需要开源您的修改作品。
