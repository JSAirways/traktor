/**
 * Channel Import Module
 * 
 * Handles fetching and importing videos/playlists from YouTube channels.
 * Uses session storage for client-side caching to minimize API quota usage.
 */

import { makeRequest, escapeHtml, parseIntSafe, getTranslation, formatDuration, showToast } from '../../core/utils.js';

class ChannelImporter {
    constructor() {
        this.channelInfo = null;
        this.currentContentType = 'uploads';
        this.currentPage = 1;
        this.nextPageToken = null;
        this.selectedItems = {}; // Object<itemId, {type, id, title}>
        this.currentItems = [];
        this.sessionCache = {}; // Client-side cache
        this.isImporting = false; // Flag to prevent duplicate import requests
        this.existingVideoIds = new Set(); // Use Set for O(1) lookups
        this.existingPlaylistIds = new Set(); // Use Set for O(1) lookups
        this.existingIdsLoaded = false; // Track if IDs have been loaded
        
        this.initializeElements();
        this.attachEventListeners();
    }
    
    initializeElements() {
        // Input section
        this.channelInput = document.getElementById('channelInput');
        this.fetchChannelBtn = document.getElementById('fetchChannelBtn');
        this.contentTypeSection = document.getElementById('contentTypeSection');
        this.contentTypeUploads = document.getElementById('contentTypeUploads');
        this.contentTypePlaylists = document.getElementById('contentTypePlaylists');
        
        // State sections
        this.channelInputSection = document.getElementById('channelInputSection');
        this.channelLoadingState = document.getElementById('channelLoadingState');
        this.channelErrorState = document.getElementById('channelErrorState');
        this.channelErrorMessage = document.getElementById('channelErrorMessage');
        this.channelResultsSection = document.getElementById('channelResultsSection');
        
        // Results section
        this.channelTitle = document.getElementById('channelTitle');
        this.channelStats = document.getElementById('channelStats');
        this.selectAllItems = document.getElementById('selectAllItems');
        this.selectedItemsCount = document.getElementById('selectedItemsCount');
        this.channelItemsGrid = document.getElementById('channelItemsGrid');
        
        // Pagination
        this.prevPageBtn = document.getElementById('prevPageBtn');
        this.nextPageBtn = document.getElementById('nextPageBtn');
        this.paginationInfo = document.getElementById('paginationInfo');
        
        // Actions
        this.importSelectedBtn = document.getElementById('importSelectedBtn');
        this.importSelectedBtnCount = document.getElementById('importSelectedBtnCount');
        this.importAllBtn = document.getElementById('importAllBtn');
        this.importAllBtnCount = document.getElementById('importAllBtnCount');
        
        // Modal
        this.modal = document.getElementById('channelImportModal');
    }
    
