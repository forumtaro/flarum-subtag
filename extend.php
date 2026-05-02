<?php

namespace Forumtaro\Homepage;



use Flarum\Extend;
use Flarum\Discussion\Event\Saving;
use Flarum\Discussion\Event\Deleting;
use Flarum\Tags\Tag;
use Flarum\Frontend\Document;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Support\Facades\DB;

return [
    // ==================== EVENT LISTENERS ====================
    
    (new Extend\Event)
    ->listen(Saving::class, function (Saving $event) {
        $discussion = $event->discussion;
        if ($discussion->exists) return;
        
        $tags = isset($event->data['relationships']['tags']['data']) 
            ? $event->data['relationships']['tags']['data'] 
            : [];
        
        $deckTagId = 3;
        
        foreach ($tags as $tag) {
            $tagId = isset($tag['id']) ? (int)$tag['id'] : 0;
            
            if ($tagId === $deckTagId) {
                $title = isset($event->data['attributes']['title']) 
                    ? trim(strip_tags($event->data['attributes']['title'])) 
                    : '';
                
                $title = mb_substr($title, 0, 200, 'UTF-8');
                
                if (empty($title)) {
                    $title = 'discussion-' . time();
                }
                
                $discussion->afterSave(function ($discussion) use ($title) {
                    Tag::unguarded(function () use ($title, $discussion) {
                        $map = [
                            'а'=>'a','б'=>'b','в'=>'v','г'=>'h','ґ'=>'g',
                            'д'=>'d','е'=>'e','є'=>'ye','ж'=>'zh','з'=>'z',
                            'и'=>'y','і'=>'i','ї'=>'yi','й'=>'y','к'=>'k',
                            'л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p',
                            'р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f',
                            'х'=>'kh','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'shch',
                            'ь'=>'','ю'=>'yu','я'=>'ya',
                            'А'=>'A','Б'=>'B','В'=>'V','Г'=>'H','Ґ'=>'G',
                            'Д'=>'D','Е'=>'E','Є'=>'Ye','Ж'=>'Zh','З'=>'Z',
                            'И'=>'Y','І'=>'I','Ї'=>'Yi','Й'=>'Y','К'=>'K',
                            'Л'=>'L','М'=>'M','Н'=>'N','О'=>'O','П'=>'P',
                            'Р'=>'R','С'=>'S','Т'=>'T','У'=>'U','Ф'=>'F',
                            'Х'=>'Kh','Ц'=>'Ts','Ч'=>'Ch','Ш'=>'Sh','Щ'=>'Shch',
                            'Ь'=>'','Ю'=>'Yu','Я'=>'Ya',
                            '’'=>'', '\''=>'', '"'=>'', '«'=>'', '»'=>'',
                            '—'=>'-', '–'=>'-', ' '=>'-', '_'=>'-',
                            '№'=>'No', '#'=>'No',
                        ];
                        
                        $slug = str_replace(array_keys($map), array_values($map), $title);
                        $slug = mb_strtolower($slug, 'UTF-8');
                        $slug = preg_replace('/[^\x20-\x7E]/', '', $slug);
                        $slug = preg_replace('/[^A-Za-z0-9-]+/', '-', $slug);
                        $slug = trim(preg_replace('/-+/', '-', $slug), '-');
                        
                        if (mb_strlen($slug) > 100) {
                            $slug = mb_substr($slug, 0, 100, 'UTF-8');
                            $slug = trim($slug, '-');
                        }
                        
                        if (empty($slug)) {
                            $slug = 'discussion-' . $discussion->id;
                        }
                        
                        // ====== НОВА ЛОГІКА ======
                        // Спочатку шукаємо існуючий тег за назвою (name)
                        $existingTag = Tag::where('name', $title)
                            ->where('color', '#8a2be2')
                            ->first();
                        
                        if ($existingTag) {
                            // Якщо тег з такою назвою вже існує - просто прикріплюємо його
                            $discussion->tags()->syncWithoutDetaching([$existingTag->id]);
                        } else {
                            // Якщо тегу немає - створюємо новий
                            // Перевіряємо унікальність slug
                            $originalSlug = $slug;
                            $counter = 1;
                            while (Tag::where('slug', $slug)->exists()) {
                                $slug = $originalSlug . '-' . $counter;
                                $counter++;
                            }
                            
                            $newTag = Tag::firstOrCreate(
                                ['slug' => $slug],
                                [
                                    'name'      => $title,
                                    'color'     => '#8a2be2',
                                    'is_hidden' => 0,
                                    'position'  => null,
                                    'parent_id' => null
                                ]
                            );
                            
                            $discussion->tags()->syncWithoutDetaching([$newTag->id]);
                        }
                    });
                });
                break;
            }
        }
    }),

   (new Extend\Event)
    ->listen(Deleting::class, function (Deleting $event) {
        $discussion = $event->discussion;
        
        // Отримуємо всі теги дискусії з кольором #6b4226
        $autoTags = $discussion->tags()
            ->where('color', '#8a2be2')
            ->get();
        
        foreach ($autoTags as $tag) {
            // Чекаємо завершення поточної транзакції
            $discussion->afterDelete(function ($deletedDiscussion) use ($tag) {
                // Оновлюємо модель тегу з бази даних
                $tag->refresh();
                
                // Перевіряємо кількість дискусій
                $count = $tag->discussions()->count();
                
                // Якщо дискусій немає - видаляємо тег
                if ($count === 0) {
                    $tag->delete();
                }
            });
        }
    }),

    // ==================== FRONTEND - FORUM ====================
    
    (new Extend\Frontend('forum'))
    
       
        ->content(function (Document $document) {
            $document->foot[] = <<<'JS'
<script>
// ==================== NAVIGATION LINKS ====================
// ==================== NAVIGATION LINKS ====================
(function() {
    // Додаємо стилі
    var style = document.createElement('style');
    style.textContent = '.custom-nav-links{display:flex;gap:1px;margin:8px 0;flex-wrap:nowrap;overflow-x:auto;-webkit-overflow-scrolling:touch;scrollbar-width:none;justify-content:center}.custom-nav-links::-webkit-scrollbar{display:none}.custom-nav-link{display:inline-flex;align-items:center;padding:6px 4px;background:var(--primary-color);color:var(--button-color);text-decoration:none;border-radius:12px;font-size:12px !important;font-weight:700;transition:all 0.2s;cursor:pointer;white-space:nowrap;flex-shrink:0}.custom-nav-link svg{flex-shrink:0;width:16px;height:16px}.custom-nav-link:hover{background:var(--primary-color);color:#fff}.custom-nav-link:hover svg{color:#fff}.custom-nav-link.active{background:var(--primary-color);color:#fff}.custom-nav-link.active svg{color:#fff}@media(min-width:480px){.custom-nav-link{padding:5px 10px;font-size:12px}.custom-nav-link svg{width:18px;height:18px}}@media(min-width:768px){.custom-nav-link{padding:6px 12px;font-size:13px}.custom-nav-link svg{width:20px;height:20px}}';
    document.head.appendChild(style);

    function addNav() {
        var cp = window.location.pathname;
        var paths = ['/t/tlumachennya-kart', '/t/velyki-arkany', '/t/zhezly', '/t/kubky', '/t/mechi', '/t/pentakli'];
        
        // ЯК У ПЕРШОМУ КОДІ: перевіряємо наявність перед додаванням
        if (paths.indexOf(cp) !== -1) {
            var h = document.querySelector('.Hero-title');
            if (h && !document.querySelector('.custom-nav-links')) {
                var c = document.createElement('div');
                c.className = 'custom-nav-links';
                
                var links = [
                    { text: 'Аркани', href: '/t/velyki-arkany', icon: '<svg viewBox="0 0 24 24" fill="none"><polygon points="12,3 22,21 2,21" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>' },
                    { text: 'Жезли', href: '/t/zhezly', icon: '<svg viewBox="0 0 24 24" fill="none"><line x1="12" y1="3" x2="12" y2="21" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/><circle cx="12" cy="4" r="2.5" fill="currentColor"/></svg>' },
                    { text: 'Кубки', href: '/t/kubky', icon: '<svg viewBox="0 0 24 24" fill="none"><path d="M6 8 C6 4 18 4 18 8 C18 13 15 16 12 16 C9 16 6 13 6 8 Z" stroke="currentColor" stroke-width="2"/><line x1="12" y1="16" x2="12" y2="21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="8" y1="21" x2="16" y2="21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><rect x="9" y="12" width="6" height="2" rx="1" fill="currentColor" opacity="0.3"/></svg>' },
                    { text: 'Мечі', href: '/t/mechi', icon: '<svg viewBox="0 0 24 24" fill="none"><line x1="12" y1="2" x2="12" y2="16" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/><line x1="6" y1="14" x2="18" y2="14" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/><polygon points="10,2 12,0 14,2" fill="currentColor"/></svg>' },
                    { text: 'Пентаклі', href: '/t/pentakli', icon: '<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8" stroke="currentColor" stroke-width="2"/><polygon points="12,4 14.5,9.5 20,10 16,14 17,19.5 12,17 7,19.5 8,14 4,10 9.5,9.5" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>' }
                ];
                
                links.forEach(function(link) {
                    var a = document.createElement('a');
                    a.innerHTML = link.icon + '<span>' + link.text + '</span>';
                    a.href = link.href;
                    a.className = 'custom-nav-link';
                    if (cp === link.href) a.classList.add('active');
                    a.onclick = function(e) {
                        e.preventDefault();
                        if (typeof m !== 'undefined' && m.route && typeof m.route.set === 'function') {
                            m.route.set(link.href);
                        } else {
                            window.location.href = link.href;
                        }
                    };
                    c.appendChild(a);
                });
                
                h.parentNode.insertBefore(c, h.nextSibling);
            }
        }
        
        // ЯК У ПЕРШОМУ КОДІ: для сторінки дискусії
        if (cp.indexOf('/d/100') !== -1) {
            var dh = document.querySelector('.DiscussionHero-title');
            if (dh && !document.querySelector('.custom-nav-links')) {
                var c = document.createElement('div');
                c.className = 'custom-nav-links';
                
                var links = [
                    { text: 'Аркани', href: '/t/velyki-arkany', icon: '<svg viewBox="0 0 24 24" fill="none"><polygon points="12,3 22,21 2,21" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>' },
                    { text: 'Жезли', href: '/t/zhezly', icon: '<svg viewBox="0 0 24 24" fill="none"><line x1="12" y1="3" x2="12" y2="21" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/><circle cx="12" cy="4" r="2.5" fill="currentColor"/></svg>' },
                    { text: 'Кубки', href: '/t/kubky', icon: '<svg viewBox="0 0 24 24" fill="none"><path d="M6 8 C6 4 18 4 18 8 C18 13 15 16 12 16 C9 16 6 13 6 8 Z" stroke="currentColor" stroke-width="2"/><line x1="12" y1="16" x2="12" y2="21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="8" y1="21" x2="16" y2="21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><rect x="9" y="12" width="6" height="2" rx="1" fill="currentColor" opacity="0.3"/></svg>' },
                    { text: 'Мечі', href: '/t/mechi', icon: '<svg viewBox="0 0 24 24" fill="none"><line x1="12" y1="2" x2="12" y2="16" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/><line x1="6" y1="14" x2="18" y2="14" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/><polygon points="10,2 12,0 14,2" fill="currentColor"/></svg>' },
                    { text: 'Пентаклі', href: '/t/pentakli', icon: '<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8" stroke="currentColor" stroke-width="2"/><polygon points="12,4 14.5,9.5 20,10 16,14 17,19.5 12,17 7,19.5 8,14 4,10 9.5,9.5" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>' }
                ];
                
                links.forEach(function(link) {
                    var a = document.createElement('a');
                    a.innerHTML = link.icon + '<span>' + link.text + '</span>';
                    a.href = link.href;
                    a.className = 'custom-nav-link';
                    a.onclick = function(e) {
                        e.preventDefault();
                        if (typeof m !== 'undefined' && m.route && typeof m.route.set === 'function') {
                            m.route.set(link.href);
                        } else {
                            window.location.href = link.href;
                        }
                    };
                    c.appendChild(a);
                });
                
                dh.parentNode.insertBefore(c, dh.nextSibling);
            }
        }
    }
    
    // ЯК У ПЕРШОМУ КОДІ: викликаємо одразу
    addNav();
    
    // ЯК У ПЕРШОМУ КОДІ: MutationObserver для відстеження змін
    var observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList') {
                addNav();
            }
        });
    });
    
    observer.observe(document.body, { childList: true, subtree: true });
    
    // ЯК У ПЕРШОМУ КОДІ: обробник для внутрішніх посилань
    document.body.addEventListener('click', function(event) {
        if (event.target.classList.contains('internal-link')) {
            event.preventDefault();
            const href = event.target.getAttribute('href');
            if (typeof m !== 'undefined' && m.route && typeof m.route.set === 'function') {
                m.route.set(href);
            } else {
                window.location.href = href;
            }
        }
    });
})();
</script>
JS;
        })
       
    
    
    
    
    
    
    
    
    
    
        ->content(function (Document $document) {
            $settings = resolve(SettingsRepositoryInterface::class);
            $hideSidebarSubtags = $settings->get('forumtaro-subtags.hide_sidebar', false) ? 'true' : 'false';
            $mobileDrawerTags = $settings->get('forumtaro-subtags.mobile_drawer_tags', true) ? 'true' : 'false';
            
            $customLinksRaw = $settings->get('forumtaro-subtags.custom_links', '[]');
            $customLinks = json_decode($customLinksRaw, true);
            if (!is_array($customLinks)) {
                $customLinks = [];
            }
            
            $cleanLinks = array_values(array_filter(array_map(function($link) {
                if (!is_array($link)) return null;
                
                $url = isset($link['url']) ? $link['url'] : '#';
                if (!filter_var($url, FILTER_VALIDATE_URL) && strpos($url, '/') !== 0) {
                    $url = '#';
                }
                
                $title = isset($link['title']) ? htmlspecialchars(strip_tags(trim($link['title'])), ENT_QUOTES | ENT_HTML5, 'UTF-8') : '';
                $icon = isset($link['icon']) ? preg_replace('/[^a-zA-Z0-9\s\-]/', '', $link['icon']) : '';
                $iconColor = isset($link['iconColor']) ? preg_replace('/[^#a-fA-F0-9]/', '', $link['iconColor']) : '#667c99';
                if (!preg_match('/^#[a-fA-F0-9]{3,6}$/', $iconColor)) {
                    $iconColor = '#667c99';
                }
                
                return [
                    'id' => isset($link['id']) ? (int)$link['id'] : 0,
                    'title' => $title,
                    'url' => $url,
                    'icon' => $icon,
                    'iconColor' => $iconColor,
                    'target' => isset($link['target']) && $link['target'] === '_blank' ? '_blank' : '_self',
                    'enabled' => !isset($link['enabled']) || $link['enabled'] !== false,
                    'showInDrawer' => !isset($link['showInDrawer']) || $link['showInDrawer'] !== false
                ];
            }, $customLinks), function($link) {
                return $link !== null && $link['enabled'] === true;
            }));
            
            $cleanLinksJson = json_encode($cleanLinks, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT);
            
            $document->head[] = '
<script>
(function() {
    // ========== ВИЗНАЧЕННЯ БАЗОВОГО ШЛЯХУ ==========
    function getBasePath() {
        var path = window.location.pathname;
        if (path.indexOf("/public") !== -1) {
            return "/public";
        }
        if (typeof app !== "undefined" && app.forum && app.forum.attribute("baseUrl")) {
            var baseUrl = app.forum.attribute("baseUrl");
            var match = baseUrl.match(/^(?:https?:\/\/[^\/]+)(\/.*)$/);
            if (match && match[1] && match[1] !== "/") {
                return match[1];
            }
        }
        return "";
    }
    
    var basePath = getBasePath();
    var hideSidebarSubtags = ' . $hideSidebarSubtags . ';
    var mobileDrawerTags = ' . $mobileDrawerTags . ';
    var customLinks = ' . $cleanLinksJson . ';
    
    var observer = null;
    var debounceTimer = null;
    var isProcessing = false;
    var lastProcessedUrl = null;
    
    function initSubtags() {
        var waitForApp = setInterval(function() {
            if (typeof app !== "undefined" && app.store && m) {
                clearInterval(waitForApp);
                
                if (hideSidebarSubtags) {
                    applySidebarHiding();
                }
                
                if (mobileDrawerTags) {
                    initMobileDrawerTags();
                }
                
                if (customLinks && customLinks.length > 0) {
                    initCustomLinks();
                }
                
                setupRouteListener();
                startOptimizedObserver();
                scheduleCheck(500);
            }
        }, 50);
        
        setTimeout(function() { clearInterval(waitForApp); }, 10000);
    }
    
    function initCustomLinks() {
        function tryAddCustomLinks() {
            var sidebar = document.querySelector(".IndexPage-nav");
            if (sidebar && !document.querySelector(".custom-links-section")) {
                addCustomLinks(sidebar);
            }
        }
        tryAddCustomLinks();
    }
    
    function addCustomLinks(sidebar) {
        var oldCustomSection = sidebar.querySelector(".custom-links-section");
        if (oldCustomSection) oldCustomSection.remove();
        
        var activeLinks = customLinks.filter(function(link) {
            return link.enabled === true;
        });
        
        if (activeLinks.length === 0) return;
        
        var customSection = document.createElement("div");
        customSection.className = "custom-links-section";
        
        var title = document.createElement("div");
        title.className = "custom-links-title";
        title.textContent = "Корисні посилання";
        customSection.appendChild(title);
        
        var linksList = document.createElement("div");
        linksList.className = "custom-links-list";
        
        activeLinks.forEach(function(link) {
            var linkElement = document.createElement("a");
            var url = link.url;
            if (basePath && url.charAt(0) === "/" && url.indexOf(basePath) !== 0) {
                url = basePath + url;
            }
            linkElement.href = url;
            linkElement.setAttribute("rel", "noopener noreferrer");
            
            if (link.target === "_blank") {
                linkElement.setAttribute("target", "_blank");
            }
            
            if (link.icon) {
                var icon = document.createElement("i");
                icon.className = link.icon;
                if (link.iconColor) {
                    icon.style.color = link.iconColor;
                }
                linkElement.appendChild(icon);
            }
            
            linkElement.appendChild(document.createTextNode(link.title));
            linksList.appendChild(linkElement);
        });
        
        customSection.appendChild(linksList);
        sidebar.appendChild(customSection);
    }
    
    function initMobileDrawerTags() {
        if (!document.querySelector("#mobile-drawer-tags-style")) {
            var style = document.createElement("style");
            style.id = "mobile-drawer-tags-style";
            style.textContent = `
                @media (max-width: 768px) {
                    .App-drawer .tags-section,
                    .App-drawer .custom-links-section-drawer { padding: 16px 0; border-top: 0.5px solid var(--control-bg); margin-top: 16px; }
                    .App-drawer .tags-section-title { font-size: 14px; font-weight: 600; color: #787C7E; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px; padding: 0 16px; }
                    .App-drawer .drawer-tag-item,
                    .App-drawer .drawer-custom-link { display: block !important; padding: 10px 16px !important; margin: 2px 0 !important; color: var(--discussion-title-color) !important; text-decoration: none !important; font-size: 16px !important; cursor: pointer !important; }
                    .App-drawer .drawer-tag-item:hover,
                    .App-drawer .drawer-custom-link:hover { background: var(--muted-more-bg) !important; }
                    .App-drawer .drawer-tag-item i,
                    .App-drawer .drawer-custom-link i { margin-right: 10px; width: 16px; text-align: center; }
                }
                @media (min-width: 768px) { .tags-section, .custom-links-section-drawer { display: none; } }
                @media (max-width: 991px) { .custom-links-section { display: none; } }
            `;
            document.head.appendChild(style);
        }
        addTagsToDrawer();
    }
    
    function addTagsToDrawer() {
        var drawer = document.querySelector(".App-drawer");
        if (!drawer || typeof app === "undefined" || !app.store) return;
        
        var oldSection = drawer.querySelector(".tags-section");
        if (oldSection) oldSection.remove();
        
        var oldCustomSection = drawer.querySelector(".custom-links-section-drawer");
        if (oldCustomSection) oldCustomSection.remove();
        
        try {
            var allTags = app.store.all("tags");
            var primaryTags = [];
            
            allTags.forEach(function(tag) {
                var parent = tag.parent();
                var isPrimary = !parent && tag.position() !== null;
                if (isPrimary) {
                    primaryTags.push({
                        name: tag.name(),
                        slug: tag.slug(),
                        color: tag.color() || "#888",
                        icon: tag.icon() || "fas fa-tag",
                        position: tag.position() || 0
                    });
                }
            });
            
            primaryTags.sort(function(a, b) { return a.position - b.position; });
            
            if (primaryTags.length > 0) {
                var tagsSection = document.createElement("div");
                tagsSection.className = "tags-section";
                
                var title = document.createElement("div");
                title.className = "tags-section-title";
                title.textContent = "Категорії";
                tagsSection.appendChild(title);
                
                primaryTags.forEach(function(tag) {
                    var link = document.createElement("a");
                    link.className = "drawer-tag-item";
                    
                    var icon = document.createElement("i");
                    icon.className = tag.icon;
                    icon.style.color = tag.color;
                    
                    link.appendChild(icon);
                    link.appendChild(document.createTextNode(tag.name));
                    
                    var url = basePath + "/t/" + encodeURIComponent(tag.slug);
                    link.addEventListener("click", function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        drawer.classList.remove("open");
                        document.body.classList.remove("drawerOpen");
                        if (typeof m !== "undefined" && m.route) {
                            m.route.set(url);
                        } else {
                            window.location.href = url;
                        }
                    });
                    tagsSection.appendChild(link);
                });
                drawer.appendChild(tagsSection);
            }
            
            var activeLinks = customLinks.filter(function(link) {
                return link.enabled && link.showInDrawer;
            });
            
            if (activeLinks.length > 0) {
                var customSection = document.createElement("div");
                customSection.className = "custom-links-section-drawer";
                
                var customTitle = document.createElement("div");
                customTitle.className = "tags-section-title";
                customTitle.textContent = "Корисні посилання";
                customSection.appendChild(customTitle);
                
                activeLinks.forEach(function(link) {
                    var linkElement = document.createElement("a");
                    linkElement.className = "drawer-custom-link";
                    
                    if (link.icon) {
                        var icon = document.createElement("i");
                        icon.className = link.icon;
                        if (link.iconColor) {
                            icon.style.color = link.iconColor;
                        }
                        linkElement.appendChild(icon);
                    }
                    
                    linkElement.appendChild(document.createTextNode(link.title));
                    
                    var url = link.url;
                    if (basePath && url.charAt(0) === "/" && url.indexOf(basePath) !== 0) {
                        url = basePath + url;
                    }
                    
                    linkElement.addEventListener("click", function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        drawer.classList.remove("open");
                        document.body.classList.remove("drawerOpen");
                        if (link.target === "_blank") {
                            window.open(url, "_blank", "noopener,noreferrer");
                        } else {
                            window.location.href = url;
                        }
                    });
                    customSection.appendChild(linkElement);
                });
                drawer.appendChild(customSection);
            }
        } catch (e) {
            console.error("Drawer error:", e);
        }
    }
    
    function applySidebarHiding() {
        if (!document.querySelector("#subtags-sidebar-hide")) {
            var style = document.createElement("style");
            style.id = "subtags-sidebar-hide";
            style.textContent = ".IndexPage-nav .TagLinkButton.child { display: none !important; }";
            document.head.appendChild(style);
        }
    }
    
    function setupRouteListener() {
        if (typeof m === "undefined" || !m.route) return;
        
        var originalRouteSet = m.route.set;
        m.route.set = function() {
            removeSubtags();
            isProcessing = false;
            lastProcessedUrl = null;
            var result = originalRouteSet.apply(this, arguments);
            scheduleCheck(300);
            
            // ДОДАЄМО ЦЕ: відновлюємо кастомні посилання після навігації
        setTimeout(function() {
            var sidebar = document.querySelector(".IndexPage-nav");
            if (sidebar && !document.querySelector(".custom-links-section")) {
                addCustomLinks(sidebar);
            }
        }, 200);
        
        return result;
    };
            
       
    }
    
    function startOptimizedObserver() {
        if (observer) observer.disconnect();
        
        observer = new MutationObserver(function(mutations) {
            var hasRelevantChanges = mutations.some(function(mutation) {
                return Array.from(mutation.addedNodes).some(function(node) {
                    return node.nodeType === 1 && (
                        node.classList && (
                            node.classList.contains("TagLinkButton") ||
                            node.classList.contains("IndexPage-nav") ||
                            node.classList.contains("IndexPage-results")
                        ) ||
                        node.querySelector && (
                            node.querySelector(".TagLinkButton") ||
                            node.querySelector(".IndexPage-nav") ||
                            node.querySelector(".IndexPage-results")
                        )
                    );
                });
            });
            
            if (hasRelevantChanges) {
                scheduleCheck(200);
            }
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
    
    function scheduleCheck(delay) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(processSubtags, delay);
    }
    
    function processSubtags() {
        if (isProcessing) return;
        
        var childTags = getChildTagsData();
        
        if (childTags.length === 0) {
            removeSubtags();
            return;
        }
        
        var currentUrl = window.location.pathname;
        var isOnChildTag = childTags.some(function(tag) {
            return currentUrl.includes(tag.href);
        });
        
        if (isOnChildTag) {
            removeSubtags();
            return;
        }
        
        isProcessing = true;
        try {
            renderSubtags(childTags);
        } finally {
            isProcessing = false;
        }
    }
    
    function getChildTagsData() {
        var sidebar = document.querySelector(".IndexPage-nav");
        if (!sidebar) return [];
        
        var childTags = sidebar.querySelectorAll(".TagLinkButton.child");
        var tagsData = [];
        
        for (var i = 0; i < Math.min(childTags.length, 30); i++) {
            var tag = childTags[i];
            var href = tag.getAttribute("href");
            var name = tag.querySelector(".Button-label");
            if (name && href) {
                tagsData.push({
                    name: name.textContent.trim(),
                    href: href,
                    style: tag.getAttribute("style") || ""
                });
            }
        }
        return tagsData;
    }
    
    function renderSubtags(childTagsData) {
        if (typeof m === "undefined") return;
        
        try {
            removeSubtags();
            
            var container = document.querySelector(".IndexPage-results") ||
                             document.querySelector(".DiscussionList") ||
                             document.querySelector(".IndexPage-toolbar");
            
            if (!container) return;
            
            var subtagsDiv = document.createElement("div");
            subtagsDiv.className = "subtags-display";
            
            var buttonsArray = childTagsData.map(function(tag) {
                var href = tag.href;
                if (basePath && href.indexOf("/t/") === 0) {
                    href = basePath + href;
                }
                
                return m("a.subtag-item", {
                    href: href,
                    style: tag.style,
                    onclick: function(e) {
                        e.preventDefault();
                        removeSubtags();
                        if (m.route) {
                            m.route.set(href);
                        }
                    }
                }, m("span.subtag-label", tag.name));
            });
            
            m.render(subtagsDiv, 
                m("div.subtags-container", [
                    m("div.subtags-wrapper", [
                        m("span.subtags-title", "📂"),
                        ...buttonsArray
                    ])
                ])
            );
            
            if (container.classList.contains("IndexPage-toolbar")) {
                container.parentNode.insertBefore(subtagsDiv, container.nextSibling);
            } else {
                container.insertBefore(subtagsDiv, container.firstChild);
            }
        } catch (e) {
            console.error("Render error:", e);
        }
    }
    
    function removeSubtags() {
        var oldBlock = document.querySelector(".subtags-display");
        if (oldBlock) oldBlock.remove();
    }
    
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initSubtags);
    } else {
        initSubtags();
    }
})();
</script>
<style>
.subtags-container { padding: 16px 0; margin-bottom: 16px; border-bottom: 1px solid var(--control-bg); }
.subtags-wrapper { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.subtags-title { font-size: 14px; font-weight: 500; color: var(--muted-color); margin-right: 8px; }
.subtag-item { text-decoration: none; transition: transform 0.2s ease; display: inline-block; }
.subtag-item:hover { transform: translateY(-2px); }
.subtag-label { padding: 6px 12px; border-radius: 16px; background-color: var(--body-bg-faded); color: var(--tag-color); font-size: 14px; font-weight: 500; box-shadow: 0px 0px 1px 1px var(--button-toggled-bg); transition: all 0.2s ease; display: inline-block; white-space: nowrap; }
.subtag-item:hover .subtag-label { background-color: var(--tag-bg); box-shadow: 0px 2px 6px rgba(0, 0, 0, 0.2); transform: scale(1.05); }
.subtags-display { animation: subtagsFadeIn 0.3s ease-in; }
@keyframes subtagsFadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.custom-links-section { margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--control-bg); }
.custom-links-title { font-size: 14px; font-weight: 600; color: #787C7E; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px; padding: 0 8px; }
.custom-link-item { display: block; padding: 8px; margin: 2px 0; color: var(--discussion-title-color); text-decoration: none; font-size: 14px; border-radius: 4px; transition: background-color 0.15s ease; }
.custom-link-item:hover { background-color: var(--muted-more-bg); }
.custom-link-item i { margin-right: 10px; width: 16px; text-align: center; }


/* Основний контейнер */
.custom-links-section {
    margin-top: 20px;
    padding: 16px 0;
    border-top: 1px solid var(--control-bg);
}

/* Заголовок */
.custom-links-title {
    font-size: 13px;
    font-weight: 700;
    color: #7a8599;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    margin-bottom: 8px;
    padding: 0 8px;
}

/* Список посилань */
.custom-links-list {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

/* Кожне посилання як пункт меню */
.custom-links-list a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    color: var(--discussion-title-color);
    text-decoration: none;
    font-size: 14px;
    font-weight: 300;
    border-radius: 8px;
    transition: all 0.15s ease;
    position: relative;
}

/* Іконка */
.custom-links-list a i {
    width: 20px;
    text-align: center;
    font-size: 15px;
    flex-shrink: 0;
}

/* Ховер */
.custom-links-list a:hover {
    background-color: var(--muted-more-bg);
    color: var(--link-color);
}

/* Активне посилання (поточний шлях) */
.custom-links-list a.active {
    background-color: var(--button-toggled-bg);
    color: var(--button-toggled-color);
    font-weight: 600;
}

/* Індикатор активного пункту */
.custom-links-list a.active::before {
   
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 3px;
    height: 60%;
    background: var(--primary-color);
    border-radius: 0 3px 3px 0;
}

.App-drawer {
        overflow: auto;}

</style>';
        })
        ->content(function (Document $document) {
            $document->foot[] = '
<script>
(function() {
    function getBasePath() {
        var path = window.location.pathname;
        if (path.indexOf("/public") !== -1) {
            return "/public";
        }
        if (typeof app !== "undefined" && app.forum && app.forum.attribute("baseUrl")) {
            var baseUrl = app.forum.attribute("baseUrl");
            var match = baseUrl.match(/^(?:https?:\/\/[^\/]+)(\/.*)$/);
            if (match && match[1] && match[1] !== "/") {
                return match[1];
            }
        }
        return "";
    }
    
    var basePath = getBasePath();
    var currentSlug = null;
    var isLoaded = false;
    
    function getDiscussionId() {
        var m = window.location.pathname.match(/\/d\/(\d+)/);
        return m ? m[1] : null;
    }
    
    function getAutoTagSlug() {
        var tags = document.querySelectorAll(".TagLabel");
        for (var i = 0; i < tags.length; i++) {
            var el = tags[i];
            var style = el.getAttribute("style") || "";
            if (style.indexOf("8a2be2") === -1) continue;
            var link = el.closest("a");
            if (link && link.href) {
                var m = link.href.match(/\/t\/([^\/\?#]+)/);
                if (m) return m[1];
            }
        }
        return null;
    }
    
    function createMessage(text, color) {
        var div = document.createElement("div");
        div.style.cssText = "text-align:center;color:" + color + ";padding:15px;";
        div.textContent = text;
        return div;
    }
    
    function renderDiscussions(postBody, discussions) {
        var block = document.querySelector(".rss-discussions-block");
        if (!block) {
            block = document.createElement("div");
            block.className = "rss-discussions-block";
            block.style.cssText = "margin-top:15px;padding:15px;background:var(--code-bg);border-radius:8px;border:1px solid #e0d5c7;";
        }
        
        while (block.firstChild) block.removeChild(block.firstChild);
        
        if (!discussions || !discussions.length) {
            block.appendChild(createMessage("Немає обговорень", "#8a2be2"));
            if (!block.parentNode && postBody) postBody.appendChild(block);
            return;
        }
        
        var header = document.createElement("h4");
        header.style.cssText = "margin:0 0 10px;color:var(--discussion-title-color);";
        header.textContent = "Сторінки про цю колоду:";
        block.appendChild(header);
        
        var list = document.createElement("ul");
        list.style.cssText = "list-style:none;padding:0;margin:0;";
        
        discussions.forEach(function(discussion) {
            var title = discussion.attributes.title;
            var slug = discussion.attributes.slug;
            var url = basePath + "/d/" + encodeURIComponent(slug);
            
            var li = document.createElement("li");
            li.style.margin = "5px 0";
            
            var link = document.createElement("a");
            link.href = url;
            link.style.cssText = "color:#8a2be2;text-decoration:none;";
            link.textContent = title;
            link.addEventListener("click", function(e) {
                e.preventDefault();
                if (window.m && m.route) {
                    m.route.set(url);
                } else {
                    window.location.href = url;
                }
            });
            
            li.appendChild(link);
            list.appendChild(li);
        });
        
        block.appendChild(list);
        if (!block.parentNode && postBody) postBody.appendChild(block);
    }
    
    function loadDiscussions() {
        var postBody = document.querySelector(".Post-body");
        if (!postBody) {
            setTimeout(loadDiscussions, 1000);
            return;
        }
        
        var slug = getAutoTagSlug();
        if (!slug) return;
        if (slug === currentSlug && isLoaded) return;
        
        currentSlug = slug;
        isLoaded = false;
        
        var block = document.querySelector(".rss-discussions-block");
        if (!block) {
            block = document.createElement("div");
            block.className = "rss-discussions-block";
            block.style.cssText = "margin-top:15px;padding:15px;background:var(--code-bg);border-radius:8px;border:1px solid #e0d5c7;";
            postBody.appendChild(block);
        }
        
        while (block.firstChild) block.removeChild(block.firstChild);
        block.appendChild(createMessage("📴 Завантаження...", "#8a2be2"));
        
        fetch(basePath + "/api/discussions?filter[tag]=" + encodeURIComponent(slug) + "&sort=createdAt&page[limit]=50", {
            headers: { "Accept": "application/json" }
        })
            .then(function(r) { return r.ok ? r.json() : Promise.reject(); })
            .then(function(data) {
                var discussions = data.data || [];
                renderDiscussions(postBody, discussions);
                isLoaded = true;
            })
            .catch(function() {
                var block = document.querySelector(".rss-discussions-block");
                if (block) {
                    while (block.firstChild) block.removeChild(block.firstChild);
                    block.appendChild(createMessage("Помилка завантаження", "#8a2be2"));
                }
            });
    }
    
    function init() {
        if (!window.location.pathname.match(/\/d\/\d+/)) return;
        setTimeout(loadDiscussions, 1500);
    }
    
    if (typeof m !== "undefined" && m.route) {
        var originalRouteSet = m.route.set;
        m.route.set = function() {
            var block = document.querySelector(".rss-discussions-block");
            if (block) block.remove();
            currentSlug = null;
            isLoaded = false;
            var result = originalRouteSet.apply(this, arguments);
            setTimeout(init, 1500);
            return result;
        };
    }
    
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
    
    
})();
</script>';
        }),

    // ==================== FRONTEND - ADMIN ====================

(new Extend\Frontend('admin'))
    ->content(function (Document $document) {
        $document->head[] = <<<'SCRIPT'
<script>
window.addEventListener("load", function() {
    setTimeout(function() {
        if (window.app && window.app.extensionData) {
            try {
                const extension = app.extensionData.for("forumtaro-subtags");
                
                // Прості налаштування
                extension
                    .registerSetting({
                        setting: "forumtaro-subtags.hide_sidebar",
                        type: "boolean",
                        label: "Ховати дочірні теги в сайдбарі",
                        help: "Приховає дочірні теги на головній сторінці"
                    })
                    .registerSetting({
                        setting: "forumtaro-subtags.mobile_drawer_tags",
                        type: "boolean",
                        label: "Показувати теги в мобільному меню",
                        help: "Відображає список категорій в мобільному меню"
                    });
                
                // РЕДАКТОР ПОСИЛАНЬ
                extension.registerSetting(function() {
                    return m("div", {className: "Form-group"}, [
                        m("label", "Кастомні посилання"),
                        m("div", {className: "helpText"}, 
                            "Додайте посилання які будуть відображатися в бічній панелі та мобільному меню"
                        ),
                        m("div", {id: "custom-links-editor", style: "margin-top: 15px;"}, [
                            m("button", {
                                className: "Button Button--primary",
                                onclick: function() {
                                    const currentLinks = JSON.parse(app.data.settings["forumtaro-subtags.custom_links"] || "[]");
                                    const newLink = {
                                        id: Date.now(),
                                        title: "",
                                        url: "/",
                                        icon: "fas fa-link",
                                        iconColor: "#667c99",
                                        target: "_self",
                                        enabled: true,
                                        showInDrawer: true
                                    };
                                    currentLinks.push(newLink);
                                    renderLinksEditor(currentLinks);
                                }
                            }, "➕ Додати посилання"),
                            m("div", {id: "custom-links-container", style: "margin-top: 15px;"})
                        ])
                    ]);
                });
                
                // ========== БЕЗПЕЧНІ ФУНКЦІЇ ДЛЯ РОБОТИ З ПОСИЛАННЯМИ ==========
                
                // Санітизація даних
                function sanitizeLink(link) {
                    // Заголовок
                    let title = (link.title || "").toString();
                    title = title.replace(/<[^>]*>/g, "").trim();
                    title = title.substring(0, 200);
                    
                    // URL
                    let url = (link.url || "#").toString();
                    url = url.replace(/<[^>]*>/g, "").trim();
                    if (!/^https?:\/\//i.test(url) && url.charAt(0) !== "/" && url !== "#") {
                        url = "/" + url;
                    }
                    url = url.substring(0, 500);
                    
                    // Іконка (тільки безпечні символи)
                    let icon = (link.icon || "").toString();
                    icon = icon.replace(/[^a-zA-Z0-9\s\-]/g, "").trim();
                    icon = icon.substring(0, 50);
                    
                    // Колір (тільки hex)
                    let iconColor = (link.iconColor || "#667c99").toString();
                    iconColor = iconColor.replace(/[^#a-fA-F0-9]/g, "");
                    if (!/^#[a-fA-F0-9]{3,6}$/.test(iconColor)) {
                        iconColor = "#667c99";
                    }
                    
                    return {
                        id: parseInt(link.id) || Date.now(),
                        title: title || "Нове посилання",
                        url: url,
                        icon: icon,
                        iconColor: iconColor,
                        target: link.target === "_blank" ? "_blank" : "_self",
                        enabled: link.enabled !== false,
                        showInDrawer: link.showInDrawer !== false
                    };
                }
                
                // Збереження з debounce
                let saveTimer;
                function saveLinks(links) {
                    clearTimeout(saveTimer);
                    saveTimer = setTimeout(function() {
                        const cleanLinks = links.map(sanitizeLink);
                        const data = {
                            "forumtaro-subtags.custom_links": JSON.stringify(cleanLinks)
                        };
                        
                        // CSRF захист
                        const csrfToken = document.querySelector('meta[name="csrf-token"]');
                        const headers = { 'Content-Type': 'application/json' };
                        if (csrfToken) {
                            headers['X-CSRF-TOKEN'] = csrfToken.getAttribute('content');
                        }
                        
                        app.request({
                            method: "POST",
                            url: app.forum.attribute("apiUrl") + "/settings",
                            body: data,
                            headers: headers,
                            errorHandler: function(err) {
                                console.error("Помилка збереження:", err);
                                app.alerts.show({type: "error"}, "Помилка збереження налаштувань");
                            }
                        });
                    }, 500);
                }
                
                // Перевірка URL для підсвічування помилок
                function isValidUrl(url) {
                    if (url === "#") return true;
                    if (url.startsWith("/")) return true;
                    try {
                        const parsed = new URL(url);
                        return parsed.protocol === "http:" || parsed.protocol === "https:";
                    } catch(e) {
                        return false;
                    }
                }
                
                // Попередній перегляд іконки
                function renderIconPreview(icon, color) {
                    if (!icon) return null;
                    return m("i", {
                        className: icon,
                        style: "color: " + color + "; width: 24px; text-align: center; font-size: 18px;"
                    });
                }
                
                // ГОЛОВНА ФУНКЦІЯ РЕНДЕРИНГУ
                function renderLinksEditor(links) {
                    const container = document.getElementById("custom-links-container");
                    if (!container) return;
                    
                    const save = () => saveLinks(links);
                    
                    const linkItems = links.map(function(link, index) {
                        return m("div", {
                            key: link.id,
                            className: "custom-link-editor",
                            style: "border: 1px solid var(--control-bg); padding: 15px; margin-bottom: 15px; border-radius: 8px; background: var(--body-bg);"
                        }, [
                            // Заголовок з іконкою
                            m("div", {style: "display: flex; align-items: center; margin-bottom: 15px; gap: 10px;"}, [
                                renderIconPreview(link.icon, link.iconColor),
                                m("strong", {style: "flex: 1;"}, link.title || "Нове посилання"),
                                m("span", {
                                    style: "padding: 2px 8px; border-radius: 4px; font-size: 12px; background: " + (link.enabled ? "#4caf50" : "#f44336") + "; color: white;"
                                }, link.enabled ? "Активне" : "Вимкнено")
                            ]),
                            
                            // Поля вводу (2 колонки)
                            m("div", {style: "display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;"}, [
                                // Назва
                                m("div", [
                                    m("label", {style: "display: block; margin-bottom: 5px; font-weight: 600;"}, "📝 Назва"),
                                    m("input", {
                                        className: "FormControl",
                                        type: "text",
                                        value: link.title,
                                        placeholder: "Назва посилання",
                                        maxlength: 200,
                                        oninput: function(e) { 
                                            link.title = e.target.value; 
                                            save();
                                        }
                                    })
                                ]),
                                
                                // URL
                                m("div", [
                                    m("label", {style: "display: block; margin-bottom: 5px; font-weight: 600;"}, "🔗 URL"),
                                    m("input", {
                                        className: "FormControl",
                                        type: "text",
                                        value: link.url,
                                        placeholder: "/шлях або https://...",
                                        maxlength: 500,
                                        oninput: function(e) { 
                                            link.url = e.target.value; 
                                            save();
                                        },
                                        style: !isValidUrl(link.url) ? "border-color: #d50000;" : ""
                                    })
                                ]),
                                
                                // Іконка
                                m("div", [
                                    m("label", {style: "display: block; margin-bottom: 5px; font-weight: 600;"}, "🎨 Іконка"),
                                    m("input", {
                                        className: "FormControl",
                                        type: "text",
                                        value: link.icon,
                                        placeholder: "fas fa-link",
                                        maxlength: 50,
                                        oninput: function(e) { 
                                            link.icon = e.target.value; 
                                            save();
                                        }
                                    })
                                ]),
                                
                                // Колір
                                m("div", [
                                    m("label", {style: "display: block; margin-bottom: 5px; font-weight: 600;"}, "🎨 Колір"),
                                    m("input", {
                                        className: "FormControl",
                                        type: "color",
                                        value: link.iconColor,
                                        oninput: function(e) { 
                                            link.iconColor = e.target.value; 
                                            save();
                                        }
                                    })
                                ]),
                                
                                // Відкривати в
                                m("div", [
                                    m("label", {style: "display: block; margin-bottom: 5px; font-weight: 600;"}, "🪟 Відкривати в"),
                                    m("select", {
                                        className: "FormControl",
                                        value: link.target,
                                        onchange: function(e) { 
                                            link.target = e.target.value; 
                                            save();
                                        }
                                    }, [
                                        m("option", {value: "_self"}, "Поточному вікні"),
                                        m("option", {value: "_blank"}, "Новому вікні")
                                    ])
                                ])
                            ]),
                            
                            // Чекбокси та кнопка видалення
                            m("div", {style: "display: flex; align-items: center; gap: 15px; margin-top: 10px; flex-wrap: wrap;"}, [
                                m("label", {className: "checkbox", style: "display: flex; align-items: center; gap: 5px;"}, [
                                    m("input", {
                                        type: "checkbox",
                                        checked: link.enabled,
                                        onchange: function(e) { 
                                            link.enabled = e.target.checked; 
                                            save();
                                        }
                                    }),
                                    " ✅ Активне"
                                ]),
                                m("label", {className: "checkbox", style: "display: flex; align-items: center; gap: 5px;"}, [
                                    m("input", {
                                        type: "checkbox",
                                        checked: link.showInDrawer,
                                        onchange: function(e) { 
                                            link.showInDrawer = e.target.checked; 
                                            save();
                                        }
                                    }),
                                    " 📱 Показувати в мобільному меню"
                                ]),
                                m("div", {style: "flex: 1;"}),
                                m("button", {
                                    className: "Button Button--danger",
                                    onclick: function() {
                                        if (confirm(`Видалити посилання "${link.title || 'без назви'}"?`)) {
                                            links.splice(index, 1);
                                            renderLinksEditor(links);
                                            save();
                                        }
                                    }
                                }, "🗑️ Видалити")
                            ])
                        ]);
                    });
                    
                    // Додаємо повідомлення якщо немає посилань
                    if (linkItems.length === 0) {
                        m.render(container, [
                            m("div", {
                                style: "text-align: center; padding: 30px; color: var(--muted-color);"
                            }, "📭 Немає доданих посилань. Натисніть кнопку «Додати посилання» вище.")
                        ]);
                    } else {
                        m.render(container, linkItems);
                    }
                }
                
                // Завантаження збережених посилань
                const savedLinks = JSON.parse(app.data.settings["forumtaro-subtags.custom_links"] || "[]");
                
                // Чекаємо поки DOM готовий
                setTimeout(function() {
                    if (document.getElementById("custom-links-container")) {
                        renderLinksEditor(savedLinks);
                    }
                }, 500);
                
                // Оновлюємо UI
                setTimeout(function() {
                    if (window.m && window.m.redraw) {
                        window.m.redraw();
                    }
                }, 100);
                
            } catch (error) {
                console.error("Помилка адмінки:", error);
            }
        }
    }, 500);
});
</script>

<style>
.custom-link-editor input,
.custom-link-editor select {
    transition: all 0.2s ease;
}

.custom-link-editor input:focus,
.custom-link-editor select:focus {
    border-color: #8a2be2;
    box-shadow: 0 0 0 2px rgba(138, 43, 226, 0.2);
}

.custom-link-editor .Button--danger {
    background-color: #f44336;
    color: white;
}

.custom-link-editor .Button--danger:hover {
    background-color: #d32f2f;
}

.checkbox {
    cursor: pointer;
    user-select: none;
}

.checkbox:hover {
    opacity: 0.8;
}
</style>
SCRIPT;
    }),

    // ==================== SETTINGS & LOCALES ====================
    
    (new Extend\Locales(__DIR__.'/locale')),
    
    (new Extend\Settings())
        ->default('forumtaro-subtags.hide_sidebar', false)
        ->default('forumtaro-subtags.mobile_drawer_tags', true)
        ->default('forumtaro-subtags.custom_links', '[]')
];
