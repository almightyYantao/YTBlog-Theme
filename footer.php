<?php if (!defined('__TYPECHO_ROOT_DIR__')) { exit; } ?>
    <footer class="site-footer">
        <div class="flux-container footer-inner">
            <div class="footer-copy">
                <span class="footer-title">© <?php echo date('Y'); ?> <?php echo fluxgrid_escape(fluxgrid_option($this->options, 'logoText', (string) $this->options->title)); ?>.</span>
                <span><?php echo fluxgrid_escape(fluxgrid_option($this->options, 'footerText', (string) $this->options->description)); ?></span>
            </div>
            <div class="footer-meta">
                <a href="<?php $this->options->siteUrl(); ?>">首页</a>
                <a href="<?php $this->options->feedUrl(); ?>">RSS</a>
            </div>
        </div>
    </footer>
</div>
<div class="pjax-progress-bar" id="pjax-progress" aria-hidden="true"></div>
<button type="button" class="back-to-top" id="back-to-top" aria-label="回到顶部" data-no-swup>
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 15l-6-6-6 6"/></svg>
</button>
<div class="link-redirect-modal" id="link-redirect-modal" role="dialog" aria-modal="true" aria-labelledby="link-redirect-title" hidden>
    <div class="link-redirect-dialog">
        <div class="link-redirect-head">
            <span class="link-redirect-icon" aria-hidden="true">↗</span>
            <h3 id="link-redirect-title">即将打开外部链接</h3>
        </div>
        <div class="link-redirect-body">
            <p>你正在离开本站访问外部网站，请确认链接安全：</p>
            <div class="link-redirect-url" id="link-redirect-url"></div>
        </div>
        <div class="link-redirect-actions">
            <button type="button" class="link-redirect-cancel" data-redirect-cancel>取消</button>
            <a class="link-redirect-continue" id="link-redirect-continue" target="_blank" rel="noopener noreferrer">继续打开 →</a>
        </div>
        <div class="link-redirect-countdown"><span id="link-redirect-seconds">5</span> 秒后自动打开新窗口</div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/glightbox@3.3.0/dist/js/glightbox.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/DIYgod/OwO@master/dist/OwO.min.css">
<script src="https://cdn.jsdelivr.net/gh/DIYgod/OwO@master/dist/OwO.min.js"></script>
<script src="<?php echo fluxgrid_escape(fluxgrid_asset_url($this->options, 'assets/js/theme.js')); ?>"></script>
<?php $this->footer(); ?>
</body>
</html>
