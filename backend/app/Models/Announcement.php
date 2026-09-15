<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Announcement extends Model
{
    protected $fillable = ['title', 'body', 'audience_type', 'status', 'created_by', 'scheduled_at', 'sent_at'];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'sent_at' => 'datetime'];
    }

    public function channels(): HasMany
    {
        return $this->hasMany(AnnouncementChannel::class);
    }
}
