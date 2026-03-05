<?php

namespace App\Http\Resources;

use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Report */
class ReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $requestIpHash = hash('sha256', (string) $request->ip());
        $requestBrowserId = (string) $request->cookie('browser_id', '');
        $isOwnReport = $this->belongsToReporter($requestIpHash, $requestBrowserId);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'confirmations_count' => $this->confirmations_count,
            'clear_votes_count' => $this->clear_votes_count,
            'can_confirm' => ! $isOwnReport && $this->status->value !== 'resolved',
            'created_at' => $this->created_at?->toIso8601String(),
            'category' => $this->whenLoaded('category', function (): ?array {
                if (! $this->category) {
                    return null;
                }

                return [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                    'color' => $this->category->color,
                ];
            }),
        ];
    }
}
