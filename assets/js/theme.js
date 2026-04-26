(function () {
  var reducedMotionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
  var pageCleanups = [];
  var currentLightbox = null;

  // 平台检测:Mac 上把 .search-hint-mac (⌘) 显示出来,其他平台显示 Ctrl
  var isMac = /Mac|iPhone|iPad|iPod/i.test(navigator.platform || navigator.userAgent || '');
  if (isMac) { document.documentElement.classList.add('is-mac'); }

  // 全局 ⌘K / Ctrl+K → 聚焦搜索框 (一次性绑定,不需 PJAX 重置)
  document.addEventListener('keydown', function (e) {
    if ((e.metaKey || e.ctrlKey) && (e.key === 'k' || e.key === 'K')) {
      var input = document.getElementById('site-search-input');
      if (input) {
        e.preventDefault();
        input.focus();
        input.select();
      }
    }
  });

  var runPageCleanups = function () {
    pageCleanups.forEach(function (fn) {
      try { fn(); } catch (e) {}
    });
    pageCleanups = [];
  };

  // ==================== one-time inits ====================

  var initNav = function () {
    var toggle = document.querySelector('.nav-toggle');
    var nav = document.getElementById('site-nav');
    if (!toggle || !nav) { return; }

    var setNavState = function (isOpen) {
      toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      nav.classList.toggle('is-open', isOpen);
    };

    toggle.addEventListener('click', function () {
      var expanded = toggle.getAttribute('aria-expanded') === 'true';
      setNavState(!expanded);
    });

    nav.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () { setNavState(false); });
    });

    document.addEventListener('click', function (event) {
      if (!nav.classList.contains('is-open')) { return; }
      if (nav.contains(event.target) || toggle.contains(event.target)) { return; }
      setNavState(false);
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') { setNavState(false); }
    });

    window.addEventListener('resize', function () {
      if (window.innerWidth > 1120) { setNavState(false); }
    });
  };

  var initHeaderScroll = function () {
    var header = document.getElementById('site-header');
    if (!header) { return; }

    var syncHeader = function () {
      if (window.scrollY > 24) {
        header.classList.add('is-scrolled');
      } else {
        header.classList.remove('is-scrolled');
      }
    };

    syncHeader();
    window.addEventListener('scroll', syncHeader, { passive: true });
  };

  var initBackToTop = function () {
    var btn = document.getElementById('back-to-top');
    if (!btn) { return; }

    var sync = function () {
      if (window.scrollY > 480) {
        btn.classList.add('is-visible');
      } else {
        btn.classList.remove('is-visible');
      }
    };

    sync();
    window.addEventListener('scroll', sync, { passive: true });

    btn.addEventListener('click', function () {
      var prefersReduced = reducedMotionQuery.matches;
      window.scrollTo({ top: 0, behavior: prefersReduced ? 'auto' : 'smooth' });
    });
  };

  var initThemeToggle = function () {
    var toggle = document.getElementById('theme-toggle');
    if (!toggle) { return; }

    var applyTheme = function (theme) {
      document.documentElement.setAttribute('data-theme', theme);
      try { localStorage.setItem('fluxgrid-theme', theme); } catch (e) {}
      document.dispatchEvent(new CustomEvent('fluxgrid:themechange', { detail: { theme: theme } }));
    };

    toggle.addEventListener('click', function () {
      var current = document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
      applyTheme(current === 'light' ? 'dark' : 'light');
    });
  };

  var initParticleBackground = function () {
    var canvas = document.getElementById('particle-bg');
    if (!canvas || !canvas.getContext) { return; }
    if (reducedMotionQuery.matches) { return; }

    var ctx = canvas.getContext('2d');
    var width = 0;
    var height = 0;
    var particles = [];
    var maxDist = 140;
    var rafId = null;
    var visible = !document.hidden;

    var resize = function () {
      var dpr = Math.min(window.devicePixelRatio || 1, 2);
      width = canvas.clientWidth;
      height = canvas.clientHeight;
      canvas.width = width * dpr;
      canvas.height = height * dpr;
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    };

    var seed = function () {
      var count = Math.max(28, Math.min(95, Math.floor((width * height) / 17000)));
      particles = [];
      for (var i = 0; i < count; i++) {
        particles.push({
          x: Math.random() * width,
          y: Math.random() * height,
          vx: (Math.random() - 0.5) * 0.24,
          vy: (Math.random() - 0.5) * 0.24,
          r: Math.random() * 1.2 + 0.4
        });
      }
    };

    var palette = {
      dark: { dot: 'rgba(147, 197, 253, 0.9)', lineBase: 'rgba(147, 197, 253, ', shadow: 'rgba(96, 165, 250, 0.85)', lineMax: 0.55 },
      light: { dot: 'rgba(37, 99, 235, 0.55)', lineBase: 'rgba(37, 99, 235, ', shadow: 'rgba(37, 99, 235, 0.4)', lineMax: 0.38 }
    };

    var currentPalette = function () {
      var theme = document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
      return palette[theme];
    };

    var draw = function () {
      if (!visible) {
        rafId = window.requestAnimationFrame(draw);
        return;
      }

      ctx.clearRect(0, 0, width, height);
      var pal = currentPalette();

      for (var i = 0; i < particles.length; i++) {
        var p = particles[i];
        p.x += p.vx;
        p.y += p.vy;
        if (p.x < 0 || p.x > width) { p.vx *= -1; }
        if (p.y < 0 || p.y > height) { p.vy *= -1; }

        ctx.beginPath();
        ctx.arc(p.x, p.y, p.r + 0.4, 0, Math.PI * 2);
        ctx.fillStyle = pal.dot;
        ctx.shadowBlur = 6;
        ctx.shadowColor = pal.shadow;
        ctx.fill();
      }

      ctx.shadowBlur = 0;

      for (var m = 0; m < particles.length; m++) {
        for (var n = m + 1; n < particles.length; n++) {
          var dx = particles[m].x - particles[n].x;
          var dy = particles[m].y - particles[n].y;
          var dist = Math.sqrt(dx * dx + dy * dy);
          if (dist < maxDist) {
            var alpha = (1 - dist / maxDist) * pal.lineMax;
            ctx.strokeStyle = pal.lineBase + alpha + ')';
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(particles[m].x, particles[m].y);
            ctx.lineTo(particles[n].x, particles[n].y);
            ctx.stroke();
          }
        }
      }

      rafId = window.requestAnimationFrame(draw);
    };

    var restart = function () {
      resize();
      seed();
    };

    restart();
    window.addEventListener('resize', function () {
      window.clearTimeout(restart._t);
      restart._t = window.setTimeout(restart, 180);
    });
    document.addEventListener('visibilitychange', function () {
      visible = !document.hidden;
    });

    if (typeof reducedMotionQuery.addEventListener === 'function') {
      reducedMotionQuery.addEventListener('change', function (e) {
        if (e.matches && rafId) {
          window.cancelAnimationFrame(rafId);
          rafId = null;
          ctx.clearRect(0, 0, width, height);
        } else if (!e.matches && !rafId) {
          draw();
        }
      });
    }

    draw();
  };

  // ==================== per-page inits ====================

  var fitArticleTitle = function () {
    var titles = document.querySelectorAll('.article-copy-wide h1');

    titles.forEach(function (title) {
      title.style.fontSize = '';
      var computed = window.getComputedStyle(title);
      var size = parseFloat(computed.fontSize);
      var minSize = 24;
      while (title.scrollWidth > title.clientWidth && size > minSize) {
        size -= 1;
        title.style.fontSize = size + 'px';
      }
    });
  };

  var initArticleImageLightbox = function () {
    if (currentLightbox) {
      try { currentLightbox.destroy(); } catch (e) {}
      currentLightbox = null;
    }

    var articleImages = document.querySelectorAll('.article-content img');
    if (!articleImages.length || typeof GLightbox === 'undefined') { return; }

    articleImages.forEach(function (img) {
      var parent = img.parentNode;
      var link;

      if (parent && parent.tagName === 'A') {
        link = parent;
        link.classList.add('glightbox');
      } else {
        link = document.createElement('a');
        link.href = img.currentSrc || img.src;
        link.className = 'glightbox';
        parent.insertBefore(link, img);
        link.appendChild(img);
      }

      link.setAttribute('data-gallery', 'article');
      link.setAttribute('data-no-swup', '');
      if (!link.getAttribute('data-title') && img.alt) {
        link.setAttribute('data-title', img.alt);
      }
    });

    currentLightbox = GLightbox({
      selector: '.article-content a.glightbox',
      touchNavigation: true,
      keyboardNavigation: true,
      loop: true,
      closeOnOutsideClick: true,
      moreLength: 0
    });

    pageCleanups.push(function () {
      if (currentLightbox) {
        try { currentLightbox.destroy(); } catch (e) {}
        currentLightbox = null;
      }
    });
  };

  var initArticleToc = function () {
    var articleContent = document.querySelector('.article-content');
    var tocWrap = document.querySelector('[data-article-toc-wrap]');
    var toc = document.querySelector('[data-article-toc]');
    if (!articleContent || !tocWrap || !toc) { return; }

    // 排除短代码内部的标题(如 [timeline] 渲染的 h4.sc-timeline-title,
    // [collapse]、[tabs]、[scode] 等内部的 hN),否则 TOC 会塞进不属于
    // 文章正文骨架的项,scroll-spy 也会因为这些节点的 offsetTop 不规律错乱。
    var allHeadings = articleContent.querySelectorAll('h2, h3, h4');
    var shortcodeContainerSelector =
      '.sc-timeline, .sc-collapse, .sc-tabs, .sc-callout, .sc-column, .sc-block, .sc-tab-panel';
    var headings = Array.prototype.filter.call(allHeadings, function (h) {
      return !h.closest(shortcodeContainerSelector);
    });
    if (!headings.length) { return; }

    var slugify = function (text) {
      return text
        .toLowerCase()
        .replace(/<[^>]+>/g, '')
        .replace(/[^\w一-龥-]+/g, '-')
        .replace(/^-+|-+$/g, '') || 'section';
    };

    var usedIds = {};
    toc.innerHTML = '';

    headings.forEach(function (heading) {
      var text = (heading.textContent || '').trim();
      if (!text) { return; }

      var baseId = heading.id || slugify(text);
      var uniqueId = baseId;
      var suffix = 2;
      while (usedIds[uniqueId] || (document.getElementById(uniqueId) && document.getElementById(uniqueId) !== heading)) {
        uniqueId = baseId + '-' + suffix;
        suffix += 1;
      }
      usedIds[uniqueId] = true;
      heading.id = uniqueId;

      var link = document.createElement('a');
      link.href = '#' + uniqueId;
      link.textContent = text;
      link.setAttribute('data-level', heading.tagName.replace('H', ''));
      toc.appendChild(link);
    });

    if (!toc.children.length) { return; }
    tocWrap.hidden = false;

    var tocLinks = toc.querySelectorAll('a');
    var syncActiveLink = function () {
      var activeId = '';
      headings.forEach(function (heading) {
        var rect = heading.getBoundingClientRect();
        if (rect.top <= 140) { activeId = heading.id; }
      });
      tocLinks.forEach(function (link) {
        link.classList.toggle('is-active', link.getAttribute('href') === '#' + activeId);
      });
    };

    syncActiveLink();
    window.addEventListener('scroll', syncActiveLink, { passive: true });

    pageCleanups.push(function () {
      window.removeEventListener('scroll', syncActiveLink, { passive: true });
    });
  };

  var initHeroCarousel = function () {
    var carousels = document.querySelectorAll('[data-hero-carousel]');
    carousels.forEach(function (carousel) {
      var slides = carousel.querySelectorAll('[data-hero-slide]');
      if (slides.length <= 1) { return; }

      var prevButton = carousel.querySelector('[data-hero-prev]');
      var nextButton = carousel.querySelector('[data-hero-next]');
      var dots = carousel.querySelectorAll('[data-hero-dot]');
      var activeIndex = 0;
      var timerId = null;
      var interval = 5000;

      var canAutoplay = function () {
        return !reducedMotionQuery.matches && !document.hidden;
      };

      var setActiveSlide = function (index) {
        activeIndex = (index + slides.length) % slides.length;
        slides.forEach(function (slide, slideIndex) {
          slide.classList.toggle('is-active', slideIndex === activeIndex);
        });
        dots.forEach(function (dot, dotIndex) {
          dot.classList.toggle('is-active', dotIndex === activeIndex);
        });
      };

      var startAutoplay = function () {
        clearInterval(timerId);
        if (!canAutoplay()) { return; }
        timerId = window.setInterval(function () {
          setActiveSlide(activeIndex + 1);
        }, interval);
      };

      var stopAutoplay = function () { clearInterval(timerId); };

      if (prevButton) {
        prevButton.addEventListener('click', function () {
          setActiveSlide(activeIndex - 1);
          startAutoplay();
        });
      }

      if (nextButton) {
        nextButton.addEventListener('click', function () {
          setActiveSlide(activeIndex + 1);
          startAutoplay();
        });
      }

      dots.forEach(function (dot) {
        dot.addEventListener('click', function () {
          var index = Number(dot.getAttribute('data-hero-dot'));
          setActiveSlide(index);
          startAutoplay();
        });
      });

      carousel.addEventListener('mouseenter', stopAutoplay);
      carousel.addEventListener('mouseleave', startAutoplay);
      carousel.addEventListener('focusin', stopAutoplay);
      carousel.addEventListener('focusout', startAutoplay);

      var onVisibilityChange = function () {
        if (document.hidden) { stopAutoplay(); } else { startAutoplay(); }
      };
      document.addEventListener('visibilitychange', onVisibilityChange);

      if (typeof reducedMotionQuery.addEventListener === 'function') {
        reducedMotionQuery.addEventListener('change', startAutoplay);
      } else if (typeof reducedMotionQuery.addListener === 'function') {
        reducedMotionQuery.addListener(startAutoplay);
      }

      setActiveSlide(0);
      startAutoplay();

      pageCleanups.push(function () {
        stopAutoplay();
        document.removeEventListener('visibilitychange', onVisibilityChange);
      });
    });
  };

  var fallbackCopy = function (text, onDone) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    ta.style.top = '0';
    ta.style.left = '0';
    document.body.appendChild(ta);
    ta.focus();
    ta.select();
    var ok = false;
    try { ok = document.execCommand('copy'); } catch (e) {}
    document.body.removeChild(ta);
    if (onDone) { onDone(ok); }
  };

  var initCodeBlocks = function () {
    var pres = document.querySelectorAll('.article-content pre');
    if (!pres.length) { return; }

    pres.forEach(function (pre) {
      if (pre.querySelector('.code-bar')) { return; }

      var code = pre.querySelector('code');
      var lang = 'text';
      if (code && code.className) {
        var match = code.className.match(/language-([A-Za-z0-9+\-_.]+)/);
        if (match) { lang = match[1]; }
      }
      if (!code && pre.className) {
        var m2 = pre.className.match(/language-([A-Za-z0-9+\-_.]+)/);
        if (m2) { lang = m2[1]; }
      }

      var bar = document.createElement('div');
      bar.className = 'code-bar';
      bar.innerHTML =
        '<div class="code-bar-left">' +
          '<div class="code-bar-dots">' +
            '<span class="code-dot"></span>' +
            '<span class="code-dot"></span>' +
            '<span class="code-dot"></span>' +
          '</div>' +
          '<span class="code-lang"></span>' +
        '</div>' +
        '<button class="code-copy" type="button" aria-label="复制代码">复制</button>';

      bar.querySelector('.code-lang').textContent = lang;
      pre.insertBefore(bar, pre.firstChild);

      var copyBtn = bar.querySelector('.code-copy');
      copyBtn.addEventListener('click', function () {
        var text = code ? code.textContent : '';
        if (!text) {
          var clone = pre.cloneNode(true);
          var barClone = clone.querySelector('.code-bar');
          if (barClone) { barClone.parentNode.removeChild(barClone); }
          text = clone.textContent;
        }

        var showCopied = function (ok) {
          if (ok === false) {
            copyBtn.textContent = '失败';
          } else {
            copyBtn.textContent = '已复制';
            copyBtn.classList.add('is-copied');
          }
          window.setTimeout(function () {
            copyBtn.textContent = '复制';
            copyBtn.classList.remove('is-copied');
          }, 1500);
        };

        if (navigator.clipboard && window.isSecureContext) {
          navigator.clipboard.writeText(text).then(function () { showCopied(true); }, function () { fallbackCopy(text, showCopied); });
        } else {
          fallbackCopy(text, showCopied);
        }
      });
    });
  };

  var syncNavActive = function () {
    var nav = document.getElementById('site-nav');
    if (!nav) { return; }

    var normalize = function (p) {
      p = String(p || '').replace(/\/+$/, '');
      return p === '' ? '/' : p;
    };

    var currentPath = normalize(window.location.pathname);
    var links = nav.querySelectorAll('a');

    links.forEach(function (link) {
      var linkUrl;
      try { linkUrl = new URL(link.href, window.location.origin); } catch (e) { return; }
      if (linkUrl.origin !== window.location.origin) {
        link.classList.remove('is-active');
        return;
      }
      var linkPath = normalize(linkUrl.pathname);
      var match = false;
      if (linkPath === currentPath) {
        match = true;
      } else if (linkPath !== '/' && currentPath.indexOf(linkPath + '/') === 0) {
        match = true;
      }
      link.classList.toggle('is-active', match);
    });

    var hasChildrenActive = nav.querySelectorAll('.nav-item.has-children');
    hasChildrenActive.forEach(function (item) {
      var isActive = item.querySelector('a.is-active') !== null;
      item.classList.toggle('is-active', isActive);
      var topLink = item.querySelector(':scope > .nav-link');
      if (topLink && isActive) { topLink.classList.add('is-active'); }
    });
  };

  var initShortcodeTabs = function () {
    var wrappers = document.querySelectorAll('[data-sc-tabs]');
    if (!wrappers.length) { return; }
    wrappers.forEach(function (wrap) {
      var triggers = wrap.querySelectorAll('.sc-tab-trigger');
      var panels = wrap.querySelectorAll('.sc-tab-panel');

      var activate = function (idx) {
        triggers.forEach(function (t, i) {
          var active = i === idx;
          t.classList.toggle('is-active', active);
          t.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        panels.forEach(function (p, i) {
          var active = i === idx;
          p.classList.toggle('is-active', active);
          if (active) { p.removeAttribute('hidden'); } else { p.setAttribute('hidden', ''); }
        });
      };

      triggers.forEach(function (trigger, i) {
        trigger.addEventListener('click', function () { activate(i); });
        trigger.addEventListener('keydown', function (e) {
          if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
            e.preventDefault();
            var next = e.key === 'ArrowRight' ? (i + 1) % triggers.length : (i - 1 + triggers.length) % triggers.length;
            triggers[next].focus();
            activate(next);
          }
        });
      });
    });
  };

  var redirectState = {
    modal: null,
    urlEl: null,
    continueEl: null,
    secondsEl: null,
    cancelBtn: null,
    timerId: null,
    openTimer: null,
    onKey: null,
    onModalClick: null,
    current: ''
  };

  var closeRedirectModal = function () {
    if (!redirectState.modal) { return; }
    redirectState.modal.classList.remove('is-open');
    redirectState.modal.setAttribute('hidden', '');
    if (redirectState.timerId) { window.clearInterval(redirectState.timerId); redirectState.timerId = null; }
    if (redirectState.openTimer) { window.clearTimeout(redirectState.openTimer); redirectState.openTimer = null; }
    redirectState.current = '';
  };

  var openRedirectModal = function (url) {
    if (!redirectState.modal) { return; }
    redirectState.current = url;
    redirectState.urlEl.textContent = url;
    redirectState.continueEl.setAttribute('href', url);
    redirectState.modal.removeAttribute('hidden');
    // Force reflow then add class for transition
    redirectState.modal.offsetHeight;
    redirectState.modal.classList.add('is-open');

    var count = 5;
    redirectState.secondsEl.textContent = count;
    redirectState.timerId = window.setInterval(function () {
      count -= 1;
      if (count > 0) {
        redirectState.secondsEl.textContent = count;
      } else {
        window.clearInterval(redirectState.timerId);
        redirectState.timerId = null;
        try { window.open(redirectState.current, '_blank', 'noopener,noreferrer'); } catch (e) {}
        closeRedirectModal();
      }
    }, 1000);
  };

  var initExternalLinkRedirect = function () {
    if (!redirectState.modal) {
      var modal = document.getElementById('link-redirect-modal');
      if (!modal) { return; }
      redirectState.modal = modal;
      redirectState.urlEl = document.getElementById('link-redirect-url');
      redirectState.continueEl = document.getElementById('link-redirect-continue');
      redirectState.secondsEl = document.getElementById('link-redirect-seconds');
      redirectState.cancelBtn = modal.querySelector('[data-redirect-cancel]');

      if (redirectState.cancelBtn) {
        redirectState.cancelBtn.addEventListener('click', closeRedirectModal);
      }
      if (redirectState.continueEl) {
        redirectState.continueEl.addEventListener('click', function () {
          // Allow the anchor to open the link in a new tab, then close modal
          window.setTimeout(closeRedirectModal, 50);
        });
      }
      redirectState.onModalClick = function (event) {
        if (event.target === modal) { closeRedirectModal(); }
      };
      modal.addEventListener('click', redirectState.onModalClick);

      redirectState.onKey = function (event) {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
          closeRedirectModal();
        }
      };
      document.addEventListener('keydown', redirectState.onKey);
    }

    var articleLinks = document.querySelectorAll('.article-content a[href]');
    articleLinks.forEach(function (link) {
      if (link.dataset.fxExt === '1') { return; }
      // GLightbox 给图片包了一层 <a> 指向图床域名,不能当外链拦截,否则点图就弹跳转提示
      if (link.classList.contains('glightbox')) { return; }
      var href = link.getAttribute('href');
      if (!href || href.charAt(0) === '#' || /^(?:javascript|mailto|tel):/i.test(href)) { return; }

      var url;
      try { url = new URL(link.href, window.location.origin); } catch (e) { return; }
      if (url.origin === window.location.origin) {
        return;
      }

      link.dataset.fxExt = '1';
      link.classList.add('is-external');
      link.setAttribute('target', '_blank');
      link.setAttribute('rel', 'noopener noreferrer');

      link.addEventListener('click', function (event) {
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) { return; }
        if (event.button !== 0) { return; }
        event.preventDefault();
        openRedirectModal(link.href);
      });
    });
  };

  var initRevealOnScroll = function () {
    var selector = [
      '.post-stack .post-card',
      '.list-wrap .list-item',
      '.featured-grid .featured-card',
      '.sidebar .side-card',
      '.empty-panel',
      '.pagination-wrap',
      '.article-toc-panel',
      '.article-info',
      '.article-footer',
      '.comment-form-panel'
    ].join(', ');
    var targets = document.querySelectorAll(selector);
    if (!targets.length) { return; }

    if (reducedMotionQuery.matches || !('IntersectionObserver' in window)) {
      targets.forEach(function (el) { el.classList.add('is-visible'); });
      return;
    }

    var observer = new IntersectionObserver(function (entries) {
      var batch = [];
      entries.forEach(function (e) { if (e.isIntersecting) { batch.push(e.target); } });
      batch.forEach(function (el, idx) {
        var delay = Math.min(idx, 6) * 50;
        window.setTimeout(function () { el.classList.add('is-visible'); }, delay);
        observer.unobserve(el);
      });
    }, { rootMargin: '0px 0px -40px 0px', threshold: 0.05 });

    targets.forEach(function (el) { observer.observe(el); });

    pageCleanups.push(function () { observer.disconnect(); });
  };

  // ==================== 评论 emoji 选择器 ====================

  // wide=true → 宽度可变,渲染为可换行的横向 chip(适合长文本如颜文字)
  // 默认窄列网格(适合单字符 emoji)
  var EMOJI_GROUPS = [
    { name: '常用',  list: ['😀','😂','😅','😍','🥰','😘','😎','🤔','😏','😴','😭','😡','🥳','🤩','😇','🤗','🙄','😋','🤤','😬','🤯','🥺','😱','🫠','🫡','🤝'] },
    { name: '手势',  list: ['👍','👎','👏','🙌','🙏','💪','✌️','👌','🤙','🤘','✋','👋','🤚','🤞','🫶','💅'] },
    { name: '心情',  list: ['❤️','💔','💕','💖','💯','🔥','✨','⭐','🌟','💫','🎉','🎊','🥂','☕','🍻','🍰'] },
    { name: '动物',  list: ['🐶','🐱','🐭','🐰','🦊','🐻','🐼','🐨','🐯','🦁','🐮','🐷','🐸','🐵','🐔','🐧'] },
    { name: '物品',  list: ['💻','📱','⌨️','🖥️','💾','📷','🎧','🎮','📚','✏️','📝','📌','📎','💡','🔧','🚀'] },
    { name: '颜文字', wide: true, list: [
      '(´∀｀)', '(°▽°)', '(✿◠‿◠)', '(◕‿◕)', '(•‿•)', '(￣▽￣)',
      '(￣ω￣)', '(´；ω；`)', '(╥﹏╥)', '(ㅠ_ㅠ)', '(；・∀・)', '(¬_¬")',
      '(ಠ_ಠ)', '(￣へ￣)', '(；￣Д￣)', '(￣□￣;)', '(°ロ°)', '(•_•)',
      '(´◔౪◔)', '(´∀｀*)', '(✪ω✪)', '(★ω★)', '(灬º‿º灬)♡', '(っ˘ω˘ς)',
      '(*≧ω≦)', '(´｡• ω •｡`)', '(*/ω＼*)', '＼(^o^)／', 'ヽ(✿ﾟ▽ﾟ)ノ', '(งΦ_Φ)ง',
      'ʕ•ᴥ•ʔ', '(=^･ω･^=)', '(=^‥^=)', '٩(◕‿◕)۶', '٩(ˊᗜˋ*)و', 'ヾ(≧▽≦*)o',
      '(っ°Д°；)っ', '(ノಠ益ಠ)ノ彡', '(╯°□°）╯︵┻━┻', '┬─┬ノ(゜-゜ノ)', '¯\\_(ツ)_/¯', '( ͡° ͜ʖ ͡°)',
    ]},
  ];

  var initCommentEmojiPicker = function () {
    var toggle = document.querySelector('[data-emoji-toggle]');
    var panel  = document.querySelector('[data-emoji-panel]');
    var area   = document.querySelector('[data-comment-text]');
    if (!toggle || !panel || !area) { return; }
    if (panel.dataset.fxEmojiInit === '1') { return; }
    panel.dataset.fxEmojiInit = '1';

    var insertAtCursor = function (text) {
      var start = area.selectionStart, end = area.selectionEnd;
      var before = area.value.substring(0, start);
      var after  = area.value.substring(end);
      area.value = before + text + after;
      area.focus();
      var pos = start + text.length;
      area.setSelectionRange(pos, pos);
    };

    EMOJI_GROUPS.forEach(function (g) {
      var h = document.createElement('div');
      h.className = 'comment-emoji-group-title';
      h.textContent = g.name;
      panel.appendChild(h);

      // 长文本(颜文字)用可换行 chip 流式布局, 单字符 emoji 用固定列网格
      var grid = document.createElement('div');
      grid.className = g.wide ? 'comment-emoji-flow' : 'comment-emoji-grid';

      g.list.forEach(function (s) {
        var b = document.createElement('button');
        b.type = 'button';
        b.textContent = s;
        b.addEventListener('click', function (ev) {
          ev.preventDefault();
          insertAtCursor(s);
        });
        grid.appendChild(b);
      });
      panel.appendChild(grid);
    });

    var open = function () {
      panel.hidden = false;
      setTimeout(function () {
        document.addEventListener('click', onDoc, true);
        document.addEventListener('keydown', onEsc, true);
      }, 0);
    };
    var close = function () {
      panel.hidden = true;
      document.removeEventListener('click', onDoc, true);
      document.removeEventListener('keydown', onEsc, true);
    };
    var onDoc = function (e) {
      if (panel.contains(e.target) || toggle.contains(e.target)) { return; }
      close();
    };
    var onEsc = function (e) { if (e.key === 'Escape') { close(); } };

    toggle.addEventListener('click', function (e) {
      e.preventDefault();
      if (panel.hidden) { open(); } else { close(); }
    });

    pageCleanups.push(close);
  };

  // ==================== 站点统计 modal (ECharts 懒加载) ====================

  var ECHARTS_CDN = 'https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js';
  var echartsLoadingPromise = null;
  var statsCharts = [];
  var statsKeyHandler = null;

  var loadECharts = function () {
    if (window.echarts) { return Promise.resolve(window.echarts); }
    if (echartsLoadingPromise) { return echartsLoadingPromise; }
    echartsLoadingPromise = new Promise(function (resolve, reject) {
      var s = document.createElement('script');
      s.src = ECHARTS_CDN;
      s.async = true;
      s.onload  = function () { resolve(window.echarts); };
      s.onerror = function () { echartsLoadingPromise = null; reject(new Error('ECharts 加载失败')); };
      document.head.appendChild(s);
    });
    return echartsLoadingPromise;
  };

  var statsTheme = function () {
    return document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
  };

  var statsTextColor = function () {
    return statsTheme() === 'light' ? '#475569' : '#cbd5e1';
  };
  var statsAxisColor = function () {
    return statsTheme() === 'light' ? '#cbd5e1' : '#334155';
  };

  var renderStatsCharts = function (echarts, data) {
    statsCharts.forEach(function (c) { try { c.dispose(); } catch (e) {} });
    statsCharts = [];

    var textColor = statsTextColor();
    var axisColor = statsAxisColor();
    var palette = ['#3b82f6', '#22c55e', '#f59e0b', '#ef4444', '#a855f7', '#06b6d4', '#ec4899', '#10b981', '#f97316', '#6366f1'];

    var commonGrid = {
      left: 50, right: 16, top: 24, bottom: 30, containLabel: true
    };

    // ── 1) 动态日历热力图 ────────────────────────────────────
    if (data.heatmap && data.heatmap.length) {
      var max = 0;
      data.heatmap.forEach(function (e) { if (e[1] > max) max = e[1]; });
      var heatEl = document.getElementById('stats-chart-heatmap');
      if (heatEl) {
        var heatChart = echarts.init(heatEl, null, { renderer: 'canvas' });
        heatChart.setOption({
          tooltip: {
            formatter: function (p) {
              return p.value[0] + '<br>📝 文章 ' + (p.value[2] || 0) + ' · 💬 评论 ' + (p.value[3] || 0);
            }
          },
          visualMap: {
            min: 0, max: Math.max(max, 1),
            type: 'continuous',
            orient: 'horizontal',
            left: 'center',
            bottom: 0,
            inRange: { color: ['#0f172a22', '#3b82f6', '#22c55e'] },
            textStyle: { color: textColor },
            itemWidth: 12, itemHeight: 80
          },
          calendar: {
            range: [data.startDate, data.endDate],
            cellSize: ['auto', 16],
            top: 18, left: 30, right: 30,
            splitLine: { show: false },
            itemStyle: { borderWidth: 2, borderColor: 'transparent', color: statsTheme() === 'light' ? '#f1f5f9' : '#1e293b' },
            yearLabel: { show: false },
            monthLabel: { color: textColor, fontSize: 11 },
            dayLabel: { color: textColor, fontSize: 10, firstDay: 1, nameMap: ['日','一','二','三','四','五','六'] }
          },
          series: [{
            type: 'heatmap',
            coordinateSystem: 'calendar',
            data: data.heatmap.map(function (e) { return [e[0], e[1], e[2], e[3]]; })
          }]
        });
        statsCharts.push(heatChart);
      }
    }

    // ── 2) 分类雷达图 ───────────────────────────────────────
    if (data.categories && data.categories.length) {
      var radarEl = document.getElementById('stats-chart-radar');
      if (radarEl) {
        var topCats = data.categories.slice(0, 6);
        var radarMax = 0;
        topCats.forEach(function (c) { if (c.count > radarMax) radarMax = c.count; });
        var radarChart = echarts.init(radarEl);
        radarChart.setOption({
          tooltip: {},
          radar: {
            indicator: topCats.map(function (c) { return { name: c.name, max: Math.ceil(radarMax * 1.2) }; }),
            axisName: { color: textColor, fontSize: 11 },
            splitLine: { lineStyle: { color: axisColor } },
            splitArea: { show: false },
            axisLine: { lineStyle: { color: axisColor } }
          },
          series: [{
            type: 'radar',
            data: [{
              value: topCats.map(function (c) { return c.count; }),
              name: '文章数',
              areaStyle: { color: 'rgba(59, 130, 246, 0.28)' },
              lineStyle: { color: '#3b82f6', width: 2 },
              itemStyle: { color: '#3b82f6' }
            }]
          }]
        });
        statsCharts.push(radarChart);
      }
    }

    // ── 3) 月度发布柱图 ─────────────────────────────────────
    if (data.monthly && data.monthly.length) {
      var monEl = document.getElementById('stats-chart-monthly');
      if (monEl) {
        var monChart = echarts.init(monEl);
        monChart.setOption({
          tooltip: { trigger: 'axis' },
          legend: { textStyle: { color: textColor }, top: 0, right: 8 },
          grid: commonGrid,
          xAxis: {
            type: 'category',
            data: data.monthly.map(function (m) { return m.month; }),
            axisLine: { lineStyle: { color: axisColor } },
            axisLabel: { color: textColor, fontSize: 11 }
          },
          yAxis: {
            type: 'value',
            axisLine: { show: false },
            axisLabel: { color: textColor },
            splitLine: { lineStyle: { color: axisColor, type: 'dashed' } }
          },
          series: [
            { name: '文章', type: 'bar', barMaxWidth: 22, itemStyle: { color: '#3b82f6', borderRadius: [4,4,0,0] }, data: data.monthly.map(function (m) { return m.posts; }) },
            { name: '评论', type: 'bar', barMaxWidth: 22, itemStyle: { color: '#22c55e', borderRadius: [4,4,0,0] }, data: data.monthly.map(function (m) { return m.comments; }) }
          ]
        });
        statsCharts.push(monChart);
      }
    }

    // ── 4) 分类饼图 ─────────────────────────────────────────
    if (data.categories && data.categories.length) {
      var pieEl = document.getElementById('stats-chart-categories');
      if (pieEl) {
        var pieChart = echarts.init(pieEl);
        pieChart.setOption({
          tooltip: { trigger: 'item', formatter: '{b}: {c} ({d}%)' },
          legend: { orient: 'vertical', right: 8, top: 'middle', textStyle: { color: textColor, fontSize: 11 } },
          color: palette,
          series: [{
            type: 'pie',
            radius: ['42%', '68%'],
            center: ['38%', '50%'],
            avoidLabelOverlap: true,
            itemStyle: { borderRadius: 4, borderColor: statsTheme() === 'light' ? '#fff' : '#0f172a', borderWidth: 2 },
            label: { show: false },
            labelLine: { show: false },
            data: data.categories.map(function (c) { return { name: c.name, value: c.count }; })
          }]
        });
        statsCharts.push(pieChart);
      }
    }

    // ── 5) 标签 TOP 20 横向柱图 ───────────────────────────────
    if (data.tags && data.tags.length) {
      var tagEl = document.getElementById('stats-chart-tags');
      if (tagEl) {
        var tagChart = echarts.init(tagEl);
        var tagData = data.tags.slice().reverse(); // ECharts 横向 bar 倒序更直观
        tagChart.setOption({
          tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
          grid: { left: 70, right: 24, top: 8, bottom: 24, containLabel: true },
          xAxis: {
            type: 'value',
            axisLine: { show: false },
            axisLabel: { color: textColor },
            splitLine: { lineStyle: { color: axisColor, type: 'dashed' } }
          },
          yAxis: {
            type: 'category',
            data: tagData.map(function (t) { return t.name; }),
            axisLine: { lineStyle: { color: axisColor } },
            axisLabel: { color: textColor, fontSize: 11 }
          },
          series: [{
            type: 'bar',
            barMaxWidth: 14,
            itemStyle: {
              color: { type: 'linear', x: 0, y: 0, x2: 1, y2: 0,
                colorStops: [{ offset: 0, color: '#3b82f6' }, { offset: 1, color: '#22c55e' }] },
              borderRadius: [0, 4, 4, 0]
            },
            data: tagData.map(function (t) { return t.count; })
          }]
        });
        statsCharts.push(tagChart);
      }
    }
  };

  var resizeStatsCharts = function () {
    statsCharts.forEach(function (c) { try { c.resize(); } catch (e) {} });
  };

  var initStatsModal = function () {
    var toggle = document.getElementById('stats-toggle');
    var modal  = document.getElementById('stats-modal');
    if (!toggle || !modal || toggle.dataset.fxStatsInit === '1') { return; }
    toggle.dataset.fxStatsInit = '1';

    var openStats = function () {
      modal.hidden = false;
      document.body.style.overflow = 'hidden';
      var data = window.fluxgridStats || {};
      loadECharts().then(function (echarts) {
        // 等 modal 完成 layout 后再 init,这样宽高读取正确
        requestAnimationFrame(function () {
          renderStatsCharts(echarts, data);
        });
      }).catch(function (err) {
        console.error(err);
        var body = modal.querySelector('.stats-modal-body');
        if (body) {
          body.innerHTML = '<div class="stats-empty">图表库加载失败：' + err.message + '</div>';
        }
      });
      statsKeyHandler = function (e) { if (e.key === 'Escape') { closeStats(); } };
      document.addEventListener('keydown', statsKeyHandler, true);
      window.addEventListener('resize', resizeStatsCharts);
    };
    var closeStats = function () {
      modal.hidden = true;
      document.body.style.overflow = '';
      if (statsKeyHandler) {
        document.removeEventListener('keydown', statsKeyHandler, true);
        statsKeyHandler = null;
      }
      window.removeEventListener('resize', resizeStatsCharts);
    };

    toggle.addEventListener('click', openStats);
    modal.querySelectorAll('[data-stats-close]').forEach(function (el) {
      el.addEventListener('click', closeStats);
    });

    // 主题切换时重渲染
    var themeBtn = document.getElementById('theme-toggle');
    if (themeBtn) {
      themeBtn.addEventListener('click', function () {
        if (modal.hidden) { return; }
        setTimeout(function () {
          if (window.echarts && window.fluxgridStats) {
            renderStatsCharts(window.echarts, window.fluxgridStats);
          }
        }, 60);
      });
    }
  };

  var initPageContent = function () {
    runPageCleanups();
    syncNavActive();
    initHeroCarousel();
    initArticleImageLightbox();
    initArticleToc();
    initCodeBlocks();
    initShortcodeTabs();
    initExternalLinkRedirect();
    fitArticleTitle();
    initRevealOnScroll();
    initCommentEmojiPicker();
    initStatsModal();
  };

  // ==================== PJAX (AJAX navigation) ====================

  var progressBar = null;
  var progressTimer = null;
  var isNavigating = false;

  var showProgress = function () {
    if (!progressBar) { progressBar = document.getElementById('pjax-progress'); }
    if (!progressBar) { return; }
    window.clearTimeout(progressTimer);
    progressBar.style.transition = 'width 0.4s ease, opacity 0.3s ease';
    progressBar.style.opacity = '1';
    progressBar.style.width = '25%';
    progressTimer = window.setTimeout(function () {
      progressBar.style.width = '75%';
    }, 120);
  };

  var finishProgress = function () {
    if (!progressBar) { return; }
    window.clearTimeout(progressTimer);
    progressBar.style.width = '100%';
    progressTimer = window.setTimeout(function () {
      progressBar.style.opacity = '0';
      progressTimer = window.setTimeout(function () {
        progressBar.style.transition = 'none';
        progressBar.style.width = '0';
      }, 280);
    }, 150);
  };

  var shouldIntercept = function (link, event) {
    if (!link || link.tagName !== 'A') { return false; }
    if (event && (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey)) { return false; }
    if (event && event.button !== 0) { return false; }
    if (link.hasAttribute('data-no-swup') || link.hasAttribute('data-no-pjax')) { return false; }
    if (link.target && link.target !== '_self') { return false; }
    if (link.hasAttribute('download')) { return false; }

    var href = link.getAttribute('href');
    if (!href) { return false; }
    if (href.indexOf('#') === 0) { return false; }
    if (/^(javascript|mailto|tel|data):/i.test(href)) { return false; }

    var url;
    try { url = new URL(link.href, window.location.href); } catch (e) { return false; }
    if (url.origin !== window.location.origin) { return false; }

    if (/\.(jpg|jpeg|png|gif|webp|svg|ico|pdf|zip|rar|7z|tar|gz|mp3|mp4|mov|webm|xml|json|txt|csv)$/i.test(url.pathname)) { return false; }
    if (/\/admin\b/i.test(url.pathname)) { return false; }
    if (/\/feed\b/i.test(url.pathname)) { return false; }
    if (/action\/\w+/i.test(url.pathname)) { return false; }

    // Same URL (only hash change) — let browser handle it
    if (url.href === window.location.href) { return false; }
    if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) { return false; }

    return true;
  };

  var navigate = function (url, push) {
    if (isNavigating) { return; }
    isNavigating = true;

    document.documentElement.classList.add('is-animating');
    showProgress();

    var startedAt = Date.now();

    window.fetch(url, {
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }
    })
      .then(function (response) {
        if (!response.ok) { throw new Error('HTTP ' + response.status); }
        return response.text();
      })
      .then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var newMain = doc.querySelector('.site-main');
        if (!newMain) { throw new Error('No .site-main in response'); }

        var elapsed = Date.now() - startedAt;
        var minWait = Math.max(0, 220 - elapsed);

        window.setTimeout(function () {
          runPageCleanups();

          var oldMain = document.querySelector('.site-main');
          if (oldMain && oldMain.parentNode) {
            oldMain.parentNode.replaceChild(newMain, oldMain);
          }

          var newTitle = doc.querySelector('title');
          if (newTitle) { document.title = newTitle.textContent; }

          var newBody = doc.querySelector('body');
          if (newBody) { document.body.className = newBody.className; }

          var newDesc = doc.querySelector('meta[name="description"]');
          var currentDesc = document.querySelector('meta[name="description"]');
          if (newDesc && currentDesc) {
            currentDesc.setAttribute('content', newDesc.getAttribute('content'));
          }

          if (push !== false) {
            window.history.pushState({ fluxgrid: true }, '', url);
          }

          window.scrollTo(0, 0);
          document.documentElement.classList.remove('is-animating');

          initPageContent();
          finishProgress();
          isNavigating = false;
        }, minWait);
      })
      .catch(function () {
        isNavigating = false;
        document.documentElement.classList.remove('is-animating');
        finishProgress();
        window.location.href = url;
      });
  };

  var initPjax = function () {
    if (!window.fetch || !window.DOMParser || !window.history || !window.history.pushState) { return; }

    progressBar = document.getElementById('pjax-progress');

    document.addEventListener('click', function (event) {
      var link = event.target.closest ? event.target.closest('a') : null;
      if (!link && event.target.parentNode) {
        // fallback for older browsers
        var node = event.target;
        while (node && node.tagName !== 'A') { node = node.parentNode; }
        link = node;
      }
      if (!shouldIntercept(link, event)) { return; }
      event.preventDefault();
      navigate(link.href, true);
    });

    window.addEventListener('popstate', function (event) {
      if (event.state && event.state.fluxgrid) {
        navigate(window.location.href, false);
      }
    });

    if (!window.history.state || !window.history.state.fluxgrid) {
      window.history.replaceState({ fluxgrid: true }, '', window.location.href);
    }
  };

  // ==================== bootstrap ====================

  document.addEventListener('DOMContentLoaded', function () {
    initNav();
    initHeaderScroll();
    initThemeToggle();
    initBackToTop();
    initParticleBackground();
    initPageContent();
    window.addEventListener('resize', fitArticleTitle);
    initPjax();
  });
})();
