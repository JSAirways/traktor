    /**
     * Drag and drop reordering for content management (channel-level and content-level)
     */
    
import { showToast, makeRequest } from '../../core/utils.js';
import { t } from '../../core/i18n.js';

// Keyboard handler for div-based accordion buttons
// Bootstrap handles clicks automatically, but we need keyboard support for accessibility
document.addEventListener('keydown', (e) => {
    // Only handle Enter and Space keys
    if (e.key !== 'Enter' && e.key !== ' ') return;
    
    // Check if target is a div with accordion-button class and role="button"
    const accordionButton = e.target.closest('.accordion-button[role="button"]');
    if (!accordionButton) return;
    
    // Prevent default behavior
    e.preventDefault();
    
    // Trigger click to activate Bootstrap collapse
    accordionButton.click();
});

document.addEventListener('DOMContentLoaded', () => {
    const channelsAccordion = document.getElementById('channelsAccordion');
    
    if (!channelsAccordion) return;
        
        // Sortable should be available globally from the bundle
    if (typeof window === 'undefined' || typeof window.Sortable === 'undefined') {
            console.error('Sortable not available');
            return;
        }
    
    const { Sortable } = window;
    
    // Initialize channel-level drag and drop (reorder channels)
    const channelSortable = Sortable.create(channelsAccordion, {
        handle: '.channel-drag-handle',
        animation: 150,
        ghostClass: 'bg-light',
        filter: '.accordion-body', // Don't allow dragging when clicking on accordion body
        touchStartThreshold: 5,
        forceFallback: false,
        onEnd: async (evt) => {
            // Only get channel accordion items (not "All Content" which is not draggable)
            const channelItems = Array.from(channelsAccordion.querySelectorAll('.accordion-item[data-channel-id]:not([data-channel-id="all"])'));
            const channelOrder = channelItems.map(item => item.getAttribute('data-channel-id'));
            
            // Get user_id from the page (check if user selector exists)
            const userIdSelect = document.getElementById('user_id');
            const userId = userIdSelect ? parseInt(userIdSelect.value, 10) : null;
            
            if (!userId) {
                const errorMsg = t?.('messages.channel_order_update_failed', 'Channel order update failed') || 'Channel order update failed';
                    if (showToast) {
                        showToast(errorMsg, 'error', 5000);
                    }
                    return;
                }
                
                if (!makeRequest) {
                const errorMsg = 'makeRequest utility not available';
                    if (showToast) {
                    showToast(errorMsg, 'error', 5000);
                    }
                return;
            }
            
            // Send update to server
            try {
                const response = await makeRequest('/admin/content/reorder-channels', {
                method: 'POST',
                body: { 
                    user_id: userId,
                    channels: channelOrder 
                },
                    responseType: 'json'
                });
                
                    // Extract data from response object
                const data = response.data || response; // Backward compatibility fallback
                if (data?.success) {
                    const successMsg = t?.('messages.channel_order_updated', 'Channel order updated') || 'Channel order updated';
                        if (showToast) {
                            showToast(successMsg, 'success', 3000);
                        }
                    } else {
                    const errorMsg = t?.('messages.channel_order_update_failed', 'Channel order update failed') || 'Channel order update failed';
                        if (showToast) {
                        showToast(errorMsg, 'error', 5000);
                        }
                }
            } catch (error) {
                const errorMsg = t?.('messages.channel_order_update_failed', 'Channel order update failed') || 'Channel order update failed';
                    if (showToast) {
                    showToast(errorMsg, 'error', 5000);
                    }
            }
        }
    });
    
    // Initialize content-level drag and drop for each channel table
    const channelTables = channelsAccordion.querySelectorAll('table[data-channel-id]');
    for (const table of channelTables) {
        const tbody = table.querySelector('tbody');
            if (!tbody) continue;
        
        const channelId = table.getAttribute('data-channel-id');
        
        Sortable.create(tbody, {
            handle: '.drag-handle',
            animation: 150,
            ghostClass: 'bg-light',
            filter: '.no-drag', // Don't allow dragging of nested rows
            touchStartThreshold: 5,
            forceFallback: false,
            group: 'content-items', // Allow dragging between channels if needed
            onEnd: async (evt) => {
                // Only get main content rows (not expanded playlist rows) for this channel
                const items = Array.from(tbody.querySelectorAll('tr[data-id]:not(.no-drag)'));
                const order = items.map((row, index) => ({
                            id: parseInt(row.getAttribute('data-id'), 10),
                type: row.getAttribute('data-type'),
                    order: index + 1
                }));
                    
                    if (!makeRequest) {
                    const errorMsg = 'makeRequest utility not available';
                        if (showToast) {
                        showToast(errorMsg, 'error', 5000);
                        }
                        return;
                    }
            
            // Send update to server
                try {
                    const response = await makeRequest('/admin/content/reorder', {
                method: 'POST',
                body: { items: order },
                        responseType: 'json'
                    });
                    
                    // Extract data from response object
                    const data = response.data || response; // Backward compatibility fallback
                    if (data?.success) {
                        const successMsg = t?.('messages.order_updated', 'Order updated') || 'Order updated';
                            if (showToast) {
                            showToast(successMsg, 'success', 3000);
                            }
                    } else {
                        const errorMsg = t?.('messages.order_update_failed', 'Order update failed') || 'Order update failed';
                        if (showToast) {
                            showToast(errorMsg, 'error', 5000);
                        }
                    }
                } catch (error) {
                    const errorMsg = t?.('messages.order_update_failed', 'Order update failed') || 'Order update failed';
                            if (showToast) {
                        showToast(errorMsg, 'error', 5000);
                            }
                }
        }
    });
        }
    
    // Handle channel import buttons (prefill modal)
    const channelImportButtons = document.querySelectorAll('.channel-import-btn');
    const modal = document.getElementById('channelImportModal');
    
    // Store channel info to prefill when modal opens
    let pendingChannelInfo = null;
    
    // Set up one-time modal listener that checks for pending channel info
    if (modal) {
        modal.addEventListener('shown.bs.modal', () => {
            if (pendingChannelInfo) {
                const { channelId, channelName } = pendingChannelInfo;
                
                // Try to get channelImporter with retry logic
                const tryPrefill = (attempts = 0) => {
                    const channelImporter = typeof window !== 'undefined' && (window.channelImporter || window.Traktor?.Admin?.channelImport?.channelImporter);
                    
                    if (channelImporter && typeof channelImporter.prefillChannelInfo === 'function') {
                        channelImporter.prefillChannelInfo(channelId, channelName);
                        pendingChannelInfo = null; // Clear after successful prefill
                    } else if (attempts < 5) {
                        // Retry after a short delay if channelImporter isn't ready yet
                        setTimeout(() => tryPrefill(attempts + 1), 100);
                    }
                };
                
                tryPrefill();
            }
        });
    }
    
    // Set up button click handlers to store channel info
    for (const btn of channelImportButtons) {
        btn.addEventListener('click', () => {
            const channelId = btn.getAttribute('data-channel-id');
            const channelName = btn.getAttribute('data-channel-name');
            
            // Store channel info to be used when modal opens
            if (channelId) {
                pendingChannelInfo = { channelId, channelName };
            }
        });
    }
    
    // Make playlist rows clickable (except checkbox, drag handle, and action buttons)
    const playlistRows = document.querySelectorAll('tr.playlist-row');
    for (const row of playlistRows) {
        // Find the chevron button to get its collapse target
        const chevronBtn = row.querySelector('.playlist-chevron-btn');
        if (!chevronBtn) continue;
        
        // Make clickable cells have pointer cursor
        const clickableCells = row.querySelectorAll('.playlist-clickable-cell');
        for (const cell of clickableCells) {
            cell.style.cursor = 'pointer';
        }
        
        // Make the row clickable (but exclude certain elements)
        row.style.cursor = 'pointer';
        row.addEventListener('click', (e) => {
            // Don't trigger if clicking on excluded elements
            if (e.target.closest('.content-checkbox') || 
                e.target.closest('.drag-handle') ||
                e.target.closest('form') ||
                e.target.closest('.playlist-chevron-btn')) {
                return;
            }
            
            // Only trigger if clicking on clickable cells
            if (e.target.closest('.playlist-clickable-cell')) {
                // Trigger the collapse toggle via the chevron button
                chevronBtn.click();
            }
        });
    }
});

// toggleSelectAllChannel is now available from bulk-actions.js via window global
