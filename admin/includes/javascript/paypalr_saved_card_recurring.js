/**
 * Change items per page
 */
function changePerPage(newPerPage) {
    const params = new URLSearchParams(window.location.search);
    params.set('page', '1'); // Reset to first page when changing per page
    params.set('per_page', newPerPage);
    window.location.href = baseUrl + '?' + params.toString();
}

function toggleEdit(el) {
    var parent = el.parentNode;
    var editContent = parent.querySelector('.edit-content');
    if (editContent) {
        editContent.classList.toggle('active');
    }
}

function updateProduct(id, originalOrdersProductsId) {
    var select = document.querySelector('select[name="set_products_id_' + id + '"]');
    var productsId = select ? select.value : '';
    window.location.href = baseUrl + '?action=update_product_id&saved_card_recurring_id=' + id + 
        '&set_products_id=' + productsId + '&original_orders_products_id=' + originalOrdersProductsId + queryString;
}

function updateAmount(id) {
    var input = document.getElementById('set_amount_' + id);
    var amount = input ? input.value : '';
    window.location.href = baseUrl + '?action=update_amount_subscription&saved_card_recurring_id=' + id + 
        '&set_amount=' + encodeURIComponent(amount) + queryString;
}

function updateDate(id) {
    var input = document.getElementById('set_date_' + id);
    var date = input ? input.value : '';
    window.location.href = baseUrl + '?action=update_payment_date&saved_card_recurring_id=' + id + 
        '&set_date=' + encodeURIComponent(date) + queryString;
}

function updateCard(id) {
    var select = document.querySelector('select[name="set_card_' + id + '"]');
    var cardId = select ? select.value : '';
    window.location.href = baseUrl + '?action=update_credit_card&saved_card_recurring_id=' + id + 
        '&set_card=' + cardId + queryString;
}

function toggleDetails(id) {
    var row = document.getElementById('details-' + id);
    if (row) {
        row.style.display = (row.style.display === 'none') ? 'table-row' : 'none';
    }
}

function toggleAddressEdit(id) {
    var display = document.getElementById('address-display-' + id);
    var edit = document.getElementById('address-edit-' + id);
    if (display && edit) {
        if (display.style.display === 'none') {
            display.style.display = 'block';
            edit.style.display = 'none';
        } else {
            display.style.display = 'none';
            edit.style.display = 'block';
        }
    }
}
