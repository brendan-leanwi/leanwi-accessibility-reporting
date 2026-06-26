(function () {
  function countWords(text) {
    const matches = String(text || "").match(/[A-Za-z0-9][A-Za-z0-9'-]*/g);
    return matches ? matches.length : 0;
  }

  function shorten(text, limit) {
    const value = String(text || "").replace(/\s+/g, " ").trim();
    return value.length <= limit ? value : value.slice(0, limit - 1).trim() + "...";
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

    const link = document.createElement("p");
    link.innerHTML =
      '<a href="https://www.w3.org/WAI/tutorials/images/complex/" target="_blank" rel="noopener">Tutorial</a>';

    article.append(meta, heading, detail, suggestion, element, link);
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
      return { post, resultsNode, images };
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    const button = document.getElementById("leanwi-run-ocr");
    const status = document.getElementById("leanwi-ocr-status");
    const minWords = Number((window.leanwiFocusedReport && window.leanwiFocusedReport.ocrMinWords) || 10);

    if (!button || !status) {
      return;
    }

    button.addEventListener("click", async function () {
      const groups = getImageGroups();
      const images = groups.flatMap((group) => group.images.map((image) => ({ ...image, group })));

      document.querySelectorAll(".leanwi-focused-ocr-issue").forEach((node) => node.remove());
      groups.forEach((group) => {
        if (group.resultsNode) {
          group.resultsNode.textContent = "";
        }
      });

      if (!images.length) {
        status.textContent = "No images were available for OCR on this report.";
        return;
      }

      button.disabled = true;
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
        status.textContent = `Image text scan complete. ${flagged} image${flagged === 1 ? "" : "s"} flagged.`;
      } finally {
        button.disabled = false;
      }
    });
  });
})();
