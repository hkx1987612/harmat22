# Harmat Local Assistant

这是 Harmat22 网站用的本地知识库客服插件。

## 功能

- 不连接外部 AI 接口，不需要 OpenAI API。
- 使用插件目录里的 `data/harmat_apartments_kb.json` 回答户型、面积、房间数、价格和状态。
- 支持匈牙利语、中文和英文的常见问题。
- 默认在网站右下角显示客服按钮。
- 支持短代码：`[harmat_local_assistant]`。
- 不保存聊天记录。

## 上传安装

1. 把整个 `harmat-local-assistant` 文件夹上传到 WordPress 的 `wp-content/plugins/`。
2. 在 WordPress 后台启用 `Harmat Local Assistant`。
3. 打开网站前台，右下角会出现 `Kérdezzen` 按钮。

## 资料更新

如果价格或户型资料有变化，只需要替换：

- `data/harmat_apartments_kb.json`

替换后刷新网站即可。插件不会改动网站原有页面和数据库。

## 测试问题

- `A1-F-L1 多少钱？`
- `Van 2 szobás lakás?`
- `70 millió Ft körül mit ajánl?`
- `Mikor várható az átadás?`
- `可以贷款吗？`

## 注意

插件会显示资料库里的参考价格。最终价格和可售状态仍应由销售团队确认。
