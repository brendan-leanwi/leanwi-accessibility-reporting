document.addEventListener("DOMContentLoaded", function () {
  const toggles = document.querySelectorAll(".et_pb_toggle");

  toggles.forEach((toggle, i) => {
    const title = toggle.querySelector(".et_pb_toggle_title");
    const content = toggle.querySelector(".et_pb_toggle_content");

    if (!title || !content) return;

    // Give the content an ID for aria-controls
    const contentId = `toggle-content-${i}`;
    content.setAttribute("id", contentId);

    // Replace title text with a button (but keep heading wrapper <h3>)
    const button = document.createElement("button");
    button.type = "button";
    button.innerHTML = title.textContent + 
      '<span class="sr-only"> Press Enter or Space to toggle content.</span>';

    // Initial aria state
    const isOpen = toggle.classList.contains("et_pb_toggle_open");
    button.setAttribute("aria-expanded", isOpen ? "true" : "false");
    button.setAttribute("aria-controls", contentId);

    // Clear old text and insert button
    title.textContent = "";
    title.appendChild(button);

    // Update aria-expanded on click
    button.addEventListener("click", () => {
      const expanded = button.getAttribute("aria-expanded") === "true";
      button.setAttribute("aria-expanded", expanded ? "false" : "true");
    });
  });
});

