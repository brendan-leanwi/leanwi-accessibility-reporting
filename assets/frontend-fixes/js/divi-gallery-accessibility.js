document.addEventListener("DOMContentLoaded", function () {
  // Improve gallery image link descriptions.
  const galleryLinks = document.querySelectorAll(".et_pb_gallery_item a");
  galleryLinks.forEach(link => {
    const img = link.querySelector("img");
    if (img) {
      const altText = img.getAttribute("alt")?.trim();
      if (altText && altText.length > 0) {
        link.setAttribute("aria-label", altText);
      } else {
        link.removeAttribute("aria-label");
      }
    }
  });

  // Improve gallery pagination semantics without replacing Divi's handlers.
  const paginations = document.querySelectorAll(".et_pb_gallery_pagination");
  paginations.forEach(pagination => {
    pagination.setAttribute("role", "navigation");
    pagination.setAttribute("aria-label", "Gallery pages");

    function updatePaginationState() {
      const links = pagination.querySelectorAll("a[data-page]");

      links.forEach(link => {
        const page = link.dataset.page;

        if (page === "prev") {
          link.setAttribute("aria-label", "Previous page");
        } else if (page === "next") {
          link.setAttribute("aria-label", "Next page");
        } else {
          link.setAttribute("aria-label", `Go to page ${page}`);

          if (link.classList.contains("active")) {
            link.setAttribute("aria-current", "page");
          } else {
            link.removeAttribute("aria-current");
          }
        }
      });
    }

    updatePaginationState();

    // Divi changes the active class after pagination. Keep aria-current in sync.
    const observer = new MutationObserver(updatePaginationState);
    observer.observe(pagination, {
      attributes: true,
      attributeFilter: ["class"],
      childList: true,
      subtree: true
    });

    // Enter activates links natively. Also support Space for users who treat
    // these page links like pagination buttons.
    pagination.addEventListener("keydown", function (event) {
      const link = event.target.closest && event.target.closest("a[data-page]");

      if (link && event.key === " ") {
        event.preventDefault();
        link.click();
      }
    });
  });
});

// Add event listener for skip link keyboard focus
document.addEventListener("DOMContentLoaded", function() {
  const skipLink = document.querySelector('a[href="#after-gallery"]');
  const target = document.getElementById("after-gallery");

  if (skipLink && target) {
    skipLink.addEventListener("click", function(e) {
      e.preventDefault(); // Prevent default jump
      target.setAttribute("tabindex", "-1"); // Make it focusable temporarily
      target.focus(); // Move keyboard focus
      window.scrollTo({ top: target.offsetTop, behavior: "smooth" }); // Optional smooth scroll
    });
  }
});
