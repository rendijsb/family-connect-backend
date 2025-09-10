<?php

namespace App\Http\Controllers\Api\Memories;

use App\Http\Controllers\Controller;
use App\Http\Requests\Memories\CreateTimeCapsuleRequest;
use App\Http\Requests\Memories\UpdateTimeCapsuleRequest;
use App\Http\Requests\Memories\CreateTimeCapsuleCommentRequest;
use App\Http\Requests\Memories\CreateTimeCapsuleContributionRequest;
use App\Http\Resources\Memories\TimeCapsuleResource;
use App\Http\Resources\Memories\TimeCapsuleCollection;
use App\Http\Resources\Memories\TimeCapsuleCommentResource;
use App\Http\Resources\Memories\TimeCapsuleCommentCollection;
use App\Http\Resources\Memories\TimeCapsuleContributionResource;
use App\Http\Resources\Memories\TimeCapsuleContributionCollection;
use App\Models\Family;
use App\Models\Memories\TimeCapsule;
use App\Models\Memories\TimeCapsuleComment;
use App\Models\Memories\TimeCapsuleLike;
use App\Models\Memories\TimeCapsuleView;
use App\Models\Memories\TimeCapsuleContribution;
use App\Services\Memories\TimeCapsuleService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TimeCapsuleController extends Controller
{
    protected TimeCapsuleService $timeCapsuleService;

    public function __construct(TimeCapsuleService $timeCapsuleService)
    {
        $this->timeCapsuleService = $timeCapsuleService;
    }

    /**
     * Get paginated time capsules for a family
     */
    public function index(Request $request, string $familySlug): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = TimeCapsule::where('family_id', $family->id)
            ->with(['creator', 'contributors', 'media'])
            ->withCount(['likes', 'comments', 'views', 'contributions']);

        // Apply filters
        if ($request->has('status')) {
            switch ($request->status) {
                case 'ready':
                    $query->where('open_date', '<=', now())
                          ->where('is_opened', false);
                    break;
                case 'opened':
                    $query->where('is_opened', true);
                    break;
                case 'sealed':
                    $query->where('open_date', '>', now())
                          ->where('is_opened', false);
                    break;
            }
        }

        if ($request->has('year') && $request->year !== 'all') {
            $query->whereYear('open_date', $request->year);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'open_date');
        $sortOrder = $request->get('sort_order', 'asc');
        
        switch ($sortBy) {
            case 'contributions':
                $query->orderBy('contributions_count', $sortOrder);
                break;
            case 'likes':
                $query->orderBy('likes_count', $sortOrder);
                break;
            case 'created':
                $query->orderBy('created_at', $sortOrder);
                break;
            default:
                $query->orderBy('open_date', $sortOrder);
        }

        $timeCapsules = $query->paginate($request->get('per_page', 20));

        return response()->json(new TimeCapsuleCollection($timeCapsules));
    }

    /**
     * Get a specific time capsule
     */
    public function show(string $familySlug, string $capsuleId): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $timeCapsule = TimeCapsule::where('id', $capsuleId)
            ->where('family_id', $family->id)
            ->with(['creator', 'contributors', 'media'])
            ->withCount(['likes', 'comments', 'views', 'contributions'])
            ->firstOrFail();

        // Check if user liked this time capsule
        $timeCapsule->is_liked_by_current_user = $timeCapsule->likes()
            ->where('user_id', Auth::id())
            ->exists();

        return response()->json(new TimeCapsuleResource($timeCapsule));
    }

    /**
     * Create a new time capsule
     */
    public function store(CreateTimeCapsuleRequest $request, string $familySlug): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            DB::beginTransaction();

            $timeCapsule = $this->timeCapsuleService->createTimeCapsule($family, $request->validated());

            DB::commit();

            $timeCapsule->load(['creator', 'contributors', 'media']);
            $timeCapsule->loadCount(['likes', 'comments', 'views', 'contributions']);

            return response()->json(new TimeCapsuleResource($timeCapsule), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create time capsule'], 500);
        }
    }

    /**
     * Update a time capsule
     */
    public function update(UpdateTimeCapsuleRequest $request, string $familySlug, string $capsuleId): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $timeCapsule = TimeCapsule::where('id', $capsuleId)
            ->where('family_id', $family->id)
            ->firstOrFail();

        // Check permissions - only allow editing if not opened
        if ($timeCapsule->is_opened || !$this->timeCapsuleService->canEdit($timeCapsule, Auth::user())) {
            return response()->json(['message' => 'Cannot edit this time capsule'], 403);
        }

        try {
            DB::beginTransaction();

            $timeCapsule = $this->timeCapsuleService->updateTimeCapsule($timeCapsule, $request->validated());

            DB::commit();

            $timeCapsule->load(['creator', 'contributors', 'media']);
            $timeCapsule->loadCount(['likes', 'comments', 'views', 'contributions']);

            return response()->json(new TimeCapsuleResource($timeCapsule));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update time capsule'], 500);
        }
    }

    /**
     * Delete a time capsule
     */
    public function destroy(string $familySlug, string $capsuleId): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $timeCapsule = TimeCapsule::where('id', $capsuleId)
            ->where('family_id', $family->id)
            ->firstOrFail();

        // Check permissions
        if (!$this->timeCapsuleService->canDelete($timeCapsule, Auth::user())) {
            return response()->json(['message' => 'Cannot delete this time capsule'], 403);
        }

        try {
            $this->timeCapsuleService->deleteTimeCapsule($timeCapsule);
            return response()->json(['message' => 'Time capsule deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete time capsule'], 500);
        }
    }

    /**
     * Toggle like on a time capsule
     */
    public function toggleLike(string $familySlug, string $capsuleId): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $timeCapsule = TimeCapsule::where('id', $capsuleId)
            ->where('family_id', $family->id)
            ->firstOrFail();

        $like = TimeCapsuleLike::where('time_capsule_id', $timeCapsule->id)
            ->where('user_id', Auth::id())
            ->first();

        if ($like) {
            $like->delete();
            $liked = false;
        } else {
            TimeCapsuleLike::create([
                'time_capsule_id' => $timeCapsule->id,
                'user_id' => Auth::id()
            ]);
            $liked = true;
        }

        $likesCount = $timeCapsule->likes()->count();

        return response()->json([
            'liked' => $liked,
            'likes_count' => $likesCount
        ]);
    }

    /**
     * Open a time capsule
     */
    public function open(string $familySlug, string $capsuleId): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        $member = $family->members()->where('user_id', Auth::id())->first();
        if (!$member) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $timeCapsule = TimeCapsule::where('id', $capsuleId)
            ->where('family_id', $family->id)
            ->firstOrFail();

        // Check if ready to open
        if ($timeCapsule->is_opened) {
            return response()->json(['message' => 'Time capsule already opened'], 400);
        }

        if ($timeCapsule->open_date > now()) {
            return response()->json(['message' => 'Time capsule is not ready to open yet'], 400);
        }

        try {
            DB::beginTransaction();

            $timeCapsule->update([
                'is_opened' => true,
                'opened_at' => now(),
                'opened_by' => Auth::id()
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Time capsule opened successfully!',
                'opened_at' => $timeCapsule->opened_at
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to open time capsule'], 500);
        }
    }

    /**
     * Add a contribution to a time capsule
     */
    public function addContribution(CreateTimeCapsuleContributionRequest $request, string $familySlug, string $capsuleId): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $timeCapsule = TimeCapsule::where('id', $capsuleId)
            ->where('family_id', $family->id)
            ->firstOrFail();

        // Check if can contribute
        if ($timeCapsule->is_opened) {
            return response()->json(['message' => 'Cannot contribute to opened time capsule'], 400);
        }

        try {
            DB::beginTransaction();

            $contribution = TimeCapsuleContribution::create([
                'time_capsule_id' => $timeCapsule->id,
                'user_id' => Auth::id(),
                'type' => $request->type,
                'content' => $request->content,
                'message' => $request->message
            ]);

            // Update capsule stats
            $timeCapsule->increment('contributions_count');

            DB::commit();

            $contribution->load('user');

            return response()->json(new TimeCapsuleContributionResource($contribution), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to add contribution'], 500);
        }
    }

    /**
     * Increment view count
     */
    public function incrementViews(string $familySlug, string $capsuleId): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $timeCapsule = TimeCapsule::where('id', $capsuleId)
            ->where('family_id', $family->id)
            ->firstOrFail();

        // Only count unique views per user per day
        $today = now()->format('Y-m-d');
        $existingView = TimeCapsuleView::where('time_capsule_id', $timeCapsule->id)
            ->where('user_id', Auth::id())
            ->whereDate('created_at', $today)
            ->first();

        if (!$existingView) {
            TimeCapsuleView::create([
                'time_capsule_id' => $timeCapsule->id,
                'user_id' => Auth::id()
            ]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Get time capsule contributions
     */
    public function contributions(Request $request, string $familySlug, string $capsuleId): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $timeCapsule = TimeCapsule::where('id', $capsuleId)
            ->where('family_id', $family->id)
            ->firstOrFail();

        $query = TimeCapsuleContribution::where('time_capsule_id', $timeCapsule->id)
            ->with(['user', 'media']);

        // Only show contributions if capsule is opened or user is the contributor
        if (!$timeCapsule->is_opened) {
            $query->where('user_id', Auth::id());
        }

        if ($request->has('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        $contributions = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json(new TimeCapsuleContributionCollection($contributions));
    }

    /**
     * Get time capsule comments
     */
    public function comments(Request $request, string $familySlug, string $capsuleId): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $timeCapsule = TimeCapsule::where('id', $capsuleId)
            ->where('family_id', $family->id)
            ->firstOrFail();

        $comments = TimeCapsuleComment::where('time_capsule_id', $timeCapsule->id)
            ->with(['user', 'replies.user'])
            ->whereNull('parent_id')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json(new TimeCapsuleCommentCollection($comments));
    }

    /**
     * Add a comment to a time capsule
     */
    public function addComment(CreateTimeCapsuleCommentRequest $request, string $familySlug, string $capsuleId): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $timeCapsule = TimeCapsule::where('id', $capsuleId)
            ->where('family_id', $family->id)
            ->firstOrFail();

        $comment = TimeCapsuleComment::create([
            'time_capsule_id' => $timeCapsule->id,
            'user_id' => Auth::id(),
            'content' => $request->content,
            'parent_id' => $request->parent_id
        ]);

        $comment->load('user');

        return response()->json(new TimeCapsuleCommentResource($comment), 201);
    }

    /**
     * Get time capsule statistics
     */
    public function statistics(string $familySlug): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $now = now();
        
        $stats = [
            'total_capsules' => TimeCapsule::where('family_id', $family->id)->count(),
            'opened_capsules' => TimeCapsule::where('family_id', $family->id)
                ->where('is_opened', true)
                ->count(),
            'ready_to_open' => TimeCapsule::where('family_id', $family->id)
                ->where('open_date', '<=', $now)
                ->where('is_opened', false)
                ->count(),
            'sealed_capsules' => TimeCapsule::where('family_id', $family->id)
                ->where('open_date', '>', $now)
                ->where('is_opened', false)
                ->count(),
            'total_contributions' => TimeCapsuleContribution::whereHas('timeCapsule', function ($query) use ($family) {
                    $query->where('family_id', $family->id);
                })->count(),
            'upcoming_openings' => TimeCapsule::where('family_id', $family->id)
                ->where('is_opened', false)
                ->where('open_date', '>', $now)
                ->orderBy('open_date')
                ->take(5)
                ->get(['id', 'title', 'open_date']),
            'recent_openings' => TimeCapsule::where('family_id', $family->id)
                ->where('is_opened', true)
                ->orderBy('opened_at', 'desc')
                ->take(5)
                ->get(['id', 'title', 'opened_at'])
        ];

        return response()->json($stats);
    }
}