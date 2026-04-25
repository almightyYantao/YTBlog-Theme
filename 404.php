<?php if (!defined('__TYPECHO_ROOT_DIR__')) { exit; } ?>
<?php $this->need('header.php'); ?>
<main class="site-main">
    <section class="page-banner error-banner">
        <div class="flux-container page-banner-inner" style="text-align: center;">
            <span class="eyebrow" style="margin-left: auto; margin-right: auto;">// 404</span>
            <h1>页面不存在</h1>
            <p style="margin-left: auto; margin-right: auto;">你访问的内容可能已经移动，或者链接本身有误。可以返回首页继续浏览。</p>
            <div class="hero-actions" style="justify-content: center;">
                <a class="button button-primary" href="<?php $this->options->siteUrl(); ?>">返回首页 →</a>
            </div>
        </div>
    </section>
</main>
<?php $this->need('footer.php'); ?>
