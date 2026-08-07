document.addEventListener("DOMContentLoaded", function () {
  const accordions = document.querySelectorAll(".et_pb_accordion");

  accordions.forEach((accordion, accIdx) => {
    const toggles = accordion.querySelectorAll(".et_pb_toggle");

    toggles.forEach((toggle, i) => {
      const title = toggle.querySelector(".et_pb_toggle_title");
      const content = toggle.querySelector(".et_pb_toggle_content");
      if (!title || !content) return;

      // IDs
      const contentId = `accordion-${accIdx}-content-${i}`;
      const headerId  = `accordion-${accIdx}-header-${i}`;
      content.id = contentId;
      content.setAttribute("role", "region");
      content.setAttribute("aria-labelledby", headerId);
      content.hidden = !toggle.classList.contains("et_pb_toggle_open");

      // Replace text with a <button> inside the heading
      const text = title.textContent.trim();
      title.textContent = "";
      const button = document.createElement("button");
      button.type = "button";
      button.id = headerId;
      button.textContent = text;
      button.setAttribute("aria-controls", contentId);
      button.setAttribute(
        "aria-expanded",
        toggle.classList.contains("et_pb_toggle_open") ? "true" : "false"
      );
      title.appendChild(button);

      // Click handler
      button.addEventListener("click", () => {
        const expanded = button.getAttribute("aria-expanded") === "true";

        // Toggle this panel only
        button.setAttribute("aria-expanded", expanded ? "false" : "true");
        content.hidden = expanded;

        // Sync Divi classes for styling
        toggle.classList.toggle("et_pb_toggle_open", !expanded);
        toggle.classList.toggle("et_pb_toggle_close", expanded);
      });
    });
  });
});
