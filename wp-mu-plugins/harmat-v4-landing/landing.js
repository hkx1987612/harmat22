(function () {
  "use strict";
  var page = document.querySelector('.hvl-page');
  if (!page) return;
  var dialog = page.querySelector('#plan-dialog');
  page.querySelectorAll('button[data-rooms]').forEach(function (button) {
    button.addEventListener('click', function () {
      page.querySelectorAll('button[data-rooms]').forEach(function (other) {
        other.setAttribute('aria-pressed', String(other === button));
      });
      page.querySelectorAll('.unit-card').forEach(function (card) {
        card.hidden = button.dataset.rooms !== 'all' && card.dataset.rooms !== button.dataset.rooms;
      });
    });
  });
  page.querySelectorAll('[data-plan]').forEach(function (button) {
    button.addEventListener('click', function () {
      var image = button.querySelector('img');
      page.querySelector('#plan-title').textContent = button.dataset.plan + ' · Alaprajz';
      var large = page.querySelector('#large-plan');
      large.src = image.src;
      large.alt = image.alt;
      if (typeof dialog.showModal === 'function') dialog.showModal();
      else window.open(image.src, '_blank', 'noopener');
    });
  });
  page.querySelector('[data-close-plan]').addEventListener('click', function () { dialog.close(); });
  if (window.HarmatNeighborhoodIcons) window.HarmatNeighborhoodIcons.createIcons();
})();
