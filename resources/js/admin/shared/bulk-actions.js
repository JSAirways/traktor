/**
 * Bulk actions utility for admin content management
 * Handles selection, visibility toggling, and deletion of content items
 */

import { makeRequest, showToast } from '../../core/utils.js';
import { t } from '../../core/i18n.js';

/**
 * Get all currently selected content items
 * @param {string|null} channelId - Optional channel ID to scope selection to a specific channel
 * @returns {Array<{id: number, type: string}>} Array of selected items
 */
export function getSelectedItems(channelId = null) {
    let checkboxes;
    if (channelId) {
        // Scope to specific channel
        const table = document.querySelector(`table[data-channel-id="${channelId}"]`);
        if (!table) return [];
        const tbody = table.querySelector('tbody');
        if (!tbody) return [];
        checkboxes = tbody.querySelectorAll('.content-checkbox:checked');
    } else {
        // Global selection (all channels)
        checkboxes = document.querySelectorAll('.content-checkbox:checked');
    }
    
    return Array.from(checkboxes).map(cb => ({
        id: parseInt(cb.getAttribute('data-id'), 10),
        type: cb.getAttribute('data-type')
    }));
}

/**
 * Update the bulk actions toolbar visibility and selected count
 * @param {string|null} channelId - Optional channel ID to update a specific channel's toolbar
 */
export function updateBulkActionsToolbar(channelId = null) {
    const selected = getSelectedItems(channelId);
    const count = selected.length;

    // Get toolbar and count span for this channel (or global if no channelId)
    const toolbarId = channelId ? `bulkActionsToolbar-${channelId}` : 'bulkActionsToolbar';
    const countSpanId = channelId ? `selectedCount-${channelId}` : 'selectedCount';

    const toolbar = document.getElementById(toolbarId);
    const countSpan = document.getElementById(countSpanId);

    // Only update toolbar if it exists (when there's content)
    if (toolbar && countSpan) {
        if (count > 0) {
            toolbar.style.display = 'block';
            
            const itemsText = count === 1 
                ? (t?.('common.item_selected', 'item selected') || 'item selected')
                : (t?.('common.items_selected', 'items selected') || 'items selected');
            countSpan.textContent = `${count} ${itemsText}`;
        } else {
            toolbar.style.display = 'none';
        }
    }
    
    // Update select all checkbox state for this channel
    if (channelId) {
        const selectAll = document.querySelector(`.channel-select-all[data-channel-id="${channelId}"]`);
        if (selectAll) {
            const table = document.querySelector(`table[data-channel-id="${channelId}"]`);
            if (table) {
                const tbody = table.querySelector('tbody');
                if (tbody) {
                    const allCheckboxes = tbody.querySelectorAll('.content-checkbox:not([disabled])');
                    const checkedCount = tbody.querySelectorAll('.content-checkbox:checked').length;
                    selectAll.indeterminate = checkedCount > 0 && checkedCount < allCheckboxes.length;
                    selectAll.checked = checkedCount === allCheckboxes.length && allCheckboxes.length > 0;
                }
            }
        }
    } else {
        // Global select all (if exists)
        const selectAllGlobal = document.getElementById('selectAll');
        if (selectAllGlobal) {
            const allCheckboxesGlobal = document.querySelectorAll('.content-checkbox');
            const checkedCountGlobal = document.querySelectorAll('.content-checkbox:checked').length;
            selectAllGlobal.indeterminate = checkedCountGlobal > 0 && checkedCountGlobal < allCheckboxesGlobal.length;
            selectAllGlobal.checked = checkedCountGlobal === allCheckboxesGlobal.length && allCheckboxesGlobal.length > 0;
        }
    }
}

/**
 * Toggle all content checkboxes
 * @param {HTMLInputElement} checkbox - The select all checkbox
 */
export function toggleSelectAll(checkbox) {
    const checkboxes = document.querySelectorAll('.content-checkbox:not([disabled])');
    for (const cb of checkboxes) {
        cb.checked = checkbox.checked;
    }
    // Update all channel toolbars
    updateAllBulkActionsToolbars();
}

/**
 * Clear all selections
 * @param {string|null} channelId - Optional channel ID to clear selections for a specific channel
 */
export function clearSelection(channelId = null) {
    let checkboxes;
    let selectAll;

    if (channelId) {
        // Clear selections for specific channel
        const table = document.querySelector(`table[data-channel-id="${channelId}"]`);
        if (!table) return;
        const tbody = table.querySelector('tbody');
        if (!tbody) return;
        checkboxes = tbody.querySelectorAll('.content-checkbox');
        selectAll = document.querySelector(`.channel-select-all[data-channel-id="${channelId}"]`);
        for (const cb of checkboxes) {
            cb.checked = false;
        }
        if (selectAll) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
        }
        updateBulkActionsToolbar(channelId);
    } else {
        // Clear all selections globally
        checkboxes = document.querySelectorAll('.content-checkbox');
        for (const cb of checkboxes) {
            cb.checked = false;
        }
        // Clear all channel select-all checkboxes
        const channelSelectAlls = document.querySelectorAll('.channel-select-all');
        for (const selectAllEl of channelSelectAlls) {
            selectAllEl.checked = false;
            selectAllEl.indeterminate = false;
        }
        updateAllBulkActionsToolbars();
    }
}

