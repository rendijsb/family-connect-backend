<?php

namespace App\Http\Controllers\Api\Memories;

use App\Http\Controllers\Controller;
use App\Http\Requests\Memories\CreateMemoryRequest;
use App\Http\Requests\Memories\UpdateMemoryRequest;
use App\Http\Requests\Memories\CreateMemoryCommentRequest;
use App\Http\Resources\Memories\MemoryResource;
use App\Http\Resources\Memories\MemoryCollection;
use App\Http\Resources\Memories\MemoryCommentResource;
use App\Http\Resources\Memories\MemoryCommentCollection;
use App\Models\Family;
use App\Models\Memories\Memory;
use App\Models\Memories\MemoryComment;
use App\Models\Memories\MemoryLike;
use App\Models\Memories\MemoryView;
use App\Services\Memories\MemoryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MemoryController extends Controller
{
    protected MemoryService $memoryService;

    public function __construct(MemoryService $memoryService)
    {
        $this->memoryService = $memoryService;
    }

    /**
     * Get paginated memories for a family
     */
    public function index(Request $request, string $familySlug): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        // Check if user is family member
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = Memory::where('family_id', $family->id)
            ->with(['creator', 'participants', 'media', 'tags'])
            ->withCount(['likes', 'comments', 'views']);

        // Apply filters
        if ($request->has('view_mode')) {
            switch ($request->view_mode) {
                case 'featured':
                    $query->where('is_featured', true);
                    break;
                case 'recent':
                    $query->where('date', '>=', now()->subMonths(3));
                    break;
                case 'popular':
                    $query->withCount('likes')->orderBy('likes_count', 'desc');
                    break;
            }
        }

        if ($request->has('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        if ($request->has('year') && $request->year !== 'all') {
            $query->whereYear('date', $request->year);
        }

        if ($request->has('tags')) {
            $tags = explode(',', $request->tags);
            $query->whereHas('tags', function ($q) use ($tags) {
                $q->whereIn('name', $tags);
            });
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'date');
        $sortOrder = $request->get('sort_order', 'desc');
        
        switch ($sortBy) {
            case 'likes':
                $query->orderBy('likes_count', $sortOrder);
                break;
            case 'comments':
                $query->orderBy('comments_count', $sortOrder);
                break;
            case 'views':
                $query->orderBy('views_count', $sortOrder);
                break;
            case 'created':
                $query->orderBy('created_at', $sortOrder);
                break;
            default:
                $query->orderBy('date', $sortOrder);
        }

        $memories = $query->paginate($request->get('per_page', 20));

        return response()->json(new MemoryCollection($memories));
    }

    /**
     * Get a specific memory
     */
    public function show(string $familySlug, string $memoryId): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $memory = Memory::where('id', $memoryId)
            ->where('family_id', $family->id)
            ->with(['creator', 'participants', 'media', 'tags'])
            ->withCount(['likes', 'comments', 'views'])
            ->firstOrFail();

        // Check if user liked this memory
        $memory->is_liked_by_current_user = $memory->likes()
            ->where('user_id', Auth::id())
            ->exists();

        return response()->json(new MemoryResource($memory));
    }

    /**
     * Create a new memory
     */
    public function store(CreateMemoryRequest $request, string $familySlug): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            DB::beginTransaction();

            $memory = $this->memoryService->createMemory($family, $request->validated());

            DB::commit();

            $memory->load(['creator', 'participants', 'media', 'tags']);
            $memory->loadCount(['likes', 'comments', 'views']);

            return response()->json(new MemoryResource($memory), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create memory'], 500);
        }
    }

    /**
     * Update a memory
     */
    public function update(UpdateMemoryRequest $request, string $familySlug, string $memoryId): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $memory = Memory::where('id', $memoryId)
            ->where('family_id', $family->id)
            ->firstOrFail();

        // Check permissions
        if (!$this->memoryService->canEdit($memory, Auth::user())) {
            return response()->json(['message' => 'Cannot edit this memory'], 403);
        }

        try {
            DB::beginTransaction();

            $memory = $this->memoryService->updateMemory($memory, $request->validated());

            DB::commit();

            $memory->load(['creator', 'participants', 'media', 'tags']);
            $memory->loadCount(['likes', 'comments', 'views']);

            return response()->json(new MemoryResource($memory));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update memory'], 500);
        }
    }

    /**
     * Delete a memory
     */
    public function destroy(string $familySlug, string $memoryId): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $memory = Memory::where('id', $memoryId)
            ->where('family_id', $family->id)
            ->firstOrFail();

        // Check permissions
        if (!$this->memoryService->canDelete($memory, Auth::user())) {
            return response()->json(['message' => 'Cannot delete this memory'], 403);
        }

        try {
            $this->memoryService->deleteMemory($memory);
            return response()->json(['message' => 'Memory deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete memory'], 500);
        }
    }

    /**
     * Toggle like on a memory
     */
    public function toggleLike(string $familySlug, string $memoryId): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $memory = Memory::where('id', $memoryId)
            ->where('family_id', $family->id)
            ->firstOrFail();

        $like = MemoryLike::where('memory_id', $memory->id)
            ->where('user_id', Auth::id())
            ->first();

        if ($like) {
            $like->delete();
            $liked = false;
        } else {
            MemoryLike::create([
                'memory_id' => $memory->id,
                'user_id' => Auth::id()
            ]);
            $liked = true;
        }

        $likesCount = $memory->likes()->count();

        return response()->json([
            'liked' => $liked,
            'likes_count' => $likesCount
        ]);
    }

    /**
     * Toggle featured status
     */
    public function toggleFeatured(string $familySlug, string $memoryId): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        $member = $family->members()->where('user_id', Auth::id())->first();
        if (!$member || !in_array($member->role, ['owner', 'moderator'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $memory = Memory::where('id', $memoryId)
            ->where('family_id', $family->id)
            ->firstOrFail();

        $memory->update([
            'is_featured' => !$memory->is_featured
        ]);

        return response()->json([
            'is_featured' => $memory->is_featured
        ]);
    }

    /**
     * Increment view count
     */
    public function incrementViews(string $familySlug, string $memoryId): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $memory = Memory::where('id', $memoryId)
            ->where('family_id', $family->id)
            ->firstOrFail();

        // Only count unique views per user per day
        $today = now()->format('Y-m-d');
        $existingView = MemoryView::where('memory_id', $memory->id)
            ->where('user_id', Auth::id())
            ->whereDate('created_at', $today)
            ->first();

        if (!$existingView) {
            MemoryView::create([
                'memory_id' => $memory->id,
                'user_id' => Auth::id()
            ]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Get memory comments
     */
    public function comments(Request $request, string $familySlug, string $memoryId): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $memory = Memory::where('id', $memoryId)
            ->where('family_id', $family->id)
            ->firstOrFail();

        $comments = MemoryComment::where('memory_id', $memory->id)
            ->with(['user', 'replies.user'])
            ->whereNull('parent_id')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json(new MemoryCommentCollection($comments));
    }

    /**
     * Add a comment to a memory
     */
    public function addComment(CreateMemoryCommentRequest $request, string $familySlug, string $memoryId): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $memory = Memory::where('id', $memoryId)
            ->where('family_id', $family->id)
            ->firstOrFail();

        $comment = MemoryComment::create([
            'memory_id' => $memory->id,
            'user_id' => Auth::id(),
            'content' => $request->content,
            'parent_id' => $request->parent_id
        ]);

        $comment->load('user');

        return response()->json(new MemoryCommentResource($comment), 201);
    }

    /**
     * Get memory statistics
     */
    public function statistics(string $familySlug): JsonResponse
    {
        $family = Family::where('slug', $familySlug)->firstOrFail();
        
        if (!$family->isMember(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $stats = [
            'total_memories' => Memory::where('family_id', $family->id)->count(),
            'memories_this_year' => Memory::where('family_id', $family->id)
                ->whereYear('date', now()->year)
                ->count(),
            'featured_memories' => Memory::where('family_id', $family->id)
                ->where('is_featured', true)
                ->count(),
            'total_media' => Memory::where('family_id', $family->id)
                ->withCount('media')
                ->get()
                ->sum('media_count'),
            'categories' => Memory::where('family_id', $family->id)
                ->select('category', DB::raw('count(*) as count'))
                ->groupBy('category')
                ->get()
                ->pluck('count', 'category'),
            'yearly_counts' => Memory::where('family_id', $family->id)
                ->select(DB::raw('YEAR(date) as year'), DB::raw('count(*) as count'))
                ->groupBy('year')
                ->orderBy('year', 'desc')
                ->get()
                ->pluck('count', 'year')
        ];

        return response()->json($stats);
    }
}