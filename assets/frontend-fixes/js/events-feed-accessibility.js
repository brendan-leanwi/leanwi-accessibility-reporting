document.addEventListener('DOMContentLoaded', function () {

  // Remove month separators
  document.querySelectorAll('.ecs-events-list-separator-month').forEach(el => {
    el.remove();
  });

  // Remove images from keyboard navigation
  document.querySelectorAll('.decm-show-image-left img').forEach(img => {
    img.setAttribute('tabindex', '-1');
    img.setAttribute('aria-hidden', 'true');
  });

  // Remove image links from keyboard navigation
  document.querySelectorAll('.decm-show-image-left a').forEach(link => {
    link.setAttribute('tabindex', '-1');
    link.setAttribute('aria-hidden', 'true');
  });

});