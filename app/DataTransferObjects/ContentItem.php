<?php

namespace App\DataTransferObjects;

use Illuminate\Database\Eloquent\Model;

class ContentItem
{
    public string $type;
    public int $id;
    public ?int $playlist_id;
    public string $title;
    public ?int $duration;
    public string $thumbnail_url;
    public int $display_order;
    public bool $is_visible;
    public int $user_id;
    public ?int $video_count;
    public ?Model $model;
    public ?string $channel_id;
    public ?string $channel_name;
    public ?string $channel_thumbnail;

    public function __construct(
        string $type,
        int $id,
        string $title,
        ?int $duration,
        string $thumbnail_url,
        int $display_order,
        bool $is_visible,
        int $user_id,
        ?int $playlist_id = null,
        ?int $video_count = null,
        ?Model $model = null,
        ?string $channel_id = null,
        ?string $channel_name = null,
        ?string $channel_thumbnail = null
    ) {
        $this->type = $type;
        $this->id = $id;
        $this->title = $title;
        $this->duration = $duration;
        $this->thumbnail_url = $thumbnail_url;
        $this->display_order = $display_order;
        $this->is_visible = $is_visible;
        $this->user_id = $user_id;
        $this->playlist_id = $playlist_id;
        $this->video_count = $video_count;
        $this->model = $model;
        $this->channel_id = $channel_id;
        $this->channel_name = $channel_name;
        $this->channel_thumbnail = $channel_thumbnail;
    }

    public function isPlaylist(): bool
    {
        return $this->type === 'playlist';
    }

    public function isVideo(): bool
    {
        return $this->type === 'video';
    }
}


