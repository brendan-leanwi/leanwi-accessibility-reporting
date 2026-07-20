(function () {
  function normalizeText(value) {
    return String(value || "").replace(/\s+/g, " ").trim().toLowerCase();
  }

  function extractUrlish(value) {
    const raw = String(value || "").trim();
    if (!raw) {
      return "";
    }

    const absolute = raw.match(/https?:\/\/[^\s\])"'<>]+/i);
    if (absolute) {
      return absolute[0];
    }

    const protocolRelative = raw.match(/\/\/[^\s\])"'<>]+/i);
    if (protocolRelative) {
      return protocolRelative[0];
    }

    return raw;
  }

  function normalizeUrl(value) {
    const raw = extractUrlish(value);
    try {
      return new URL(raw, window.location.href);
    } catch (error) {
      return null;
    }
  }

  function sameUrlish(left, right) {
    const leftUrl = normalizeUrl(left);
    const rightUrl = normalizeUrl(right);
    if (!leftUrl || !rightUrl) {
      return false;
    }
    if (leftUrl.href === rightUrl.href) {
      return true;
    }
    const leftPath = leftUrl.pathname.replace(/\/+$/, "");
    const rightPath = rightUrl.pathname.replace(/\/+$/, "");
    if (leftUrl.hostname === rightUrl.hostname && leftPath === rightPath) {
      return true;
    }
    const leftFile = leftPath.split("/").pop();
    const rightFile = rightPath.split("/").pop();
    return Boolean(leftFile && rightFile && leftFile === rightFile);
  }

  function uniqueElements(elements) {
    return Array.from(new Set(elements.filter(Boolean)));
  }

  function contentRoots() {
    const selectors = [
      ".entry-content",
      ".wp-block-post-content",
      ".et_pb_post_content",
      ".et_pb_text_inner",
      ".et_pb_code_inner",
      ".post-content",
      ".page-content",
      "main article",
      "article",
      "main",
      "#content",
      ".site-content",
      "body",
    ];
    const roots = [];
    selectors.forEach((selector) => {
      document.querySelectorAll(selector).forEach((node) => roots.push(node));
    });
    return uniqueElements(roots);
  }

  function candidatesFor(locator) {
    const tag = String(locator.tag || "").toLowerCase();
    if (!tag) {
      return [];
    }

    const roots = contentRoots();
    const candidates = [];
    const attrs = locator.attrs || {};

    if (attrs.id) {
      candidates.push(document.getElementById(attrs.id));
    }

    roots.forEach((root) => {
      if (root.matches && root.matches(tag)) {
        candidates.push(root);
      }
      root.querySelectorAll(tag).forEach((node) => candidates.push(node));
    });

    return uniqueElements(candidates);
  }

  function classList(value) {
    return String(value || "").split(/\s+/).filter(Boolean);
  }

  function classOverlapScore(elementClass, locatorClass, weight) {
    const elementClasses = new Set(classList(elementClass));
    return classList(locatorClass).reduce((score, className) => score + (elementClasses.has(className) ? weight : 0), 0);
  }

  function elementAccessibleText(element) {
    const pieces = [
      element.getAttribute("aria-label"),
      element.getAttribute("title"),
      element.textContent,
    ];

    element.querySelectorAll("img[alt]").forEach((image) => {
      pieces.push(image.getAttribute("alt"));
    });

    return normalizeText(pieces.filter(Boolean).join(" "));
  }

  function isVisible(element) {
    if (!element || !element.isConnected) {
      return false;
    }
    const style = window.getComputedStyle(element);
    if (style.display === "none" || style.visibility === "hidden" || style.opacity === "0") {
      return false;
    }
    if (element.hidden) {
      return false;
    }
    return element.getClientRects().length > 0;
  }

  function contextScore(element, context) {
    if (!Array.isArray(context) || !context.length) {
      return 0;
    }

    let score = 0;
    let ancestor = element.parentElement;
    const ancestors = [];
    while (ancestor && ancestor !== document.body && ancestors.length < 12) {
      ancestors.push(ancestor);
      ancestor = ancestor.parentElement;
    }

    context.forEach((item, depth) => {
      ancestors.forEach((candidate) => {
        if (item.id && candidate.id === item.id) {
          score += Math.max(12, 30 - depth * 3);
        }
        if (item.class) {
          score += classOverlapScore(candidate.getAttribute("class"), item.class, 3);
        }
        if (item.role && candidate.getAttribute("role") === item.role) {
          score += 4;
        }
        if (item.tag && candidate.tagName && candidate.tagName.toLowerCase() === item.tag) {
          score += 1;
        }
      });
    });

    return score;
  }

  function scoreCandidate(element, locator, index) {
    const attrs = locator.attrs || {};
    let score = 0;

    if (attrs.id && element.id === attrs.id) {
      score += 120;
    }

    if (attrs.class) {
      score += classOverlapScore(element.getAttribute("class"), attrs.class, 6);
    }

    ["href", "src", "data-src", "data-lazy-src"].forEach((attr) => {
      if (attrs[attr] && element.getAttribute(attr) && sameUrlish(element.getAttribute(attr), attrs[attr])) {
        score += 55;
      }
    });

    ["alt", "name", "type", "title", "aria-label"].forEach((attr) => {
      if (attrs[attr] && normalizeText(element.getAttribute(attr)) === normalizeText(attrs[attr])) {
        score += 30;
      }
    });

    if (attrs.child_alt && elementAccessibleText(element).includes(normalizeText(attrs.child_alt))) {
      score += 24;
    }

    if (attrs.style && normalizeText(element.getAttribute("style")) === normalizeText(attrs.style)) {
      score += 10;
    }

    const locatorText = normalizeText(locator.text);
    const elementText = elementAccessibleText(element);
    if (locatorText && elementText) {
      if (elementText === locatorText) {
        score += 50;
      } else if (elementText.includes(locatorText) || locatorText.includes(elementText)) {
        score += 30;
      }
    }

    score += contextScore(element, locator.context);

    const hasLocatorSignal = score > 0;
    if (hasLocatorSignal && Number(locator.index) === index) {
      score += 8;
    }

    if (isVisible(element)) {
      score += 4;
    }

    return score;
  }

  function findTarget(locator) {
    const candidates = candidatesFor(locator);
    if (!candidates.length) {
      return null;
    }

    let best = null;
    let bestScore = -1;
    candidates.forEach((candidate, index) => {
      const score = scoreCandidate(candidate, locator, index);
      if (score > bestScore) {
        best = candidate;
        bestScore = score;
      }
    });

    if (bestScore > 0) {
      return best;
    }

    const fallbackIndex = Number(locator.index || 0);
    return candidates[fallbackIndex] || candidates[0];
  }

  function clickPanelControl(panel) {
    if (!panel) {
      return false;
    }

    let control = null;
    if (panel.id) {
      control = Array.from(document.querySelectorAll("[aria-controls], a[href^='#']")).find((node) => {
        return node.getAttribute("aria-controls") === panel.id || node.getAttribute("href") === "#" + panel.id;
      });
    }

    if (!control) {
      const tabClass = Array.from(panel.classList || []).find((className) => /^et_pb_tab_\d+$/.test(className));
      const tabs = panel.closest(".et_pb_tabs");
      if (tabClass && tabs) {
        control = tabs.querySelector(".et_pb_tabs_controls ." + tabClass + " a, .et_pb_tabs_controls ." + tabClass + " [role='tab']");
      }
    }

    if (control) {
      control.click();
      return true;
    }

    return false;
  }

  function forceVisiblePanel(panel) {
    if (!panel) {
      return;
    }

    panel.hidden = false;
    if (panel.style && panel.style.display === "none") {
      panel.style.display = "block";
    }
    panel.classList.add("et_pb_active_content", "et-pb-active-slide", "et_pb_toggle_open");
  }

  function revealContext(target) {
    target.closest("details:not([open])")?.setAttribute("open", "open");

    const panel = target.closest("[role='tabpanel'], .et_pb_tab, .et_pb_toggle_content");
    if (panel) {
      clickPanelControl(panel);
      window.setTimeout(() => {
        if (!isVisible(target)) {
          forceVisiblePanel(panel);
        }
      }, 120);
    }
  }

  function makeFocusable(element) {
    const naturallyFocusable = /^(a|button|input|select|textarea|iframe)$/i.test(element.tagName);
    if (!naturallyFocusable && !element.hasAttribute("tabindex")) {
      element.setAttribute("tabindex", "-1");
      element.setAttribute("data-leanwi-added-tabindex", "true");
    }
  }

  function showNotice(found) {
    const notice = document.createElement("div");
    notice.className = found ? "leanwi-acr-highlight-notice" : "leanwi-acr-highlight-notice leanwi-acr-highlight-notice-missing";
    notice.setAttribute("role", "status");
    notice.textContent = found
      ? "Focused Content Report: highlighted the selected issue."
      : "Focused Content Report: the exact element could not be found on this page.";
    document.body.appendChild(notice);
    window.setTimeout(() => notice.remove(), 8000);
  }

  document.addEventListener("DOMContentLoaded", function () {
    const locator = window.leanwiFocusedHighlight && window.leanwiFocusedHighlight.locator;
    if (!locator || !locator.tag) {
      return;
    }

    const target = findTarget(locator);
    if (!target) {
      showNotice(false);
      return;
    }

    revealContext(target);

    window.setTimeout(() => {
      target.classList.add("leanwi-acr-highlight-target");
      makeFocusable(target);
      target.scrollIntoView({ behavior: "smooth", block: "center", inline: "nearest" });

      window.setTimeout(() => {
        try {
          target.focus({ preventScroll: true });
        } catch (error) {
          target.focus();
        }
        showNotice(true);
      }, 450);
    }, 300);
  });
})();
