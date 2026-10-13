/**
 * Indigenous Heritage Management System - client interactions.
 * All behaviour is attached here (no inline handlers) so the Content-Security-Policy can forbid inline scripts.
 */

document.addEventListener('DOMContentLoaded', function () {
    // ===== Public mobile navigation =====
    const navToggle = document.querySelector('.nav-toggle');
    const navMenu = document.querySelector('.nav-menu');
    if (navToggle && navMenu) {
        navToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            const open = navMenu.classList.toggle('active');
            navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('click', function (event) {
            if (!navToggle.contains(event.target) && !navMenu.contains(event.target)) {
                navMenu.classList.remove('active');
                navToggle.setAttribute('aria-expanded', 'false');
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                navMenu.classList.remove('active');
                navToggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    // ===== Admin sidebar toggle (phones and small tablets) =====
    const adminToggle = document.querySelector('.admin-menu-toggle');
    const adminSidebar = document.querySelector('.admin-sidebar');
    if (adminToggle && adminSidebar) {
        adminToggle.addEventListener('click', function () {
            const open = adminSidebar.classList.toggle('is-open');
            adminToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    // ===== Auto-submit filter selects =====
    document.querySelectorAll('select[data-autosubmit]').forEach(function (select) {
        select.addEventListener('change', function () {
            if (select.form) {
                select.form.submit();
            }
        });
    });

    // ===== Confirmation dialogs for destructive actions =====
    document.querySelectorAll('[data-confirm]').forEach(function (button) {
        button.addEventListener('click', function (e) {
            if (!window.confirm(button.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });

    // ===== Selected-file preview for uploads =====
    document.querySelectorAll('[data-file-preview-for]').forEach(function (list) {
        const input = document.getElementById(list.getAttribute('data-file-preview-for'));
        if (!input) {
            return;
        }
        input.addEventListener('change', function () {
            list.innerHTML = '';
            Array.prototype.forEach.call(input.files, function (file) {
                const item = document.createElement('div');
                item.className = 'file-preview-item';
                if (file.type.indexOf('image/') === 0) {
                    const img = document.createElement('img');
                    img.alt = '';
                    img.src = URL.createObjectURL(file);
                    img.onload = function () { URL.revokeObjectURL(img.src); };
                    item.appendChild(img);
                } else {
                    const icon = document.createElement('span');
                    icon.textContent = '📄';
                    item.appendChild(icon);
                }
                const name = document.createElement('span');
                name.className = 'file-preview-name';
                name.textContent = file.name + ' (' + Math.max(1, Math.round(file.size / 1024)) + ' KB)';
                item.appendChild(name);
                list.appendChild(item);
            });
        });
    });

    // ===== Gallery lightbox =====
    const galleryCards = document.querySelectorAll('.gallery-card');
    if (galleryCards.length > 0) {
        const lightbox = document.createElement('div');
        lightbox.className = 'lightbox';
        lightbox.setAttribute('role', 'dialog');
        lightbox.setAttribute('aria-modal', 'true');
        lightbox.innerHTML =
            '<div class="lightbox-content-box">' +
            '<button type="button" class="lightbox-close" aria-label="Close">&times;</button>' +
            '<div class="lightbox-img-wrap"><img class="lightbox-image" src="" alt=""></div>' +
            '<div class="lightbox-caption"><h3 class="lightbox-title"></h3><p class="lightbox-desc"></p></div>' +
            '</div>';
        document.body.appendChild(lightbox);

        const lbImage = lightbox.querySelector('.lightbox-image');
        const lbTitle = lightbox.querySelector('.lightbox-title');
        const lbDesc = lightbox.querySelector('.lightbox-desc');
        const lbClose = lightbox.querySelector('.lightbox-close');
        let closeTimer = null;

        const openLightbox = function (src, title, desc, alt) {
            lbImage.src = src;
            lbImage.alt = alt || title;
            lbTitle.textContent = title;
            lbDesc.textContent = desc;
            clearTimeout(closeTimer);
            lightbox.classList.remove('is-closing');
            lightbox.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            requestAnimationFrame(function () {
                requestAnimationFrame(function () { lightbox.classList.add('is-open'); });
            });
            lbClose.focus();
        };

        const closeLightbox = function () {
            if (lightbox.style.display !== 'flex') {
                return;
            }
            lightbox.classList.remove('is-open');
            lightbox.classList.add('is-closing');
            closeTimer = setTimeout(function () {
                lightbox.style.display = 'none';
                lightbox.classList.remove('is-closing');
                lbImage.src = '';
                document.body.style.overflow = '';
            }, 240);
        };

        galleryCards.forEach(function (card) {
            const img = card.querySelector('img');
            if (!img) {
                return;
            }
            card.setAttribute('tabindex', '0');
            const open = function () {
                const title = (card.querySelector('.gallery-card-title') || {}).textContent || img.alt || 'Heritage image';
                const desc = (card.querySelector('.gallery-card-desc') || {}).textContent || '';
                openLightbox(img.getAttribute('data-full') || img.src, title.trim(), desc.trim(), img.alt);
            };
            card.addEventListener('click', function (e) {
                if (e.target.closest('a')) {
                    return;
                }
                open();
            });
            card.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    open();
                }
            });
        });

        lbClose.addEventListener('click', closeLightbox);
        lightbox.addEventListener('click', function (e) {
            if (e.target === lightbox) {
                closeLightbox();
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeLightbox();
            }
        });
    }

    // ===== FAQ accordion =====
    document.querySelectorAll('.faq-question').forEach(function (question) {
        question.addEventListener('click', function () {
            const item = question.parentElement;
            const wasActive = item.classList.contains('active');
            document.querySelectorAll('.faq-item').forEach(function (other) { other.classList.remove('active'); });
            if (!wasActive) {
                item.classList.add('active');
            }
        });
    });

    // ===== Animated stat counters (display the real value, never inflate it) =====
    const statElements = document.querySelectorAll('.hero-stat-num, .admin-stat-value');
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (statElements.length > 0 && 'IntersectionObserver' in window && !reduceMotion) {
        const statObserver = new IntersectionObserver(function (entries, observer) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) {
                    return;
                }
                const el = entry.target;
                observer.unobserve(el);
                const text = el.textContent.trim();
                if (!/^\d+$/.test(text)) {
                    return;
                }
                const target = parseInt(text, 10);
                if (target < 2) {
                    return;
                }
                const suffix = el.getAttribute('data-suffix') || '';
                let current = 0;
                const increment = Math.max(1, Math.ceil(target / 40));
                const timer = setInterval(function () {
                    current = Math.min(target, current + increment);
                    el.textContent = current + (current === target ? suffix : '');
                    if (current === target) {
                        clearInterval(timer);
                    }
                }, 25);
            });
        }, { threshold: 0.2 });
        statElements.forEach(function (stat) { statObserver.observe(stat); });
    }

    // ===== Back-to-top button =====
    const backToTop = document.createElement('button');
    backToTop.type = 'button';
    backToTop.className = 'back-to-top';
    backToTop.setAttribute('aria-label', 'Scroll to top');
    backToTop.textContent = '▲';
    document.body.appendChild(backToTop);
    window.addEventListener('scroll', function () {
        backToTop.style.display = window.scrollY > 400 ? 'flex' : 'none';
    }, { passive: true });
    backToTop.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
    });

    // ===== Reveal cards as they scroll into view =====
    if (!reduceMotion && 'IntersectionObserver' in window) {
        const revealItems = document.querySelectorAll('.card-grid > .card, .category-grid > .category-tile, .gallery-grid > .gallery-card');
        const revealObserver = new IntersectionObserver(function (entries, observer) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) {
                    return;
                }
                const target = entry.target;
                target.classList.add('is-visible');
                observer.unobserve(target);
                const clear = function () {
                    target.classList.remove('reveal-pending', 'is-visible');
                    target.style.animationDelay = '';
                };
                target.addEventListener('animationend', clear, { once: true });
                setTimeout(clear, 1200);
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -32px 0px' });

        revealItems.forEach(function (el, index) {
            if (el.getBoundingClientRect().top < window.innerHeight * 0.92) {
                return;
            }
            el.classList.add('reveal-pending');
            el.style.animationDelay = Math.min((index % 6) * 0.08, 0.4) + 's';
            revealObserver.observe(el);
        });
    }
});
