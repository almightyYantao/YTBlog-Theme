<?php if (!defined('__TYPECHO_ROOT_DIR__')) { exit; } ?>
<?php $this->need('header.php'); ?>
<?php
$pageCid = isset($this->cid) ? (int) $this->cid : 0;
$pageBannerImage = fluxgrid_safe_image_url(fluxgrid_post_cover($this), $this->options);
if ($pageBannerImage === '' && $pageCid > 0) {
    $pageBannerImage = fluxgrid_fallback_image($this->options, $pageCid);
}
$pageExcerpt = fluxgrid_excerpt($this, 140);
?>
<main class="site-main">
    <article class="article-shell article-page">
        <header class="page-banner">
            <div class="page-banner-media">
                <img
                    src="<?php echo fluxgrid_escape($pageBannerImage); ?>"
                    alt=""
                    loading="eager"
                    onerror="<?php echo fluxgrid_escape(fluxgrid_image_fallback_script($this->options)); ?>"
                >
            </div>
            <div class="page-banner-surface"></div>

            <div class="flux-container page-banner-inner">
                <span class="eyebrow">// PAGE</span>
                <h1><?php $this->title(); ?></h1>
                <?php if ($pageExcerpt !== ''): ?>
                    <p><?php echo fluxgrid_escape($pageExcerpt); ?></p>
                <?php endif; ?>
            </div>
        </header>

        <div class="flux-container article-container">
            <div class="article-layout" style="grid-template-columns: minmax(0, 1fr);">
                <div class="article-main">
                    <div class="article-content">
                        <?php fluxgrid_render_content($this); ?>
                    </div>
                </div>
            </div>
        </div>
    </article>

    <?php $this->need('comments.php'); ?>
</main>
<?php $this->need('footer.php'); ?>
