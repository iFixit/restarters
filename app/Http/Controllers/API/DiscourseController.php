<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Services\DiscourseService;
use App\Models\User;
use Auth;
use Illuminate\Http\Request;
use Cache;

class DiscourseController extends Controller
{
    /**
     * Get top Talk topics.
     */
    public function discussionTopics(Request $request, DiscourseService $discourseService, string $tag = NULL): JsonResponse
    {
        $topics = [];

        if (!config('restarters.features.discourse_integration')) {
            return response()->json([
                'error' => 'Discourse integration is not enabled'
            ], 400);
        }

        $key = $tag ? "discourse_topics_$tag" : 'discourse_topics';

        if (Cache::has($key)) {
            $topics = Cache::get($key);
        } else {
            $topics = $discourseService->getDiscussionTopics($tag);
            Cache::put($key, $topics, 60);
        }

        return response()->json([
            'success' => 'success',
            'topics' => $topics
        ], 200);
    }
}