    attachEventListeners() {
        // Guard against missing elements
        if (!this.fetchChannelBtn || !this.channelInput) {
            return;
        }
        
        // Fetch channel button
        this.fetchChannelBtn.addEventListener('click', () => this.fetchChannel());
        
        // Enter key on input
        this.channelInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.fetchChannel();
            }
        });
        
        // Content type toggle
        if (this.contentTypeUploads) {
            this.contentTypeUploads.addEventListener('change', () => {
                if (this.contentTypeUploads.checked) {
                    this.switchContentType('uploads');
                }
            });
        }
        
        if (this.contentTypePlaylists) {
            this.contentTypePlaylists.addEventListener('change', () => {
                if (this.contentTypePlaylists.checked) {
                    this.switchContentType('playlists');
                }
            });
        }
        
        // Select all checkbox
        if (this.selectAllItems) {
            this.selectAllItems.addEventListener('change', (e) => {
                this.toggleSelectAll(e.target.checked);
            });
        }
        
        // Pagination
        if (this.prevPageBtn) {
            this.prevPageBtn.addEventListener('click', () => this.previousPage());
        }
        if (this.nextPageBtn) {
            this.nextPageBtn.addEventListener('click', () => this.nextPage());
        }
        
        // Import buttons
        if (this.importSelectedBtn) {
            this.importSelectedBtn.addEventListener('click', () => this.importSelected(false));
        }
        if (this.importAllBtn) {
            this.importAllBtn.addEventListener('click', () => this.importSelected(true));
        }
        
        // Modal reset on close
        if (this.modal) {
            this.modal.addEventListener('hidden.bs.modal', () => {
                this.resetModal();
            });
            // Load existing IDs when modal opens
            this.modal.addEventListener('show.bs.modal', () => {
                this.loadExistingIds();
            });
        }
    }
    
    /**
     * Show/hide UI sections based on current state
     * @param {string} state - One of: 'input', 'loading', 'error', 'results'
     */
    showState(state) {
        // Hide all states
        if (this.channelInputSection) this.channelInputSection.style.display = 'none';
        if (this.channelLoadingState) this.channelLoadingState.style.display = 'none';
        if (this.channelErrorState) this.channelErrorState.style.display = 'none';
        if (this.channelResultsSection) this.channelResultsSection.style.display = 'none';
        
        // Show requested state
        switch(state) {
            case 'input':
                if (this.channelInputSection) this.channelInputSection.style.display = 'block';
                break;
            case 'loading':
                if (this.channelInputSection) this.channelInputSection.style.display = 'block';
                if (this.channelLoadingState) this.channelLoadingState.style.display = 'block';
                break;
            case 'error':
                if (this.channelInputSection) this.channelInputSection.style.display = 'block';
                if (this.channelErrorState) this.channelErrorState.style.display = 'block';
                break;
            case 'results':
                if (this.channelInputSection) this.channelInputSection.style.display = 'block';
                if (this.channelResultsSection) this.channelResultsSection.style.display = 'block';
                break;
        }
    }
    
    /**
     * Display error message to user
     * @param {string} message - Error message to display
     */
    showError(message) {
        if (this.channelErrorMessage) {
            this.channelErrorMessage.textContent = message;
        }
        this.showState('error');
    }
    
    /**
     * Load existing content IDs for the selected user (called once)
     * Uses Sets for O(1) lookup performance
     */
    async loadExistingIds() {
        const userIdSelect = document.querySelector('select[name="user_id"]');
        const userIdInput = document.querySelector('input[name="user_id"]');
        const userId = userIdSelect?.value || userIdInput?.value;
        
        if (!userId || this.existingIdsLoaded) {
            return; // Already loaded or no user selected
        }
        
        try {
            const response = await makeRequest('/admin/content/existing-ids', {
                method: 'POST',
                body: { user_id: parseInt(userId, 10) }
            });
            
            const result = response.data || response;
            if (result?.success) {
                // Convert arrays to Sets for O(1) lookup performance
                this.existingVideoIds = new Set(result.video_ids || []);
                this.existingPlaylistIds = new Set(result.playlist_ids || []);
                this.existingIdsLoaded = true;
            }
        } catch (error) {
            console.warn('Failed to load existing IDs, will skip import checking', error);
            // Continue without existing IDs - items just won't be marked as imported
        }
    }
    
    /**
     * Mark items as imported based on loaded IDs (in-memory comparison)
     * @param {Array} items - Array of items to mark
     * @returns {Array} Items with is_imported flag added
     */
    markItemsAsImported(items) {
        // Safety check: ensure items is an array
        if (!items || !Array.isArray(items)) {
            return [];
        }
        
        return items.map(item => {
            if (item.type === 'video' && item.video_id) {
                item.is_imported = this.existingVideoIds.has(item.video_id);
            } else if (item.type === 'playlist' && item.playlist_id) {
                item.is_imported = this.existingPlaylistIds.has(item.playlist_id);
            } else {
                item.is_imported = false;
            }
            return item;
        });
    }
    
    /**
     * Reset existing IDs when user changes or modal closes
     */
    resetExistingIds() {
        this.existingVideoIds.clear();
        this.existingPlaylistIds.clear();
        this.existingIdsLoaded = false;
    }
    
    /**
     * Extract and flatten response data from backend
     * Handles nested data structure from response macros
     * @param {object} response - Response object from makeRequest
     * @returns {object} Flattened result object
     */
    extractResponseData(response) {
        let result = response.data || response;
        
        // If result has a nested data property (from backend response macro), flatten it
        if (result?.success && result?.data && typeof result.data === 'object') {
            result = { ...result, ...result.data };
        }
        
        return result;
    }
    
    /**
     * Extract error message from error object
     * @param {Error} error - Error object from catch block
     * @param {string} defaultMsg - Default error message
     * @param {string} translationKey - Translation key for default message
     * @returns {string} Error message to display
     */
    extractErrorMessage(error, defaultMsg, translationKey = null) {
        let errorMsg = defaultMsg;
        
        // Try to get error message from response data
        if (error?.responseData?.message) {
            errorMsg = error.responseData.message;
        } else if (error?.response) {
            // Try to parse response text
            try {
                const parsed = JSON.parse(error.response);
                if (parsed?.message) {
                    errorMsg = parsed.message;
                }
            } catch (e) {
                // Use default message if parsing fails
            }
        } else if (error?.message) {
            // Use error message if available
            errorMsg = error.message;
        }
        
        // Apply translation if available and using default message
        if (translationKey && getTranslation && errorMsg === defaultMsg) {
            errorMsg = getTranslation(translationKey, errorMsg);
        }
        
        return errorMsg;
    }
    
    /**
     * Process API response and mark items as imported
     * @param {object} result - Response result object
     * @returns {object} Processed result with marked items
     */
    processResponseResult(result) {
        // Ensure items is an array before processing
        const items = Array.isArray(result.items) ? result.items : [];
        
        // Mark items as imported
        result.items = this.markItemsAsImported(items);
        
        return result;
    }
    
    /**
     * Prefill channel info and fetch channel content
     * Used when clicking import button from channel accordion header
     * @param {string} channelId - YouTube channel ID
     * @param {string} channelName - Channel name (optional, for display)
     */
    prefillChannelInfo(channelId, channelName = null) {
        if (!this.channelInput) return;
        
        // Set channel input to channel ID
        this.channelInput.value = channelId;
        
        // Automatically fetch channel content
        this.fetchChannel();
    }
    
    /**
     * Fetch channel content from YouTube
     * Handles initial channel lookup and content fetching
     */
    async fetchChannel() {
        const channelInput = this.channelInput?.value.trim();
        
        if (!channelInput) {
            const errorMsg = getTranslation?.('admin.channel_input_required', 'Please enter a channel URL, handle, or ID.') || 'Please enter a channel URL, handle, or ID.';
            this.showError(errorMsg);
            return;
        }
        
        // Load existing IDs before fetching (if not already loaded)
        await this.loadExistingIds();
        
        this.showState('loading');
        if (this.fetchChannelBtn) {
            this.fetchChannelBtn.disabled = true;
        }
        
        if (!makeRequest) {
            this.showError('makeRequest utility not available');
            if (this.fetchChannelBtn) {
                this.fetchChannelBtn.disabled = false;
            }
            return;
        }
        
        try {
            const response = await makeRequest('/admin/content/fetch-channel', {
                method: 'POST',
                body: {
                    channel_input: channelInput,
                    content_type: this.currentContentType,
                    page_token: null
                }
            });
            
            // Extract and process response data
            const result = this.extractResponseData(response);
            
            if (result?.success) {
                // Process and mark items as imported
                this.processResponseResult(result);
                
                this.channelInfo = result.channel_info;
                this.currentPage = 1;
                this.nextPageToken = result.next_page_token;
                this.currentItems = result.items;
                
                // Cache the first page (with imported flags)
                this.cachePageData(this.currentContentType, 1, result);
                
                // Show content type toggle
                if (this.contentTypeSection) {
                    this.contentTypeSection.style.display = 'block';
                }
                
                // Display results
                this.displayResults(result);
                this.showState('results');
            } else {
                let errorMsg = result?.message;
                if (!errorMsg && getTranslation) {
                    errorMsg = getTranslation('admin.channel_fetch_failed', 'Failed to fetch channel content.');
                } else if (!errorMsg) {
                    errorMsg = 'Failed to fetch channel content.';
                }
                this.showError(errorMsg);
            }
            if (this.fetchChannelBtn) {
                this.fetchChannelBtn.disabled = false;
            }
        } catch (error) {
            const errorMsg = this.extractErrorMessage(
                error,
                'An error occurred while fetching the channel. Please try again.',
                'admin.channel_fetch_error'
            );
            this.showError(errorMsg);
            if (this.fetchChannelBtn) {
                this.fetchChannelBtn.disabled = false;
            }
        }
    }

    async switchContentType(contentType) {
        if (contentType === this.currentContentType || !this.channelInfo) {
            return;
        }
        
        this.currentContentType = contentType;
        this.currentPage = 1;
        this.nextPageToken = null;
        // DON'T clear selectedItems - let selections persist across content types
        this.updateSelectionUI();
        
        // Check cache first
        const cached = this.getCachedPageData(contentType, 1);
        if (cached) {
            this.processResponseResult(cached);
            this.currentItems = cached.items;
            this.nextPageToken = cached.next_page_token;
            this.displayResults(cached);
            return;
        }
        
        // Fetch from API
        this.showState('loading');
        
        if (!makeRequest) {
            this.showError('makeRequest utility not available');
            return;
        }
        
        try {
            const response = await makeRequest('/admin/content/fetch-channel', {
                method: 'POST',
                body: {
                    channel_input: this.channelInfo.channel_id,
                    content_type: contentType,
                    page_token: null
                }
            });
            
            // Extract and process response data
            const result = this.extractResponseData(response);
            
            if (result?.success) {
                // Process and mark items as imported
                this.processResponseResult(result);
                
                this.currentItems = result.items;
                this.nextPageToken = result.next_page_token;
                
                // Cache the page (with imported flags)
                this.cachePageData(contentType, 1, result);
                
                this.displayResults(result);
                this.showState('results');
            } else {
                let errorMsg = result?.message;
                if (!errorMsg && getTranslation) {
                    errorMsg = getTranslation('admin.content_fetch_failed', 'Failed to fetch content.');
                } else if (!errorMsg) {
                    errorMsg = 'Failed to fetch content.';
                }
                this.showError(errorMsg);
            }
        } catch (error) {
            const errorMsg = this.extractErrorMessage(
                error,
                'An error occurred. Please try again.',
                'admin.content_fetch_error'
            );
            this.showError(errorMsg);
        }
    }

    async nextPage() {
        if (!this.nextPageToken) return;
        
        const nextPage = this.currentPage + 1;
        
        // Check cache first
        const cached = this.getCachedPageData(this.currentContentType, nextPage);
        if (cached) {
            this.processResponseResult(cached);
            this.currentPage = nextPage;
            this.currentItems = cached.items;
            this.nextPageToken = cached.next_page_token;
            this.displayResults(cached);
            return;
        }
        
        // Fetch from API
        this.showState('loading');
        
        if (!makeRequest) {
            this.showError('makeRequest utility not available');
            return;
        }
        
        try {
            const response = await makeRequest('/admin/content/fetch-channel', {
                method: 'POST',
                body: {
                    channel_input: this.channelInfo.channel_id,
                    content_type: this.currentContentType,
                    page_token: this.nextPageToken
                }
            });
            
            // Extract and process response data
            const result = this.extractResponseData(response);
            
            if (result?.success) {
                // Process and mark items as imported
                this.processResponseResult(result);
                
                this.currentPage = nextPage;
                this.currentItems = result.items;
                this.nextPageToken = result.next_page_token;
                
                // Cache the page (with imported flags)
                this.cachePageData(this.currentContentType, nextPage, result);
                
                this.displayResults(result);
                this.showState('results');
            } else {
                let errorMsg = result?.message;
                if (!errorMsg && getTranslation) {
                    errorMsg = getTranslation('admin.next_page_fetch_failed', 'Failed to fetch next page.');
                } else if (!errorMsg) {
                    errorMsg = 'Failed to fetch next page.';
                }
                this.showError(errorMsg);
            }
        } catch (error) {
            const errorMsg = this.extractErrorMessage(
                error,
                'An error occurred. Please try again.',
                'admin.content_fetch_error'
            );
            this.showError(errorMsg);
        }
    }
    
    previousPage() {
        if (this.currentPage <= 1) return;
        
        const prevPage = this.currentPage - 1;
        
        // Get from cache
        const cached = this.getCachedPageData(this.currentContentType, prevPage);
        if (cached) {
            this.processResponseResult(cached);
            this.currentPage = prevPage;
            this.currentItems = cached.items;
            this.nextPageToken = cached.next_page_token;
            this.displayResults(cached);
        }
    }
    
    displayResults(result) {
        // Ensure result has required properties with defaults
        const totalResults = result?.total_results ?? 0;
        const items = Array.isArray(result?.items) ? result.items : [];
        
        // Update channel info
        if (this.channelTitle) {
            this.channelTitle.textContent = this.channelInfo?.title || '';
        }
        if (this.channelStats) {
            this.channelStats.textContent = `(${totalResults} ${this.currentContentType})`;
        }
        
        // Store total results for import all button
        this.totalResults = totalResults;
        
        // Show import all button if there are results
        if (totalResults > 0 && this.importAllBtn) {
            this.importAllBtn.style.display = 'inline-block';
            if (this.importAllBtnCount) {
                this.importAllBtnCount.textContent = totalResults;
            }
        }
        
        // Clear and populate items grid
        if (this.channelItemsGrid) {
            this.channelItemsGrid.innerHTML = '';
            
            for (const item of items) {
                const itemCard = this.createItemCard(item);
                this.channelItemsGrid.appendChild(itemCard);
            }
        }
        
        // Update pagination
        this.updatePagination({ ...result, total_results: totalResults });
        
        // Update selection UI
        this.updateSelectionUI();
    }
    
    createItemCard(item) {
        const col = document.createElement('div');
        col.className = 'col-6 col-sm-4 col-md-3 col-lg-2';
        
        const itemKey = item.video_id || item.playlist_id;
        const isSelected = itemKey in this.selectedItems;
        const isImported = item.is_imported === true; // Check imported flag
        
        const card = document.createElement('div');
        let cardClasses = 'card h-100';
        if (isImported) {
            cardClasses += ' opacity-75'; // Visual indication
            cardClasses += ' border-success'; // Success border for imported items (priority)
        } else if (isSelected) {
            cardClasses += ' border-success'; // Success border for selected items
        }
        card.className = cardClasses;
        card.style.cursor = isImported ? 'not-allowed' : 'pointer';
        
        const itemId = item.video_id || item.playlist_id;
        const itemType = item.type;
        
        const escapedTitle = escapeHtml?.(item.title) || item.title;
        let playlistBadge = '';
        if (itemType === 'playlist') {
            const videoCount = item.video_count || 0;
            playlistBadge = `<div class="position-absolute bottom-0 start-0 p-1">
                <span class="badge bg-dark">
                <i class="bi bi-collection-play"></i> ${videoCount}
                </span>
                </div>`;
        }
        let durationHtml = '';
        if (item.duration && formatDuration) {
            durationHtml = `<p class="card-text small text-muted mb-0 mt-1">${formatDuration(item.duration)}</p>`;
        }
        
        // Imported badge
        const importedBadge = isImported ? '<div class="position-absolute top-0 start-0 p-1"><span class="badge bg-success">Imported</span></div>' : '';
        
        card.innerHTML = `<div class="position-relative">
            <img src="${item.thumbnail_url}" class="card-img-top" alt="${escapedTitle}" 
                 style="${isImported ? 'filter: grayscale(50%);' : ''}">
            <div class="position-absolute top-0 end-0 p-2">
            <input type="checkbox" class="form-check-input" ${isSelected ? 'checked' : ''} 
            ${isImported ? 'disabled' : ''} 
            data-item-id="${itemId}" data-item-type="${itemType}">
            </div>
            ${importedBadge}
            ${playlistBadge}
            </div>
            <div class="card-body p-2">
            <p class="card-text small text-truncate mb-0" title="${escapedTitle}">
            ${escapedTitle}
            </p>
            ${durationHtml}
            </div>`;
        
        // Only allow selection if not imported
        if (!isImported) {
            // Click handler for card (toggle selection)
            card.addEventListener('click', (e) => {
                if (e.target.type !== 'checkbox') {
                    const checkbox = card.querySelector('input[type="checkbox"]');
                    checkbox.checked = !checkbox.checked;
                    checkbox.dispatchEvent(new Event('change'));
                }
            });
            
            // Checkbox change handler
            const checkbox = card.querySelector('input[type="checkbox"]');
            checkbox.addEventListener('change', (e) => {
                e.stopPropagation();
                const videoCount = itemType === 'playlist' ? (item.video_count || 0) : 1;
                this.toggleItemSelection(itemId, itemType, item.title, e.target.checked, videoCount);
                
                // Update card border
                if (e.target.checked) {
                    card.classList.add('border-success');
                } else {
                    card.classList.remove('border-success');
                }
            });
        }
        
        col.appendChild(card);
        return col;
    }

    toggleItemSelection(itemId, itemType, itemTitle, selected, videoCount = 0) {
        if (selected) {
            this.selectedItems[itemId] = { 
                type: itemType, 
                id: itemId, 
                title: itemTitle,
                videoCount: itemType === 'playlist' ? videoCount : 1
            };
        } else {
            delete this.selectedItems[itemId];
        }
        
        this.updateSelectionUI();
    }

    toggleSelectAll(checked) {
        for (const item of this.currentItems) {
            // Skip if already imported
            if (item.is_imported === true) {
                continue;
            }
            
            const itemId = item.video_id || item.playlist_id;
            const itemType = item.type;
            const videoCount = itemType === 'playlist' ? (item.video_count || 0) : 1;
            
            if (checked) {
                this.selectedItems[itemId] = { 
                    type: itemType, 
                    id: itemId, 
                    title: item.title,
                    videoCount: videoCount
                };
            } else {
                delete this.selectedItems[itemId];
            }
        }
        
        // Update all checkboxes (only non-disabled ones)
        const checkboxes = this.channelItemsGrid?.querySelectorAll('input[type="checkbox"]:not(:disabled)');
        if (checkboxes) {
            for (const cb of checkboxes) {
                cb.checked = checked;
                const card = cb.closest('.card');
                if (card) {
                    if (checked) {
                        card.classList.add('border-success');
                    } else {
                        card.classList.remove('border-success');
                    }
                }
            }
        }
        
        this.updateSelectionUI();
    }

    updateSelectionUI() {
        // Count selected items
        const count = Object.keys(this.selectedItems).length;
        
        // Calculate total video count (including videos in playlists)
        let totalVideoCount = 0;
        for (const key in this.selectedItems) {
            totalVideoCount += this.selectedItems[key]?.videoCount || 1;
        }
        
        if (this.selectedItemsCount) {
            this.selectedItemsCount.textContent = count;
        }
        if (this.importSelectedBtn) {
            this.importSelectedBtn.disabled = count === 0;
        }
        
        // Update import selected button counter with total video count
        if (this.importSelectedBtnCount) {
            if (count > 0) {
                this.importSelectedBtnCount.textContent = totalVideoCount;
                this.importSelectedBtnCount.style.display = 'inline-block';
            } else {
                this.importSelectedBtnCount.style.display = 'none';
            }
        }
        
        // Update "select all" checkbox state (only count non-imported items)
        let allSelected = false;
        const selectableItems = this.currentItems.filter(item => item.is_imported !== true);
        if (selectableItems.length > 0) {
            allSelected = true;
            for (const item of selectableItems) {
                const itemId = item.video_id || item.playlist_id;
                if (!(itemId in this.selectedItems)) {
                    allSelected = false;
                    break;
                }
            }
        }
        
        if (this.selectAllItems) {
            this.selectAllItems.checked = allSelected;
        }
    }

    updatePagination(result) {
        const totalResults = result?.total_results ?? 0;
        const nextPageToken = result?.next_page_token;
        
        // Hide/show previous button
        if (this.prevPageBtn) {
            if (this.currentPage <= 1) {
                this.prevPageBtn.style.display = 'none';
            } else {
                this.prevPageBtn.style.display = 'inline-block';
                this.prevPageBtn.disabled = false;
            }
        }
        
        // Hide/show next button
        if (this.nextPageBtn) {
            if (!nextPageToken) {
                this.nextPageBtn.style.display = 'none';
            } else {
                this.nextPageBtn.style.display = 'inline-block';
                this.nextPageBtn.disabled = false;
            }
        }
        
        if (this.paginationInfo) {
            const start = (this.currentPage - 1) * 50 + 1;
            const end = Math.min(this.currentPage * 50, totalResults);
            this.paginationInfo.textContent = `${start}-${end} of ${totalResults}`;
        }
    }

    async importSelected(importAll = false) {
        // Prevent duplicate requests - if already importing, ignore
        if (this.isImporting) {
            return;
        }
        this.isImporting = true;
        
        // Check if we have selections or import all is true
        if (!importAll && Object.keys(this.selectedItems).length === 0) {
            this.isImporting = false;
            return;
        }
        
        const userIdSelect = document.querySelector('select[name="user_id"]');
        const userIdInput = document.querySelector('input[name="user_id"]');
        const userId = userIdSelect?.value || userIdInput?.value;
        
        if (!userId) {
            const errorMsg = getTranslation?.('admin.user_id_not_found', 'User ID not found. Please refresh the page and try again.') || 'User ID not found. Please refresh the page and try again.';
            if (showToast) {
                showToast(errorMsg, 'error', 5000);
            } else {
                alert(errorMsg);
            }
            this.isImporting = false;
            return;
        }
        
        // Confirm bulk import
        if (importAll) {
            const confirmMsg = `This will import all ${this.totalResults} items from the channel. This may take a while and use API quota. Continue?`;
            if (!confirm(confirmMsg)) {
                this.isImporting = false;
                return;
            }
        }
        
        // Disable both buttons during import
        if (this.importSelectedBtn) {
            this.importSelectedBtn.disabled = true;
        }
        if (this.importAllBtn) {
            this.importAllBtn.disabled = true;
        }
        
        const originalSelectedBtnHtml = this.importSelectedBtn?.innerHTML;
        const originalAllBtnHtml = this.importAllBtn?.innerHTML;
        
        if (importAll && this.importAllBtn) {
            this.importAllBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Importing...';
        } else if (this.importSelectedBtn) {
            this.importSelectedBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Importing...';
        }
        
        if (!makeRequest) {
            const errorMsg = 'makeRequest utility not available';
            if (showToast) {
                showToast(errorMsg, 'error', 5000);
            } else {
                alert(errorMsg);
            }
            if (this.importSelectedBtn) {
                this.importSelectedBtn.disabled = false;
                this.importSelectedBtn.innerHTML = originalSelectedBtnHtml || '';
            }
            if (this.importAllBtn) {
                this.importAllBtn.disabled = false;
                this.importAllBtn.innerHTML = originalAllBtnHtml || '';
            }
            this.isImporting = false;
            return;
        }
        
        const requestData = {
            user_id: parseIntSafe?.(userId, 0) || parseInt(userId, 10) || 0,
            import_all: importAll
        };
        
        if (importAll) {
            // Send channel info for bulk import
            requestData.channel_id = this.channelInfo?.channel_id;
            requestData.uploads_playlist_id = this.channelInfo?.uploads_playlist_id;
            requestData.content_type = this.currentContentType;
        } else {
            // Send selected items
            requestData.items = Object.values(this.selectedItems);
        }
        
        try {
            const response = await makeRequest('/admin/content/import-channel', {
                method: 'POST',
                body: requestData
            });
            
            // Extract and process response data
            const result = this.extractResponseData(response);
            if (result?.success) {
                const successMsg = result.message || `Successfully imported ${result.added_count} items.`;
                
                // Show success toast
                if (showToast) {
                    showToast(successMsg, 'success', 3000);
                } else {
                    alert(successMsg);
                }
                
                // Refresh existing IDs so next fetch shows updated status
                this.resetExistingIds();
                await this.loadExistingIds();
                
                // Close modal and reload page after a short delay to show toast
                setTimeout(() => {
                    if (typeof window !== 'undefined' && window.bootstrap?.Modal) {
                        const modalInstance = window.bootstrap.Modal.getInstance(this.modal);
                        if (modalInstance) {
                            modalInstance.hide();
                        }
                    }
                    window.location.reload();
                }, 500);
            } else {
                let errorMsg = result?.message;
                if (!errorMsg && getTranslation) {
                    errorMsg = getTranslation('admin.import_failed', 'Failed to import items.');
                } else if (!errorMsg) {
                    errorMsg = 'Failed to import items.';
                }
                if (showToast) {
                    showToast(errorMsg, 'error', 5000);
                } else {
                    alert(errorMsg);
                }
                if (this.importSelectedBtn) {
                    this.importSelectedBtn.disabled = false;
                    this.importSelectedBtn.innerHTML = originalSelectedBtnHtml || '';
                }
                if (this.importAllBtn) {
                    this.importAllBtn.disabled = false;
                    this.importAllBtn.innerHTML = originalAllBtnHtml || '';
                }
                this.isImporting = false;
            }
        } catch (error) {
            const errorMsg = this.extractErrorMessage(
                error,
                'An error occurred while importing. Please try again.',
                'admin.import_error'
            );
            
            if (showToast) {
                showToast(errorMsg, 'error', 5000);
            } else {
                alert(errorMsg);
            }
            
            // Reset button states
            if (this.importSelectedBtn) {
                this.importSelectedBtn.disabled = false;
                this.importSelectedBtn.innerHTML = originalSelectedBtnHtml || '';
            }
            if (this.importAllBtn) {
                this.importAllBtn.disabled = false;
                this.importAllBtn.innerHTML = originalAllBtnHtml || '';
            }
            this.isImporting = false;
        }
    }

    resetModal() {
        if (this.channelInput) {
            this.channelInput.value = '';
        }
        this.channelInfo = null;
        this.currentContentType = 'uploads';
        this.currentPage = 1;
        this.nextPageToken = null;
        // Clear selected items
        this.selectedItems = {};
        this.currentItems = [];
        // Clear cache
        this.sessionCache = {};
        this.totalResults = 0;
        
        // Reset existing IDs when modal closes
        this.resetExistingIds();
        
        if (this.contentTypeSection) {
            this.contentTypeSection.style.display = 'none';
        }
        if (this.contentTypeUploads) {
            this.contentTypeUploads.checked = true;
        }
        if (this.selectAllItems) {
            this.selectAllItems.checked = false;
        }
        if (this.importSelectedBtn) {
            this.importSelectedBtn.disabled = true;
            this.importSelectedBtn.innerHTML = '<i class="bi bi-download me-1"></i> Import selected <span id="importSelectedBtnCount" class="badge bg-light text-success ms-1" style="display: none;"></span>';
        }
        if (this.importSelectedBtnCount) {
            this.importSelectedBtnCount.style.display = 'none';
        }
        
        // Reset import all button
        if (this.importAllBtn) {
            this.importAllBtn.style.display = 'none';
            this.importAllBtn.disabled = false;
        }
        
        this.showState('input');
    }
    
    // Cache helpers (session storage)
    cachePageData(contentType, page, data) {
        const key = `${contentType}_page_${page}`;
        this.sessionCache[key] = data;
    }
    
    getCachedPageData(contentType, page) {
        const key = `${contentType}_page_${page}`;
        return this.sessionCache[key];
    }
}

