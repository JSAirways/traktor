    /**
     * Pending Users Management JavaScript
     * Handles bulk selection and approval/rejection of pending user registrations
     */
    
import { getTranslation } from '../../core/utils.js';
    
document.addEventListener('DOMContentLoaded', () => {
        // Get translations and routes from window object (set by Blade template)
    const translations = typeof window !== 'undefined' ? (window.pendingUsersTranslations || {}) : {};
    const routes = typeof window !== 'undefined' ? (window.pendingUsersRoutes || {}) : {};
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta?.content || null;
    
    if (!csrfToken) {
        return;
    }
    
        /**
         * Toggle all checkboxes
         */
        function toggleSelectAll(checkbox) {
        const checkboxes = document.querySelectorAll('.user-checkbox');
        for (const cb of checkboxes) {
            cb.checked = checkbox.checked;
            }
            updateSelectedCount();
        }
        
        /**
         * Update the selected count display
         */
        function updateSelectedCount() {
        const selected = document.querySelectorAll('.user-checkbox:checked');
        const count = selected.length;
        const countSpan = document.getElementById('selectedCount');
            if (countSpan) {
                countSpan.textContent = count;
            }
        }
        
        /**
         * Get selected user IDs
         */
        function getSelectedUserIds() {
        const checkboxes = document.querySelectorAll('.user-checkbox:checked');
        return Array.from(checkboxes).map(cb => cb.value);
        }
    
        /**
         * Bulk approve selected users
         */
        function bulkApprove() {
        const selected = getSelectedUserIds();
            if (selected.length === 0) {
            const errorMsg = getTranslation?.('admin.please_select_at_least_one_user', 'Please select at least one user') || 'Please select at least one user';
                alert(translations.pleaseSelectAtLeastOneUser || errorMsg);
                return;
            }
            
        const confirmMsg = (translations.confirmApproveUsers || 'Are you sure you want to approve :count user(s)?').replace(':count', selected.length);
            if (!confirm(confirmMsg)) {
                return;
            }
            
        const form = document.createElement('form');
            form.method = 'POST';
            form.action = routes.bulkApprove || '/admin/users/bulk-approve';
            
        const csrf = document.createElement('input');
            csrf.type = 'hidden';
            csrf.name = '_token';
            csrf.value = csrfToken;
            form.appendChild(csrf);
            
        for (const userId of selected) {
            const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'user_ids[]';
                input.value = userId;
                form.appendChild(input);
            }
            
            document.body.appendChild(form);
            form.submit();
        }
        
        /**
         * Bulk reject selected users (opens modal)
         */
        function bulkReject() {
        const selected = getSelectedUserIds();
            if (selected.length === 0) {
            const errorMsg = getTranslation?.('admin.please_select_at_least_one_user', 'Please select at least one user') || 'Please select at least one user';
                alert(translations.pleaseSelectAtLeastOneUser || errorMsg);
                return;
            }
            
            updateSelectedCount();
            // Modal will be shown by data-bs-toggle
        }
        
        // Attach functions to window for inline onclick handlers
    if (typeof window !== 'undefined') {
        window.toggleSelectAll = toggleSelectAll;
        window.bulkApprove = bulkApprove;
        window.bulkReject = bulkReject;
    }
        
        // Update form submission for bulk reject
    const bulkRejectForm = document.getElementById('bulkRejectForm');
        if (bulkRejectForm) {
        bulkRejectForm.addEventListener('submit', (e) => {
            const selected = getSelectedUserIds();
                if (selected.length === 0) {
                    e.preventDefault();
                const errorMsg = getTranslation?.('admin.please_select_at_least_one_user', 'Please select at least one user') || 'Please select at least one user';
                    alert(translations.pleaseSelectAtLeastOneUser || errorMsg);
                    return;
                }
                
                // Add selected user IDs to form
            for (const userId of selected) {
                const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'user_ids[]';
                    input.value = userId;
                bulkRejectForm.appendChild(input);
                }
            });
        }
        
        // Update count when checkboxes change
    const userCheckboxes = document.querySelectorAll('.user-checkbox');
    for (const checkbox of userCheckboxes) {
        checkbox.addEventListener('change', updateSelectedCount);
        }
        
        // Initialize count
        updateSelectedCount();
    });
