/**
 * Change items per page
 */
function changePerPage(newPerPage) {
    const params = new URLSearchParams(window.location.search);
    params.set('page', '1'); // Reset to first page when changing per page
    params.set('per_page', newPerPage);
    window.location.href = paypalrSubscriptionsBaseUrl + '?' + params.toString();
}

/**
 * Toggle subscription row expand/collapse
 */
function toggleSubscription(subscriptionId, event) {
    const summaryRow = document.querySelector('.subscription-summary[data-subscription-id="' + subscriptionId + '"]');
    const detailsRow = document.querySelector('.details-row[data-subscription-id="' + subscriptionId + '"]');
    
    if (summaryRow && detailsRow) {
        summaryRow.classList.toggle('subscription-row-collapsed');
        
        // Prevent event bubbling to prevent conflicts with form elements
        if (event) {
            event.stopPropagation();
        }
    }
}

// Prevent clicks on form elements from triggering the row toggle
document.addEventListener('DOMContentLoaded', function() {
    const formElements = document.querySelectorAll('.details-row input, .details-row select, .details-row textarea, .details-row button, .details-row a');
    formElements.forEach(function(element) {
        element.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });
    
    // Select All / Unselect All functionality
    const selectAllCheckbox = document.getElementById('select-all-subscriptions');
    const subscriptionCheckboxes = document.querySelectorAll('.subscription-checkbox');
    const selectedCountSpan = document.getElementById('selected-count');
    const applyBulkActionBtn = document.getElementById('apply-bulk-action');
    const bulkActionSelect = document.getElementById('bulk-action-select');
    const bulkActionsForm = document.getElementById('bulk-actions-form');
    
    // Update selected count display
    function updateSelectedCount() {
        const checkedCount = document.querySelectorAll('.subscription-checkbox:checked').length;
        if (checkedCount > 0) {
            selectedCountSpan.textContent = checkedCount + ' subscription(s) selected';
        } else {
            selectedCountSpan.textContent = '';
        }
    }
    
    // Select all checkbox handler
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            subscriptionCheckboxes.forEach(function(checkbox) {
                checkbox.checked = selectAllCheckbox.checked;
            });
            updateSelectedCount();
        });
    }
    
    // Individual checkbox handler
    subscriptionCheckboxes.forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            // Update select all checkbox state
            const allChecked = Array.from(subscriptionCheckboxes).every(cb => cb.checked);
            const someChecked = Array.from(subscriptionCheckboxes).some(cb => cb.checked);
            
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = allChecked;
                selectAllCheckbox.indeterminate = someChecked && !allChecked;
            }
            
            updateSelectedCount();
        });
    });
    
    // Apply bulk action handler
    if (applyBulkActionBtn) {
        applyBulkActionBtn.addEventListener('click', function() {
            const selectedAction = bulkActionSelect.value;
            const checkedBoxes = document.querySelectorAll('.subscription-checkbox:checked');
            const checkedCount = checkedBoxes.length;
            
            if (!selectedAction) {
                alert('Please select a bulk action.');
                return;
            }
            
            if (checkedCount === 0) {
                alert('Please select at least one subscription.');
                return;
            }
            
            // Confirmation messages
            let confirmMessage = '';
            if (selectedAction === 'bulk_archive') {
                confirmMessage = 'Are you sure you want to archive ' + checkedCount + ' subscription(s)?';
            } else if (selectedAction === 'bulk_unarchive') {
                confirmMessage = 'Are you sure you want to unarchive ' + checkedCount + ' subscription(s)?';
            }
            
            if (confirm(confirmMessage)) {
                // Set the action in the hidden field
                bulkActionsForm.querySelector('input[name="action"]').value = selectedAction;
                // Remove any previously injected subscription ids
                bulkActionsForm.querySelectorAll('input[name="subscription_ids[]"]').forEach(function(input) {
                    input.remove();
                });
                bulkActionsForm.querySelectorAll('input[name^="subscription_types["]').forEach(function(input) {
                    input.remove();
                });
                // Inject selected subscription ids and types into the bulk form payload
                checkedBoxes.forEach(function(checkbox) {
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'subscription_ids[]';
                    hiddenInput.value = checkbox.value;
                    bulkActionsForm.appendChild(hiddenInput);

                    const typeInput = document.createElement('input');
                    typeInput.type = 'hidden';
                    typeInput.name = 'subscription_types[' + checkbox.value + ']';
                    typeInput.value = checkbox.dataset.subscriptionType || '';
                    bulkActionsForm.appendChild(typeInput);
                });
                // Submit the form
                bulkActionsForm.submit();
            }
        });
    }
    
    // Initialize count on page load
    updateSelectedCount();
});