/**
 * Update all bulk actions toolbars (for all channels)
 */
export function updateAllBulkActionsToolbars() {
    // Get all channel tables and update their toolbars
    const tables = document.querySelectorAll('table[data-channel-id]');
    for (const table of tables) {
        const channelId = table.getAttribute('data-channel-id');
        updateBulkActionsToolbar(channelId);
    }
}

/**
 * Perform bulk action (show, hide, or delete)
 * @param {string} action - Action to perform ('show', 'hide', 'delete')
 * @param {string|null} channelId - Optional channel ID to scope action to a specific channel
 * @param {Object} config - Configuration object with routes and translations
 */
export async function performBulkAction(action, channelId = null, config = {}) {
    const selected = getSelectedItems(channelId);
    
    if (selected.length === 0) {
        const errorMsg = t?.('common.please_select_at_least_one_item', 'Please select at least one item.') || 'Please select at least one item.';
        alert(errorMsg);
        return;
    }
    
    let confirmMessage = '';
    let url = '';

    switch(action) {
        case 'show':
            confirmMessage = (t?.('common.confirm_show_items', 'Are you sure you want to show :count item(s)?') || 'Are you sure you want to show :count item(s)?').replace(':count', selected.length);
            url = config.routes?.bulkVisibility || '/admin/content/bulk-visibility';
            break;
        case 'hide':
            confirmMessage = (t?.('common.confirm_hide_items', 'Are you sure you want to hide :count item(s)?') || 'Are you sure you want to hide :count item(s)?').replace(':count', selected.length);
            url = config.routes?.bulkVisibility || '/admin/content/bulk-visibility';
            break;
        case 'delete':
            confirmMessage = (t?.('common.confirm_delete_items', 'Are you sure you want to delete :count item(s)? This action cannot be undone!') || 'Are you sure you want to delete :count item(s)? This action cannot be undone!').replace(':count', selected.length);
            url = config.routes?.bulkDelete || '/admin/content/bulk-delete';
            break;
        default:
            console.error('Unknown bulk action:', action);
            return;
    }
    
    if (!confirm(confirmMessage)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('items', JSON.stringify(selected));
    
    if (action === 'show' || action === 'hide') {
        formData.append('visible', action === 'show' ? '1' : '0');
    }
    
    if (!makeRequest) {
        alert('makeRequest utility not available');
        return;
    }
    
    try {
        const response = await makeRequest(url, {
            method: 'POST',
            body: formData,
            responseType: 'json'
        });
        
        // Extract data from response object
        const result = response.data || response; // Backward compatibility fallback
        if (result?.success) {
            let successMsg = result.message;
            if (!successMsg) {
                successMsg = t?.('common.action_completed_successfully', 'Action completed successfully') || 'Action completed successfully';
            }
            if (showToast) {
                showToast(successMsg, 'success', 3000);
            }
            // Clear selections for this channel after successful action
            if (channelId) {
                clearSelection(channelId);
            } else {
                clearSelection();
            }
            window.location.reload();
        } else {
            let errorMsg = t?.('common.error_occurred', 'Error: ') || 'Error: ';
            const message = result?.message || (t?.('common.failed_to_perform_bulk_action', 'Failed to perform bulk action') || 'Failed to perform bulk action');
            errorMsg = errorMsg.replace(':message', message);
            if (showToast) {
                showToast(errorMsg, 'error', 5000);
            }
        }
    } catch (error) {
        const errorMsg = t?.('common.failed_to_perform_bulk_action', 'Failed to perform bulk action') || 'Failed to perform bulk action';
        if (showToast) {
            showToast(errorMsg, 'error', 5000);
        }
    }
}

/**
 * Toggle select all for a specific channel
 * @param {HTMLInputElement} checkbox - The channel select all checkbox
 * @param {string} channelId - The channel ID
 */
export function toggleSelectAllChannel(checkbox, channelId) {
    const table = document.querySelector(`table[data-channel-id="${channelId}"]`);
    if (!table) return;
    
    const tbody = table.querySelector('tbody');
    if (!tbody) return;
    
    const checkboxes = tbody.querySelectorAll('.content-checkbox:not([disabled])');
    for (const cb of checkboxes) {
        cb.checked = checkbox.checked;
    }
    updateBulkActionsToolbar(channelId);
}

// Make functions available globally for inline event handlers and backward compatibility
if (typeof window !== 'undefined') {
    window.updateBulkActionsToolbar = updateBulkActionsToolbar;
    window.updateAllBulkActionsToolbars = updateAllBulkActionsToolbars;
    window.toggleSelectAll = toggleSelectAll;
    window.clearSelection = clearSelection;
    window.bulkAction = performBulkAction;
    window.toggleSelectAllChannel = toggleSelectAllChannel;
    window.getSelectedItems = getSelectedItems;
    
    // Also export to Traktor namespace for backward compatibility
    if (!window.Traktor) {
        window.Traktor = {};
    }
    if (!window.Traktor.Admin) {
        window.Traktor.Admin = {};
    }
    window.Traktor.Admin.bulkActions = {
        getSelectedItems,
        updateBulkActionsToolbar,
        updateAllBulkActionsToolbars,
        toggleSelectAll,
        clearSelection,
        performBulkAction,
        toggleSelectAllChannel
    };
}
