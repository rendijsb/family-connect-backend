<?php

namespace App\Http\Controllers\Api\Memories;

use App\Http\Controllers\Controller;
use App\Http\Requests\Memories\CreateTraditionRequest;
use App\Http\Requests\Memories\UpdateTraditionRequest;
use App\Http\Requests\Memories\CreateTraditionCommentRequest;
use App\Http\Requests\Memories\CreateTraditionOccurrenceRequest;
use App\Http\Resources\Memories\TraditionResource;
use App\Http\Resources\Memories\TraditionCollection;
use App\Http\Resources\Memories\TraditionCommentResource;
use App\Http\Resources\Memories\TraditionCommentCollection;
use App\Http\Resources\Memories\TraditionOccurrenceResource;
use App\Http\Resources\Memories\TraditionOccurrenceCollection;
use App\Models\Family;
use App\Models\Memories\Tradition;
use App\Models\Memories\TraditionComment;
use App\Models\Memories\TraditionLike;
use App\Models\Memories\TraditionView;
use App\Models\Memories\TraditionOccurrence;
use App\Services\Memories\TraditionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TraditionController extends Controller
{
    protected TraditionService $traditionService;

    public function __construct(TraditionService $traditionService)
    {
        $this->traditionService = $traditionService;
    }

    /**
     * Get paginated traditions for a family
     */
    public function index(Request $request, string $familySlug): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = Tradition::where('family_id', $family->id)
            ->with(['creator', 'participants', 'media'])
            ->withCount(['likes', 'comments', 'views', 'occurrences']);

        // Apply filters
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        if ($request->has('frequency') && $request->frequency !== 'all') {
            $query->where('frequency', $request->frequency);
        }

        if ($request->has('active_only') && $request->boolean('active_only')) {
            $query->where('status', 'active');
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        switch ($sortBy) {
            case 'occurrences':
                $query->orderBy('occurrences_count', $sortOrder);
                break;
            case 'last_occurrence':
                $query->orderBy('last_occurrence', $sortOrder);
                break;
            case 'next_occurrence':
                $query->orderBy('next_occurrence', $sortOrder);
                break;
            case 'likes':
                $query->orderBy('likes_count', $sortOrder);
                break;
            default:
                $query->orderBy($sortBy, $sortOrder);
        }

        $traditions = $query->paginate($request->get('per_page', 20));

        return response()->json(new TraditionCollection($traditions));
    }

    /**
     * Get a specific tradition
     */
    public function show(string $familySlug, string $traditionId): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $tradition = Tradition::where('id', $traditionId)
            ->where('family_id', $family->id)
            ->with(['creator', 'participants', 'media'])
            ->withCount(['likes', 'comments', 'views', 'occurrences'])
            ->firstOrFail();

        // Check if user liked this tradition
        $tradition->is_liked_by_current_user = $tradition->likes()
            ->where('user_id', Auth::id())
            ->exists();

        return response()->json(new TraditionResource($tradition));
    }

    /**
     * Create a new tradition
     */
    public function store(CreateTraditionRequest $request, string $familySlug): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            DB::beginTransaction();

            $tradition = $this->traditionService->createTradition($family, $request->validated());

            DB::commit();

            $tradition->load(['creator', 'participants', 'media']);
            $tradition->loadCount(['likes', 'comments', 'views', 'occurrences']);

            return response()->json(new TraditionResource($tradition), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create tradition'], 500);
        }
    }

    /**
     * Update a tradition
     */
    public function update(UpdateTraditionRequest $request, string $familySlug, string $traditionId): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $tradition = Tradition::where('id', $traditionId)
            ->where('family_id', $family->id)
            ->firstOrFail();

        // Check permissions
        if (!$this->traditionService->canEdit($tradition, Auth::user())) {
            return response()->json(['message' => 'Cannot edit this tradition'], 403);
        }

        try {
            DB::beginTransaction();

            $tradition = $this->traditionService->updateTradition($tradition, $request->validated());

            DB::commit();

            $tradition->load(['creator', 'participants', 'media']);
            $tradition->loadCount(['likes', 'comments', 'views', 'occurrences']);

            return response()->json(new TraditionResource($tradition));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update tradition'], 500);
        }
    }

    /**
     * Delete a tradition
     */
    public function destroy(string $familySlug, string $traditionId): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $tradition = Tradition::where('id', $traditionId)
            ->where('family_id', $family->id)
            ->firstOrFail();

        // Check permissions
        if (!$this->traditionService->canDelete($tradition, Auth::user())) {
            return response()->json(['message' => 'Cannot delete this tradition'], 403);
        }

        try {
            $this->traditionService->deleteTradition($tradition);
            return response()->json(['message' => 'Tradition deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete tradition'], 500);
        }
    }

    /**
     * Toggle like on a tradition
     */
    public function toggleLike(string $familySlug, string $traditionId): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $tradition = Tradition::where('id', $traditionId)
            ->where('family_id', $family->id)
            ->firstOrFail();

        $like = TraditionLike::where('tradition_id', $tradition->id)
            ->where('user_id', Auth::id())
            ->first();

        if ($like) {
            $like->delete();
            $liked = false;
        } else {
            TraditionLike::create([
                'tradition_id' => $tradition->id,
                'user_id' => Auth::id()
            ]);
            $liked = true;
        }

        $likesCount = $tradition->likes()->count();

        return response()->json([
            'liked' => $liked,
            'likes_count' => $likesCount
        ]);
    }

    /**
     * Record a tradition occurrence
     */
    public function recordOccurrence(CreateTraditionOccurrenceRequest $request, string $familySlug, string $traditionId): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $tradition = Tradition::where('id', $traditionId)
            ->where('family_id', $family->id)
            ->firstOrFail();

        try {
            DB::beginTransaction();

            $occurrence = TraditionOccurrence::create([
                'tradition_id' => $tradition->id,
                'user_id' => Auth::id(),
                'actual_date' => now(),
                'notes' => $request->notes,
                'status' => 'completed'
            ]);

            // Update tradition stats
            $tradition->update([
                'total_occurrences' => $tradition->total_occurrences + 1,
                'last_occurrence' => $occurrence->actual_date
            ]);

            DB::commit();

            $occurrence->load('user');

            return response()->json(new TraditionOccurrenceResource($occurrence), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to record occurrence'], 500);
        }
    }

    /**
     * Increment view count
     */
    public function incrementViews(string $familySlug, string $traditionId): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $tradition = Tradition::where('id', $traditionId)
            ->where('family_id', $family->id)
            ->firstOrFail();

        // Only count unique views per user per day
        $today = now()->format('Y-m-d');
        $existingView = TraditionView::where('tradition_id', $tradition->id)
            ->where('user_id', Auth::id())
            ->whereDate('created_at', $today)
            ->first();

        if (!$existingView) {
            TraditionView::create([
                'tradition_id' => $tradition->id,
                'user_id' => Auth::id()
            ]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Get tradition occurrences
     */
    public function occurrences(Request $request, string $familySlug, string $traditionId): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $tradition = Tradition::where('id', $traditionId)
            ->where('family_id', $family->id)
            ->firstOrFail();

        $query = TraditionOccurrence::where('tradition_id', $tradition->id)
            ->with(['user', 'media']);

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('year') && $request->year !== 'all') {
            $query->whereYear('actual_date', $request->year);
        }

        $occurrences = $query->orderBy('actual_date', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json(new TraditionOccurrenceCollection($occurrences));
    }

    /**
     * Get tradition comments
     */
    public function comments(Request $request, string $familySlug, string $traditionId): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $tradition = Tradition::where('id', $traditionId)
            ->where('family_id', $family->id)
            ->firstOrFail();

        $comments = TraditionComment::where('tradition_id', $tradition->id)
            ->with(['user', 'replies.user'])
            ->whereNull('parent_id')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json(new TraditionCommentCollection($comments));
    }

    /**
     * Add a comment to a tradition
     */
    public function addComment(CreateTraditionCommentRequest $request, string $familySlug, string $traditionId): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $tradition = Tradition::where('id', $traditionId)
            ->where('family_id', $family->id)
            ->firstOrFail();

        $comment = TraditionComment::create([
            'tradition_id' => $tradition->id,
            'user_id' => Auth::id(),
            'content' => $request->content,
            'parent_id' => $request->parent_id
        ]);

        $comment->load('user');

        return response()->json(new TraditionCommentResource($comment), 201);
    }

    /**
     * Get tradition statistics
     */
    public function statistics(string $familySlug): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $stats = [
            'total_traditions' => Tradition::where('family_id', $family->id)->count(),
            'active_traditions' => Tradition::where('family_id', $family->id)
                ->where('status', 'active')
                ->count(),
            'total_occurrences' => TraditionOccurrence::whereHas('tradition', function ($query) use ($family) {
                    $query->where('family_id', $family->id);
                })->count(),
            'categories' => Tradition::where('family_id', $family->id)
                ->select('category', DB::raw('count(*) as count'))
                ->groupBy('category')
                ->get()
                ->pluck('count', 'category'),
            'frequencies' => Tradition::where('family_id', $family->id)
                ->select('frequency', DB::raw('count(*) as count'))
                ->groupBy('frequency')
                ->get()
                ->pluck('count', 'frequency'),
            'upcoming_traditions' => Tradition::where('family_id', $family->id)
                ->where('status', 'active')
                ->whereNotNull('next_occurrence')
                ->where('next_occurrence', '>', now())
                ->orderBy('next_occurrence')
                ->take(5)
                ->get(['id', 'title', 'next_occurrence'])
        ];

        return response()->json($stats);
    }
}