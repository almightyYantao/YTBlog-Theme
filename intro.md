---
title: YTBlog Theme + FluxgridEditor —— 一套现代化的 Typecho 写作组合
date: 2026-04-25
categories: 工具
tags: Typecho, 主题, 插件
---

[scode type="share"]
**TL;DR** —— [tag type="primary"]YTBlog Theme[/tag] 是一款互联网科技风的 Typecho 主题，[tag type="success"]FluxgridEditor[/tag] 是它的官方配套写作插件。两者一起使用时，可以得到一个粒子背景 + 代码窗口 Hero 的现代化博客 + Vditor 三模式 Markdown 编辑器 + 兰空图床 / S3 直传的完整方案。
[/scode]

## 为什么再造一个轮子

Typecho 默认主题简单可靠，但放在 2026 年看，已经稍显朴素：

[column]
[block size=50%]
**默认主题**

- 静态首页，无沉浸式 Hero
- Markdown 写作只有一个朴素 textarea
- 图片附件挤在本地 `usr/uploads`
- 短代码生态几乎为零
[/block]
[block size=50%]
**YTBlog Theme + FluxgridEditor**

- 粒子背景 + Hero 轮播 + 代码窗口
- Vditor 三模式（IR / SV / WYSIWYG）
- 图片走兰空、附件走 S3，自动分流
- 内置 9 种短代码，覆盖排版常见场景
[/block]
[/column]

## YTBlog Theme 的核心特性

### 首页 Hero —— 让落地页有「态度」

首页顶部会自动抓取最新 5 篇带封面的文章做轮播，左侧显示标题 / 摘要 / CTA，右侧是动态的代码窗口卡片，把站点信息渲染成 JS 对象的样子。整体配上粒子背景，第一眼就能看出和默认主题的差异。

[button color="primary" url="/" icon="→"]看看首页效果[/button] [button color="dark" type="round" url="https://github.com"]在 GitHub 查看源码[/button]

### 短代码系统 —— 不写 HTML 也能排版

主题内置 9 种短代码，全部支持 Markdown 内嵌，互相也可以嵌套。下面这些就是用短代码写出来的：

[tabs]
[tab name="标签 tag" active="true"]
六种语义化颜色：
[tag type="primary"]Primary[/tag] [tag type="info"]Info[/tag] [tag type="success"]Success[/tag] [tag type="warning"]Warning[/tag] [tag type="danger"]Danger[/tag] [tag type="dark"]Dark[/tag]

```
[tag type="primary"]Primary[/tag]
```
[/tab]
[tab name="按钮 button"]
四种圆角尺寸 + 八种主题色：
[button color="success" url="#"]Success[/button] [button color="primary" type="round" url="#"]Round[/button] [button color="warning" url="#" icon="⚡"]带图标[/button]

```
[button color="success" url="https://..."]按钮文字[/button]
```
[/tab]
[tab name="折叠 collapse"]
[collapse title="点我展开示例代码"]
```bash
# 短代码会被解析成 <details>，原生支持键盘和无障碍
echo "Hello World"
```
[/collapse]

```
[collapse title="点我展开"]内容[/collapse]
```
[/tab]
[tab name="时间线 timeline"]
[timeline title="开发里程碑" type="small"]
[item date="2026.04.10" color="primary"]主题骨架 & Hero 区落地[/item]
[item date="2026.04.18" color="success"]短代码系统 + 加密模块完成[/item]
[item date="2026.04.25" color="warning"]FluxgridEditor 插件配套发布[/item]
[/timeline]
[/tab]
[/tabs]

### 多层内容加密 —— 从一段话到全站

对于「半私密」内容，主题内置了六种粒度的加密能力，可按需组合：

[timeline title="加密粒度（从粗到细）" type="small"]
[item date="L1" color="danger"]**全站密码** — 后台填一个密码，全站含 RSS 都需输入才能访问[/item]
[item date="L2" color="warning"]**分类加密** — 整个分类的文章全部锁住，列表 / 归档自动隐藏[/item]
[item date="L3" color="info"]**单篇加密** — 用 Typecho 原生的文章密码功能，主题做好兼容[/item]
[item date="L4" color="primary"]**自定义页面字段** — 给特定页面加访问密码[/item]
[item date="L5" color="success"]`[login]` — 段落级，登录用户可见[/item]
[item date="L6" color="dark"]`[reply]` — 评论审核通过后可见，常用于隐藏下载链接[/item]
[/timeline]

[scode type="yellow"]
**注意** —— 加密模块基于 HMAC + Cookie 实现，密码本身不会落到前端。但 Typecho 没有开箱即用的 CSRF 防护，建议生产环境配合 HTTPS + 强密码使用。
[/scode]

### 其他能写进首页 README 的细节

