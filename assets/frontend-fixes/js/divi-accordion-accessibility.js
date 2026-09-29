document.addEventListener("DOMContentLoaded", function () {
  const accordions = document.querySelectorAll(".et_pb_accordion");

  accordions.forEach((accordion, accordionIndex) => {
    const items = [];

    accordion.querySelectorAll(".et_pb_accordion_item").forEach((toggle, itemIndex) => {
      const title = toggle.querySelector(".et_pb_toggle_title");
      const content = toggle.querySelector(".et_pb_toggle_content");
      if (!title || !content) return;

      const contentId = `accordion-${accordionIndex}-content-${itemIndex}`;
      const headerId = `accordion-${accordionIndex}-header-${itemIndex}`;
      const titleText = title.textContent.trim();
      let button = title.querySelector("button");

      if (!button) {
        button = document.createElement("button");
        button.type = "button";
        button.textContent = titleText;
        title.textContent = "";
        title.appendChild(button);
      }

      button.id = headerId;
      button.setAttribute("aria-controls", contentId);
      content.id = contentId;
      content.setAttribute("role", "region");
      content.setAttribute("aria-labelledby", headerId);

      const item = { toggle, button, content };
      items.push(item);

      button.addEventListener("click", function (event) {
        event.preventDefault();
        event.stopPropagation();

        const willExpand = button.getAttribute("aria-expanded") !== "true";
        if (willExpand) {
          items.forEach((otherItem) => {
            if (otherItem !== item) setExpanded(otherItem, false);
          });
        }

        setExpanded(item, willExpand);
      });
    });

    items.forEach((item) => {
      setExpanded(item, item.toggle.classList.contains("et_pb_toggle_open"));
    });
  });

  function setExpanded(item, expanded) {
    item.button.setAttribute("aria-expanded", expanded ? "true" : "false");
    item.content.hidden = !expanded;
    item.content.style.display = expanded ? "block" : "none";
    item.toggle.classList.toggle("et_pb_toggle_open", expanded);
    item.toggle.classList.toggle("et_pb_toggle_close", !expanded);
  }
});
