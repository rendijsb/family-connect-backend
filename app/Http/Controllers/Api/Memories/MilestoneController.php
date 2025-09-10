<?php

namespace App\Http\Controllers\Api\Memories;

use App\Http\Controllers\Controller;
use App\Http\Requests\Memories\CreateMilestoneRequest;
use App\Http\Requests\Memories\UpdateMilestoneRequest;
use App\Http\Requests\Memories\CreateMilestoneCommentRequest;
use App\Http\Resources\Memories\MilestoneResource;
use App\Http\Resources\Memories\MilestoneCollection;
use App\Http\Resources\Memories\MilestoneCommentResource;
use App\Http\Resources\Memories\MilestoneCommentCollection;
use App\Models\Family;
use App\Models\Memories\Milestone;
use App\Models\Memories\MilestoneComment;
use App\Models\Memories\MilestoneLike;
use App\Models\Memories\MilestoneView;
use App\Services\Memories\MilestoneService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MilestoneController extends Controller
{
    protected MilestoneService $milestoneService;

    public function __construct(MilestoneService $milestoneService)
    {
        $this->milestoneService = $milestoneService;
    }

    /**
     * Get paginated milestones for a family
     */
    public function index(Request $request, string $familySlug): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = Milestone::where('family_id', $family->id)
            ->with(['creator', 'participants', 'media'])
            ->withCount(['likes', 'comments', 'views']);

        // Apply filters
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        if ($request->has('completed')) {
            $query->where('is_completed', $request->boolean('completed'));
        }

        if ($request->has('year') && $request->year !== 'all') {
            $query->whereYear('date', $request->year);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'date');
        $sortOrder = $request->get('sort_order', 'desc');
        
        switch ($sortBy) {
            case 'progress':
                $query->orderByRaw('(current_value / NULLIF(target_value, 0)) DESC');
                break;
            case 'likes':
                $query->orderBy('likes_count', $sortOrder);
                break;
            case 'created':
                $query->orderBy('created_at', $sortOrder);
                break;
            default:
                $query->orderBy('date', $sortOrder);
        }

        $milestones = $query->paginate($request->get('per_page', 20));

        return response()->json(new MilestoneCollection($milestones));
    }

    /**
     * Get a specific milestone
     */
    public function show(string $familySlug, string $milestoneId): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $milestone = Milestone::where('id', $milestoneId)
            ->where('family_id', $family->id)
            ->with(['creator', 'participants', 'media'])
            ->withCount(['likes', 'comments', 'views'])
            ->firstOrFail();

        // Check if user liked this milestone
        $milestone->is_liked_by_current_user = $milestone->likes()
            ->where('user_id', Auth::id())
            ->exists();

        return response()->json(new MilestoneResource($milestone));
    }

    /**
     * Create a new milestone
     */
    public function store(CreateMilestoneRequest $request, string $familySlug): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            DB::beginTransaction();

            $milestone = $this->milestoneService->createMilestone($family, $request->validated());

            DB::commit();

            $milestone->load(['creator', 'participants', 'media']);
            $milestone->loadCount(['likes', 'comments', 'views']);

            return response()->json(new MilestoneResource($milestone), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create milestone'], 500);
        }
    }

    /**
     * Update a milestone
     */
    public function update(UpdateMilestoneRequest $request, string $familySlug, string $milestoneId): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $milestone = Milestone::where('id', $milestoneId)
            ->where('family_id', $family->id)
            ->firstOrFail();

        // Check permissions
        if (!$this->milestoneService->canEdit($milestone, Auth::user())) {
            return response()->json(['message' => 'Cannot edit this milestone'], 403);
        }

        try {
            DB::beginTransaction();

            $milestone = $this->milestoneService->updateMilestone($milestone, $request->validated());

            DB::commit();

            $milestone->load(['creator', 'participants', 'media']);
            $milestone->loadCount(['likes', 'comments', 'views']);

            return response()->json(new MilestoneResource($milestone));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update milestone'], 500);
        }
    }

    /**
     * Delete a milestone
     */
    public function destroy(string $familySlug, string $milestoneId): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $milestone = Milestone::where('id', $milestoneId)
            ->where('family_id', $family->id)
            ->firstOrFail();

        // Check permissions
        if (!$this->milestoneService->canDelete($milestone, Auth::user())) {
            return response()->json(['message' => 'Cannot delete this milestone'], 403);
        }

        try {
            $this->milestoneService->deleteMilestone($milestone);
            return response()->json(['message' => 'Milestone deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete milestone'], 500);
        }
    }

    /**
     * Toggle like on a milestone
     */
    public function toggleLike(string $familySlug, string $milestoneId): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $milestone = Milestone::where('id', $milestoneId)
            ->where('family_id', $family->id)
            ->firstOrFail();

        $like = MilestoneLike::where('milestone_id', $milestone->id)
            ->where('user_id', Auth::id())
            ->first();

        if ($like) {
            $like->delete();
            $liked = false;
        } else {
            MilestoneLike::create([
                'milestone_id' => $milestone->id,
                'user_id' => Auth::id()
            ]);
            $liked = true;
        }

        $likesCount = $milestone->likes()->count();

        return response()->json([
            'liked' => $liked,
            'likes_count' => $likesCount
        ]);
    }

    /**
     * Update milestone progress
     */
    public function updateProgress(Request $request, string $familySlug, string $milestoneId): JsonResponse
    {
        $request->validate([
            'current_value' => 'required|numeric|min:0'
        ]);

        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        $member = $family->members()->where('user_id', Auth::id())->first();
        if (!$member) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $milestone = Milestone::where('id', $milestoneId)
            ->where('family_id', $family->id)
            ->firstOrFail();

        // Check permissions
        if (!$this->milestoneService->canEdit($milestone, Auth::user())) {
            return response()->json(['message' => 'Cannot update this milestone'], 403);
        }

        $currentValue = $request->current_value;
        $isCompleted = $milestone->target_value && $currentValue >= $milestone->target_value;

        $milestone->update([
            'current_value' => $currentValue,
            'is_completed' => $isCompleted,
            'completed_at' => $isCompleted && !$milestone->is_completed ? now() : $milestone->completed_at,
            'status' => $isCompleted ? 'completed' : ($currentValue > 0 ? 'in_progress' : 'planned')
        ]);

        return response()->json([
            'current_value' => $milestone->current_value,
            'is_completed' => $milestone->is_completed,
            'completed_at' => $milestone->completed_at,
            'status' => $milestone->status
        ]);
    }

    /**
     * Increment view count
     */
    public function incrementViews(string $familySlug, string $milestoneId): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $milestone = Milestone::where('id', $milestoneId)
            ->where('family_id', $family->id)
            ->firstOrFail();

        // Only count unique views per user per day
        $today = now()->format('Y-m-d');
        $existingView = MilestoneView::where('milestone_id', $milestone->id)
            ->where('user_id', Auth::id())
            ->whereDate('created_at', $today)
            ->first();

        if (!$existingView) {
            MilestoneView::create([
                'milestone_id' => $milestone->id,
                'user_id' => Auth::id()
            ]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Get milestone comments
     */
    public function comments(Request $request, string $familySlug, string $milestoneId): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $milestone = Milestone::where('id', $milestoneId)
            ->where('family_id', $family->id)
            ->firstOrFail();

        $comments = MilestoneComment::where('milestone_id', $milestone->id)
            ->with(['user', 'replies.user'])
            ->whereNull('parent_id')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json(new MilestoneCommentCollection($comments));
    }

    /**
     * Add a comment to a milestone
     */
    public function addComment(CreateMilestoneCommentRequest $request, string $familySlug, string $milestoneId): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $milestone = Milestone::where('id', $milestoneId)
            ->where('family_id', $family->id)
            ->firstOrFail();

        $comment = MilestoneComment::create([
            'milestone_id' => $milestone->id,
            'user_id' => Auth::id(),
            'content' => $request->content,
            'parent_id' => $request->parent_id
        ]);

        $comment->load('user');

        return response()->json(new MilestoneCommentResource($comment), 201);
    }

    /**
     * Get milestone statistics
     */
    public function statistics(string $familySlug): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $stats = [
            'total_milestones' => Milestone::where('family_id', $family->id)->count(),
            'completed_milestones' => Milestone::where('family_id', $family->id)
                ->where('is_completed', true)
                ->count(),
            'in_progress_milestones' => Milestone::where('family_id', $family->id)
                ->where('status', 'in_progress')
                ->count(),
            'planned_milestones' => Milestone::where('family_id', $family->id)
                ->where('status', 'planned')
                ->count(),
            'categories' => Milestone::where('family_id', $family->id)
                ->select('category', DB::raw('count(*) as count'))
                ->groupBy('category')
                ->get()
                ->pluck('count', 'category'),
            'completion_rate' => Milestone::where('family_id', $family->id)->count() > 0 ?
                round((Milestone::where('family_id', $family->id)->where('is_completed', true)->count() / 
                       Milestone::where('family_id', $family->id)->count()) * 100, 2) : 0,
            'average_progress' => Milestone::where('family_id', $family->id)
                ->whereNotNull('target_value')
                ->where('target_value', '>', 0)
                ->get()
                ->avg(function ($milestone) {
                    return ($milestone->current_value / $milestone->target_value) * 100;
                })
        ];

        return response()->json($stats);
    }
}