- **GitHub 个人页** —— `page-github.php` 模板，输入用户名即可拉取仓库列表 / 贡献热力图
- **暗色 / 亮色双主题** —— 顶栏右上角一键切换，状态写入 `localStorage`
- **粒子背景** —— Canvas 实现，自动根据屏幕分辨率调节粒子密度
- **PJAX-friendly** —— 主题脚本封装在 `initPageContent()`，切换页面无需重新初始化
- **响应式** —— 4 个断点（>1280 / 1060 / 768 / 480）覆盖桌面到手机

## FluxgridEditor —— 配套的写作引擎

Typecho 默认编辑器是一个朴素 textarea，写一篇带图带数学公式的长文几乎是不可能的。FluxgridEditor 用 [tag type="info"]Vditor 3.10[/tag] 替换默认编辑器，提供三种模式：

[column]
[block size=33.3%]
**即时渲染 IR**

类 Typora 体验，所见即所得但保留 Markdown 标记。
推荐日常使用。
[/block]
[block size=33.3%]
**分屏 SV**

左源码、右预览。
适合写技术文章 / 调试 Markdown 表现。
[/block]
[block size=33.3%]
**所见即所得**

类 Word，完全可视化。
适合从 Word/Typora 迁移过来的用户。
[/block]
[/column]

### 图片和附件的自动分流

[scode type="lblue"]
**核心思路** —— 图片走兰空图床，附件走 S3，全部自动判断不需要手动选。
[/scode]

```text
拖拽 / 粘贴 / 上传
        │
        ▼
   是图片 ?
    ├── 是 → 兰空 Pro v2 API（直传或外链转存）
    └── 否 → S3 兼容存储（PUT 走 AWS Sig v4）
                ↑
            未配置 S3 时回退到 Typecho 默认附件
```

支持的存储后端：

[column]
[block size=50%]
**图床（图片）**
- [tag type="primary"]兰空 Pro v2[/tag]
- Typecho 自带（默认 fallback）
[/block]
[block size=50%]
**对象存储（附件）**
- [tag type="info"]AWS S3[/tag] [tag type="info"]Cloudflare R2[/tag]
- [tag type="info"]Backblaze B2[/tag] [tag type="info"]MinIO[/tag]
- 任何兼容 S3 API 的存储
[/block]
[/column]

### 外链图自动转存

粘贴一张外站图片，插件会经服务端中继抓取后转存到兰空，避免外链失效或防盗链问题。中继路由用 `relay.php` 直连，绕开 Typecho action 路由不稳定的坑。

[collapse title="为什么要走 relay.php 而不是 Typecho action？"]
Typecho 的 `Helper::addAction()` 在 [tag type="warning"]部分 PHP / Nginx 组合[/tag] 下注册不稳，特别是开了 `try_files` 但没正确配置 rewrite 时会 404。直接通过 `usr/plugins/FluxgridEditor/relay.php` 访问可以独立 bootstrap Typecho 内核，绕开路由层。代价是需要 `usr/plugins/` 目录可被 PHP 解析，但这本身就是 Typecho 的默认行为。
[/collapse]

## 安装

[timeline title="安装步骤" type="large"]
[item date="01" color="primary"]
**下载主题和插件**

把 `blog_theme/` 整个目录上传到 `usr/themes/`，把 `FluxgridEditor/` 上传到 `usr/plugins/`。
[/item]
[item date="02" color="info"]
**启用主题**

后台 → 控制台 → 外观 → 启用 [tag type="primary"]YTBlog Theme[/tag]。
[/item]
[item date="03" color="success"]
**启用插件**

后台 → 控制台 → 插件 → 启用 [tag type="success"]FluxgridEditor[/tag]，然后在插件设置里填兰空 Token 和 S3 凭证（可选）。
[/item]
[item date="04" color="warning"]
**配置主题**

外观 → 设置外观，填头像、横幅图、加密分类等。
[/item]
[/timeline]

## 一些坑和折中

[scode type="red"]
**已知限制**

- Vditor 体积偏大（~700KB gzipped），首次进入编辑器会有短暂加载
- 兰空 v1 不支持，必须 v2
- 加密模块依赖 cookie，浏览器禁 cookie 时不可用
- 主题暂不支持 Gutenberg 风格的块编辑（也不打算支持）
[/scode]

## 写在最后

主题 + 插件这套组合是给 [tag type="dark"]工程师博主[/tag] 准备的：要的是写起来顺手、看起来不土、出问题能直接读源码定位。如果你也是这类用户，欢迎试用并提 issue。

[button color="primary" type="round" url="https://github.com" icon="★"]给个 Star[/button] [button color="dark" type="round" url="/contact/"]反馈建议[/button]

[reply]隐藏的下载链接：https://example.com/download.zip[/reply]