// Create and export singleton instance
// Initialize after DOM is ready to ensure modal elements exist
let channelImporter = null;

function initializeChannelImporter() {
    if (channelImporter) {
        // Re-initialize elements and re-attach listeners in case modal was added dynamically
        channelImporter.initializeElements();
        channelImporter.attachEventListeners();
        return;
    }
    
    channelImporter = new ChannelImporter();
    
    // Make instance available globally for accordion import buttons
    if (typeof window !== 'undefined') {
        window.channelImporter = channelImporter;
        
        // Export to Traktor namespace for backward compatibility
        if (!window.Traktor) {
            window.Traktor = {};
        }
        if (!window.Traktor.Admin) {
            window.Traktor.Admin = {};
        }
        window.Traktor.Admin.channelImport = {
            channelImporter: channelImporter
        };
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeChannelImporter);
} else {
    // DOM already ready, initialize immediately
    initializeChannelImporter();
}

// Also re-initialize when modal is shown (in case elements weren't found initially)
// Use a small delay to ensure modal is fully rendered
const modal = document.getElementById('channelImportModal');
if (modal) {
    modal.addEventListener('shown.bs.modal', () => {
        if (channelImporter) {
            // Small delay to ensure all elements are rendered
            setTimeout(() => {
                channelImporter.initializeElements();
                channelImporter.attachEventListeners();
            }, 50);
        }
    });
}

export { ChannelImporter, channelImporter };
