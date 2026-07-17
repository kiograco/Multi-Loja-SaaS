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

// Order-creation form (/app/orders/new): starts with a single item row;
// "+ Adicionar item" clones a <template> row instead of pre-rendering a fixed
// number of rows server-side. Also keeps quantity capped at the selected
// product's stock and blocks picking the same product in two rows — both
// client-side conveniences on top of the authoritative server-side checks in
// CreateOrderHandler/Order::create().
document.addEventListener('DOMContentLoaded', function () {
  var list = document.getElementById('order-items-list');
  var addButton = document.getElementById('order-item-add');
  var template = document.getElementById('order-item-row-template');
  if (!list || !addButton || !template) {
    return;
  }

  function rows() {
    return list.querySelectorAll('[data-order-item-row]');
  }

  function updateRemoveButtons() {
    var currentRows = rows();
    currentRows.forEach(function (row) {
      var removeButton = row.querySelector('[data-order-item-remove]');
      if (removeButton) {
        removeButton.hidden = currentRows.length <= 1;
      }
    });
  }

  function updateQuantityMax(select) {
    var row = select.closest('[data-order-item-row]');
    var quantityInput = row ? row.querySelector('[data-order-item-quantity]') : null;
    var selectedOption = select.options[select.selectedIndex];
    if (!quantityInput || !selectedOption) {
      return;
    }
    var stock = selectedOption.getAttribute('data-stock');
    if (!stock) {
      quantityInput.removeAttribute('max');

      return;
    }
    quantityInput.max = stock;
    if (parseInt(quantityInput.value, 10) > parseInt(stock, 10)) {
      quantityInput.value = stock;
    }
  }

  function updateDuplicateOptions() {
    var selects = list.querySelectorAll('[data-order-item-select]');
    var selectedValues = [];
    selects.forEach(function (select) {
      if (select.value) {
        selectedValues.push(select.value);
      }
    });
    selects.forEach(function (select) {
      Array.prototype.forEach.call(select.options, function (option) {
        if (!option.value) {
          return;
        }
        option.disabled = selectedValues.indexOf(option.value) !== -1 && select.value !== option.value;
      });
    });
  }

  list.addEventListener('change', function (event) {
    if (event.target.matches('[data-order-item-select]')) {
      updateQuantityMax(event.target);
      updateDuplicateOptions();
    }
  });

  list.addEventListener('click', function (event) {
    var removeButton = event.target.closest('[data-order-item-remove]');
    if (!removeButton) {
      return;
    }
    var row = removeButton.closest('[data-order-item-row]');
    if (row && rows().length > 1) {
      row.remove();
      updateRemoveButtons();
      updateDuplicateOptions();
    }
  });

  addButton.addEventListener('click', function () {
    list.appendChild(template.content.cloneNode(true));
    updateRemoveButtons();
  });

  updateRemoveButtons();
});
