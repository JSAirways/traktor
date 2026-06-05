@props([
    'channel' => null, // Channel object
    'channelContent' => null, // Collection of content items for this channel
    'playlistVideos' => [], // Playlist videos indexed by playlist ID
    'channelIndex' => 0, // Index for accordion (0-based)
    'isAccordion' => true, // Whether to render as accordion item
    'selectedUser' => null, // Selected user for toggle functionality
    'selectedUserId' => null, // Selected user ID
    'hiddenChannels' => [], // Array of hidden channel IDs
])

@if(($channelContent->count() ?? 0) > 0)
    @if(!$isAccordion)
        {{-- "All Content" section (header with toggle only) --}}
        <div class="mb-4 p-3 border rounded">
            <div class="d-flex align-items-center justify-content-between gap-2 gap-md-3">
                <x-admin.content.channel-header :channel="$channel" />

                <x-ui.form-toggle
                    :action="route('admin.content.toggle-all-content-section')"
                    :checked="$selectedUser && ($selectedUser->show_all_content_section ?? false)"
                    :title="__('admin.show_all_content_section')"
                    :hidden="['user_id' => $selectedUserId]"
                />
            </div>
        </div>
    @else
        {{-- Regular channel accordion item --}}
        <div class="accordion-item" data-channel-id="{{ $channel->id }}" data-channel-order="{{ $channelIndex }}">
            <h2 class="accordion-header" id="channelHeading{{ $channelIndex }}">
                {{-- Accordion button as div (allows real buttons inside) --}}
                <div class="accordion-button collapsed d-flex align-items-center w-100 pe-3"
                     data-bs-toggle="collapse"
                     data-bs-target="#channelCollapse{{ $channelIndex }}"
                     aria-expanded="false{{ $channelIndex === 0 ? 'true' : 'false' }}"
                     aria-controls="channelCollapse{{ $channelIndex }}"
                     role="button"
                     tabindex="0">
                    {{-- Channel content --}}
                    <x-admin.content.channel-header
                        :channel="$channel"
                        :showDragHandle="true"
                    />

                    {{-- Action buttons (now can be real buttons inside div) --}}
                    <div class="d-flex align-items-center flex-shrink-0 ms-auto" onclick="event.stopPropagation();">
                        <x-ui.form-toggle
                            :action="route('admin.content.toggle-channel-visibility')"
                            :checked="!in_array($channel->id, $hiddenChannels ?? [])"
                            :title="__('admin.show_channel_in_frontend')"
                            :hidden="['user_id' => $selectedUserId, 'channel_id' => $channel->id]"
                        />

                        <x-ui.form-action-button
                            :action="route('admin.content.delete-channel')"
                            :confirm="__('admin.confirm_delete_channel')"
                            icon="bi bi-trash"
                            variant="outline-danger"
                            :title="__('common.delete')"
                            :hidden="['user_id' => $selectedUserId, 'channel_id' => $channel->id]"
                        />
                    </div>
                </div>
            </h2>
            <div id="channelCollapse{{ $channelIndex }}"
                 class="accordion-collapse collapse"
                 aria-labelledby="channelHeading{{ $channelIndex }}"
                 data-bs-parent="#channelsAccordion">
                <div class="accordion-body p-0">
                    <div class="p-3 border-bottom">
                        <button type="button" class="btn btn-success btn-sm channel-import-btn"
                                data-channel-id="{{ $channel->id }}"
                                data-channel-name="{{ $channel->name }}"
                                data-bs-toggle="modal"
                                data-bs-target="#channelImportModal">
                            <i class="bi bi-plus me-1"></i>
                            {{ __('admin.import_from_channel') }}
                        </button>
                    </div>

                    {{-- Channel-scoped bulk actions toolbar --}}
                    <x-admin.content.bulk-actions-toolbar :channelId="$channel->id" :hasContent="($channelContent->count() ?? 0) > 0" />

                    <div class="table-responsive">
                        <table class="table table-striped mb-0" data-channel-id="{{ $channel->id }}">
                            <x-admin.content.content-table-header :channelId="$channel->id" />
                            <tbody data-channel-id="{{ $channel->id }}">
                                @foreach($channelContent as $item)
                                    <x-admin.content.content-row
                                        :item="$item"
                                        :channelId="$channel->id"
                                        :playlistVideos="$playlistVideos"
                                    />
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endif

