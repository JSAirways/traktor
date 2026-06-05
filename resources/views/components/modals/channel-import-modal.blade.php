{{--
    Channel Import Modal Component
    
    Modal for importing videos and playlists from a YouTube channel.
    Allows users to search for a channel, browse uploads/playlists, and select items to import.
--}}

<div class="modal fade" id="channelImportModal" tabindex="-1" aria-labelledby="channelImportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="channelImportModalLabel">{{ __('admin.import_from_channel') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('common.close') }}"></button>
            </div>
            <div class="modal-body">
                <!-- Channel Input Section -->
                <div id="channelInputSection">
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="channelInput">{{ __('admin.channel_url_or_name') }}</label>
                        <div class="input-group">
                            <input type="text" 
                                class="form-control" 
                                id="channelInput" 
                                placeholder="{{ __('admin.channel_input_help') }}"
                                aria-describedby="channelInputHelp">
                            <button class="btn btn-success input-group-text" type="button" id="fetchChannelBtn">
                                <i class="bi bi-search me-1"></i>
                                {{ __('admin.fetch') }}
                            </button>
                        </div>
                    </div>

                    <!-- Content Type Toggle -->
                    <div class="mb-3" id="contentTypeSection" style="display: none;">
                        <label class="form-label fw-bold">{{ __('admin.content_type') }}</label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="contentType" id="contentTypeUploads" value="uploads" checked>
                            <label class="btn btn-outline-success" for="contentTypeUploads">
                                <i class="bi bi-play-circle me-1"></i>
                                {{ __('admin.uploads') }}
                            </label>
                            
                            <input type="radio" class="btn-check" name="contentType" id="contentTypePlaylists" value="playlists">
                            <label class="btn btn-outline-success" for="contentTypePlaylists">
                                <i class="bi bi-collection-play me-1"></i>
                                {{ __('admin.playlists') }}
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Loading State -->
                <div id="channelLoadingState" style="display: none;" class="text-center py-5">
                    <div class="spinner-border text-success" role="status">
                        <span class="visually-hidden">{{ __('common.loading') }}</span>
                    </div>
                    <p class="mt-3 text-muted">{{ __('admin.fetching_channel_content') }}</p>
                </div>

                <!-- Error State -->
                <div id="channelErrorState" style="display: none;" class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <span id="channelErrorMessage"></span>
                </div>

                <!-- Results Section -->
                <div id="channelResultsSection" style="display: none;">
                    <!-- Channel Info -->
                    <p class="fw-bold mb-3">
                        <span id="channelTitle"></span>
                        <span id="channelStats" class="ms-2 text-muted fw-normal"></span>
                    </p>

                    <!-- Selection Controls -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="selectAllItems">
                            <label class="form-check-label" for="selectAllItems">
                                {{ __('admin.select_all_on_page') }}
                            </label>
                        </div>
                        <span id="selectionCount" class="text-muted">
                            <span id="selectedItemsCount">0</span> {{ __('admin.items_selected') }}
                        </span>
                    </div>

                    <!-- Items Grid -->
                    <div id="channelItemsGrid" class="row g-3 mb-3">
                        <!-- Items will be dynamically inserted here -->
                    </div>

                    <!-- Pagination -->
                    <div id="channelPagination" class="d-flex justify-content-between align-items-center">
                        <button type="button" class="btn btn-outline-success" id="prevPageBtn" disabled>
                            <i class="bi bi-chevron-left"></i>
                            {{ __('common.previous') }}
                        </button>
                        <span id="paginationInfo" class="text-muted"></span>
                        <button type="button" class="btn btn-outline-success" id="nextPageBtn" disabled>
                            {{ __('common.next') }}
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-warning" id="importAllBtn" style="display: none;">
                    <i class="bi bi-cloud-download me-1"></i>
                    {{ __('admin.import_all') }}
                    <span id="importAllBtnCount" class="badge bg-light text-dark ms-1"></span>
                </button>
                <button type="button" class="btn btn-success" id="importSelectedBtn" disabled>
                    <i class="bi bi-download me-1"></i>
                    {{ __('admin.import_selected') }}
                    <span id="importSelectedBtnCount" class="badge bg-light text-success ms-1" style="display: none;"></span>
                </button>
            </div>
        </div>
    </div>
</div>


