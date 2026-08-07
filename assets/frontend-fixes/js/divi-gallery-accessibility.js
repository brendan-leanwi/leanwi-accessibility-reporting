// This script improves the image descriptions
document.addEventListener("DOMContentLoaded", function () {
  const galleryLinks = document.querySelectorAll(".et_pb_gallery_item a");

  galleryLinks.forEach(link => {
    const img = link.querySelector("img");

    if (img) {
      const altText = img.getAttribute("alt")?.trim();

      // If alt text exists and is more descriptive than the current aria-label, use it
      if (altText && altText.length > 0) {
        link.setAttribute("aria-label", altText);
      } else {
        // If no alt, remove misleading aria-label
        link.removeAttribute("aria-label");
      }
    }
  });
});

  // This script improves the pagenation functionality for screen readers
document.addEventListener("DOMContentLoaded", function () {
  // --- Fix gallery image link aria-labels ---
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

  // --- Improve gallery pagination accessibility ---
  const paginations = document.querySelectorAll(".et_pb_gallery_pagination");
  paginations.forEach(pagination => {
    pagination.setAttribute("role", "navigation");
    pagination.setAttribute("aria-label", "Gallery pages");

    const links = pagination.querySelectorAll("a");

    links.forEach(link => {
      const page = link.dataset.page;

      if (!page) return;

      if (page === "prev") {
        link.setAttribute("aria-label", "Previous page");
      } else if (page === "next") {
        link.setAttribute("aria-label", "Next page");
      } else {
        link.setAttribute("aria-label", `Go to page ${page}`);

        // Mark the active page
        if (link.classList.contains("active")) {
          link.setAttribute("aria-current", "page");
        }
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