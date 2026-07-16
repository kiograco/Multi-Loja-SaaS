document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-nav-path]').forEach(function (link) {
    if (window.location.pathname.startsWith(link.getAttribute('data-nav-path'))) {
      link.setAttribute('aria-current', 'page');
    }
  });
});

document.body.addEventListener('htmx:afterSwap', function (event) {
  event.detail.target.classList.add('is-entering');
});
