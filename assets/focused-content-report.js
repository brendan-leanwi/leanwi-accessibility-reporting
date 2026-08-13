(function () {
  function countWords(text) {
    const matches = String(text || "").match(/[A-Za-z0-9][A-Za-z0-9'-]*/g);
    return matches ? matches.length : 0;
  }

  function shorten(text, limit) {
    const value = String(text || "").replace(/\s+/g, " ").trim();
    return value.length <= limit ? value : value.slice(0, limit - 1).trim() + "...";
  }

  function sourceFromSrcset(srcset) {
    const value = String(srcset || "").trim();
    if (!value) {
      return "";
    }

    let bestUrl = "";
    let bestSize = -1;
    value.split(",").forEach((candidate) => {
      const parts = candidate.trim().split(/\s+/);
      const url = parts[0] || "";
      const descriptor = parts[1] || "";
      let size = 0;
      const match = descriptor.match(/^(\d+(?:\.\d+)?)(w|x)$/i);
      if (match) {
        size = Number(match[1]);
        if (match[2].toLowerCase() === "x") {
          size *= 1000;
        }
      }
      if (url && size >= bestSize) {
        bestUrl = url;
        bestSize = size;
      }
    });

    return bestUrl;
  }

  function realUrl(value) {
    const raw = String(value || "").trim();
    if (!raw || /^data:/i.test(raw) || /^blob:/i.test(raw)) {
      return "";
    }
    return raw;
  }

  function absoluteUrl(value, baseUrl) {
    const raw = realUrl(value);
    if (!raw) {
      return "";
    }

    try {
      return new URL(raw, baseUrl || window.location.href).href;
    } catch (error) {
      return "";
    }
  }

  function imageSource(image, baseUrl) {
    const directAttributes = ["data-src", "data-lazy-src", "data-original", "data-orig-file"];
    for (const attr of directAttributes) {
      const url = absoluteUrl(image.getAttribute(attr), baseUrl);
      if (url) {
        return url;
      }
    }

    const srcsetAttributes = ["data-srcset", "srcset"];
    for (const attr of srcsetAttributes) {
      const url = absoluteUrl(sourceFromSrcset(image.getAttribute(attr)), baseUrl);
      if (url) {
        return url;
      }
    }

    return absoluteUrl(image.getAttribute("src"), baseUrl);
  }

  function isOcrCandidateUrl(url) {
    try {
      return /\.(?:jpe?g|png|gif|webp|bmp|tiff?)$/i.test(new URL(url, window.location.href).pathname);
    } catch (error) {
      return false;
    }
  }

  function imageKey(url) {
    try {
      const parsed = new URL(url, window.location.href);
      return `${parsed.hostname}${parsed.pathname}`.toLowerCase();
    } catch (error) {
      return String(url || "").toLowerCase();
    }
  }

  function encodeLocator(locator) {
    const json = JSON.stringify(locator);
    const bytes = new TextEncoder().encode(json);
    let binary = "";
    bytes.forEach((byte) => {
      binary += String.fromCharCode(byte);
    });
    return btoa(binary).replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/, "");
  }

  function highlightUrl(group, locator) {
    if (!group.permalink || !group.postId || !group.highlightNonce) {
      return "";
    }

    try {
      const url = new URL(group.permalink, window.location.href);
      url.searchParams.set("leanwi_acr_highlight", "1");
      url.searchParams.set("leanwi_acr_post", group.postId);
      url.searchParams.set("leanwi_acr_locator", encodeLocator(locator));
      url.searchParams.set("leanwi_acr_nonce", group.highlightNonce);
      return url.href;
    } catch (error) {
      return "";
    }
  }

  function contentRoots(documentNode) {
    const selectors = [
      ".entry-content",
      ".wp-block-post-content",
      ".et_builder_inner_content",
      ".et_pb_post_content",
      "main article",
      "article",
      "main",
    ];
    const roots = [];
    selectors.forEach((selector) => {
      documentNode.querySelectorAll(selector).forEach((node) => roots.push(node));
    });
    return Array.from(new Set(roots.length ? roots : [documentNode.body].filter(Boolean)));
  }

  function extractImagesFromPageHtml(html, group) {
    const parser = new DOMParser();
    const documentNode = parser.parseFromString(html, "text/html");
    const images = [];
    const seen = new Set();

    contentRoots(documentNode).forEach((root) => {
      root.querySelectorAll("img").forEach((image, index) => {
        const src = imageSource(image, group.permalink);
        if (!src || !isOcrCandidateUrl(src)) {
          return;
        }

        const key = imageKey(src);
        if (seen.has(key)) {
          return;
        }
        seen.add(key);

        const alt = image.getAttribute("alt") || "";
        const attrs = { src };
        ["alt", "class", "title"].forEach((attr) => {
          const value = String(image.getAttribute(attr) || "").replace(/\s+/g, " ").trim();
          if (value) {
            attrs[attr] = shorten(value, 240);
          }
        });

        const locator = {
          tag: "img",
          index,
          text: "",
          attrs,
          context: [],
        };

        images.push({
          src,
          alt,
          element: `img: ${shorten(src, 100)}`,
          locator,
          highlight_url: highlightUrl(group, locator),
        });
      });
    });

    return images;
  }

  function mergeImages(existing, discovered) {
    const seen = new Set(existing.map((image) => imageKey(image.src)));
    discovered.forEach((image) => {
      const key = imageKey(image.src);
      if (!seen.has(key)) {
        existing.push(image);
        seen.add(key);
      }
    });
    return existing;
  }

  async function fillEmptyGroupsFromPublicPages(groups, status) {
    const emptyGroups = groups.filter((group) => !group.images.length && group.permalink);
    for (let index = 0; index < emptyGroups.length; index += 1) {
      const group = emptyGroups[index];
      status.textContent = `Finding page images ${index + 1} of ${emptyGroups.length}...`;
      try {
        const response = await fetch(group.permalink, { credentials: "same-origin" });
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }
        const html = await response.text();
        group.images = mergeImages(group.images, extractImagesFromPageHtml(html, group));
      } catch (error) {
        if (group.resultsNode) {
          const note = document.createElement("p");
          note.className = "leanwi-focused-detail";
          note.textContent = `Could not check public page images for OCR. ${error.message || error}`;
          group.resultsNode.appendChild(note);
        }
      }
    }
  }

  function createOcrIssue(image, wordCount, text) {
    const article = document.createElement("article");
    article.className = "leanwi-focused-issue leanwi-focused-review leanwi-focused-ocr-issue";

    const meta = document.createElement("div");
    meta.innerHTML = '<span class="leanwi-focused-badge">review</span> <strong>Images</strong>';

    const heading = document.createElement("h3");
    heading.textContent = "Image appears to contain a lot of text.";

    const detail = document.createElement("p");
    detail.className = "leanwi-focused-detail";
    detail.textContent = `OCR found about ${wordCount} words in this image. Sample: ${shorten(text, 180)}`;

    const suggestion = document.createElement("p");
    suggestion.textContent =
      "Treat this like an infographic, flyer, chart, poster, or schedule. Make sure the same information is available as real text on the page.";

    const element = document.createElement("p");
    element.className = "leanwi-focused-detail";
    element.textContent = `Element: ${image.element || image.src}`;

    const actions = document.createElement("p");
    actions.className = "leanwi-focused-issue-actions";
    if (image.highlight_url) {
      const viewLink = document.createElement("a");
      viewLink.className = "button button-small";
      viewLink.href = image.highlight_url;
      viewLink.target = "_blank";
      viewLink.rel = "noopener";
      viewLink.textContent = "View on Page";
      actions.appendChild(viewLink);
      actions.appendChild(document.createTextNode(" "));
    }

    const tutorialLink = document.createElement("a");
    tutorialLink.className = "button button-small";
    tutorialLink.href = "https://www.w3.org/WAI/tutorials/images/complex/";
    tutorialLink.target = "_blank";
    tutorialLink.rel = "noopener";
    tutorialLink.textContent = "Tutorial";
    actions.appendChild(tutorialLink);

    article.append(meta, heading, detail, suggestion, element, actions);
    return article;
  }

  async function recognizeImage(image) {
    if (!window.Tesseract || typeof window.Tesseract.recognize !== "function") {
      throw new Error("Tesseract.js did not load.");
    }
    const result = await window.Tesseract.recognize(image.src, "eng");
    return result && result.data && result.data.text ? result.data.text : "";
  }

  function getImageGroups() {
    return Array.from(document.querySelectorAll(".leanwi-focused-post")).map((post) => {
      const dataNode = post.querySelector(".leanwi-focused-ocr-data");
      const resultsNode = post.querySelector(".leanwi-focused-ocr-results");
      let images = [];
      if (dataNode && dataNode.textContent.trim()) {
        try {
          images = JSON.parse(dataNode.textContent);
        } catch (error) {
          images = [];
        }
      }
      return {
        post,
        resultsNode,
        images,
        postId: post.dataset.postId || "",
        permalink: post.dataset.permalink || "",
        highlightNonce: post.dataset.highlightNonce || "",
      };
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    const button = document.getElementById("leanwi-run-ocr");
    const status = document.getElementById("leanwi-ocr-status");
    const minWords = Number((window.leanwiFocusedReport && window.leanwiFocusedReport.ocrMinWords) || 10);

    if (!button || !status) {
      return;
    }

    const scriptVersion =
      window.leanwiFocusedReport && window.leanwiFocusedReport.scriptVersion
        ? `OCR script ${window.leanwiFocusedReport.scriptVersion}. `
        : "";
    const initialImageCount = getImageGroups().reduce((total, group) => total + group.images.length, 0);
    status.textContent = initialImageCount
      ? `${scriptVersion}Ready to scan ${initialImageCount} stored image${initialImageCount === 1 ? "" : "s"}. Empty pages will also be checked from public HTML.`
      : `${scriptVersion}No stored OCR candidates were found yet. The scan will also check the public page HTML.`;

    button.addEventListener("click", async function () {
      const groups = getImageGroups();

      document.querySelectorAll(".leanwi-focused-ocr-issue").forEach((node) => node.remove());
      groups.forEach((group) => {
        if (group.resultsNode) {
          group.resultsNode.textContent = "";
        }
      });

      button.disabled = true;

      await fillEmptyGroupsFromPublicPages(groups, status);
      const images = groups.flatMap((group) => group.images.map((image) => ({ ...image, group })));

      if (!images.length) {
        status.textContent =
          "No OCR candidate images were found in the pages currently shown or their public page HTML.";
        button.disabled = false;
        return;
      }

      if (!window.Tesseract || typeof window.Tesseract.recognize !== "function") {
        status.textContent = "Image text scan could not start because the OCR library did not load.";
        button.disabled = false;
        return;
      }

      status.textContent = `Scanning 0 of ${images.length} images...`;
      let flagged = 0;

      try {
        for (let index = 0; index < images.length; index += 1) {
          const image = images[index];
          status.textContent = `Scanning ${index + 1} of ${images.length} images...`;
          try {
            const text = await recognizeImage(image);
            const wordCount = countWords(text);
            if (wordCount >= minWords && image.group.resultsNode) {
              image.group.resultsNode.appendChild(createOcrIssue(image, wordCount, text));
              flagged += 1;
            }
          } catch (error) {
            if (image.group.resultsNode) {
              const note = document.createElement("p");
              note.className = "leanwi-focused-detail";
              note.textContent = `OCR could not read ${shorten(image.src, 120)}. ${error.message || error}`;
              image.group.resultsNode.appendChild(note);
            }
          }
        }
        status.textContent = `Image text scan complete. Reviewed ${images.length} image${images.length === 1 ? "" : "s"}; ${flagged} image${flagged === 1 ? "" : "s"} flagged.`;
      } finally {
        button.disabled = false;
      }
    });
  });
})();
