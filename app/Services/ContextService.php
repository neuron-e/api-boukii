<?php

namespace App\Services;

use App\Models\Season as LegacySeason;
use App\Models\User;
use App\V5\Models\Season as V5Season;

class ContextService
{
    /**
     * Get the current context (school_id, season_id) for the given user.
     * Uses token.context_data as source of truth; falls back to token.meta['context'] for backward compatibility.
     */
    public function get(User $user): array
    {
        $context = [
            'school_id' => null,
            'season_id' => null,
        ];

        $token = $user->currentAccessToken();

        if ($token) {
            // Preferred: context_data JSON column
            $cd = $token->context_data ?? null;
            if (is_string($cd)) {
                $cd = json_decode($cd, true) ?? [];
            }
            if (is_array($cd)) {
                $context['school_id'] = $cd['school_id'] ?? $context['school_id'];
                $context['season_id'] = array_key_exists('season_id', $cd) ? $cd['season_id'] : $context['season_id'];
            }

            // Back-compat: meta['context']
            if ($context['school_id'] === null || $context['season_id'] === null) {
                $meta = $token->meta ?? [];
                if (is_string($meta)) {
                    $meta = json_decode($meta, true) ?? [];
                }
                if (is_array($meta)) {
                    $saved = $meta['context'] ?? [];
                    $context['school_id'] = $context['school_id'] ?? ($saved['school_id'] ?? null);
                    if ($context['season_id'] === null && array_key_exists('season_id', $saved)) {
                        $context['season_id'] = $saved['season_id'];
                    }
                }
            }
        }

        return $context;
    }

    /**
     * Set the current school for the user, resetting the season.
     */
    public function setSchool(User $user, int $schoolId): array
    {
        $context = [
            'school_id' => $schoolId,
            'season_id' => null,
        ];

        $token = $user->currentAccessToken();

        if ($token) {
            // Persist in context_data (preferred)
            $token->context_data = $context;

            // Back-compat: also mirror in meta['context'] if meta exists
            $meta = $token->meta;
            if (is_string($meta)) {
                $meta = json_decode($meta, true) ?? [];
            }
            if (! is_array($meta)) {
                $meta = [];
            }
            $meta['context'] = $context;
            $token->meta = $meta;

            $token->save();
        }

        return $context;
    }

    /**
     * Set the current season for the user. If the provided season belongs to a different
     * school than the current context, this will also update the school context accordingly.
     */
    public function setSeason(User $user, int $seasonId): array
    {
        $token = $user->currentAccessToken();
        $season = V5Season::find($seasonId) ?? LegacySeason::find($seasonId);

        $context = $this->get($user);
        $context['season_id'] = $season ? (int) $season->id : $seasonId;
        if ($season && (! $context['school_id'] || (int) $context['school_id'] !== (int) $season->school_id)) {
            $context['school_id'] = (int) $season->school_id;
        }

        if ($token) {
            // Persist in context_data (preferred)
            $token->context_data = $context;

            // Back-compat: also mirror in meta['context']
            $meta = $token->meta;
            if (is_string($meta)) {
                $meta = json_decode($meta, true) ?? [];
            }
            if (! is_array($meta)) {
                $meta = [];
            }
            $meta['context'] = $context;
            $token->meta = $meta;

            $token->save();
        }

        return $context;
    }
}
