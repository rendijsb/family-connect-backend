<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Photos;

use App\Events\Photos\AlbumCreatedEvent;
use App\Events\Photos\AlbumDeletedEvent;
use App\Events\Photos\AlbumUpdatedEvent;
use App\Http\Controllers\Controller;
use App\Models\Families\Family;
use App\Models\Photos\PhotoAlbum;
use App\Enums\Photos\AlbumPrivacyEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PhotoAlbumController extends Controller
{
    public function index(Request $request, Family $family): JsonResponse
    {
        $query = $family->photoAlbums()->with(['creator', 'photos' => function ($q) {
            $q->latest()->limit(4);
        }]);
        
        // Apply filters
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        
        if ($request->filled('privacy')) {
            $privacyFilters = explode(',', $request->get('privacy'));
            $query->whereIn('privacy', $privacyFilters);
        }
        
        if ($request->filled('created_by')) {
            $query->where('created_by', $request->get('created_by'));
        }
        
        // Sorting
        $sortBy = $request->get('sort_by', 'updated_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortBy, $sortDirection);
        
        // Apply privacy filtering based on user permissions
        $user = Auth::user();
        $query->where(function ($q) use ($user) {
            $q->where('privacy', AlbumPrivacyEnum::FAMILY)
              ->orWhere('privacy', AlbumPrivacyEnum::PUBLIC)
              ->orWhere('created_by', $user->id)
              ->orWhereJsonContains('allowed_members', $user->id);
        });
        
        $albums = $query->paginate(12);
        
        // Add permissions for each album
        $albums->getCollection()->transform(function ($album) use ($user) {
            $album->permissions = [
                'canEdit' => $album->created_by === $user->id,
                'canDelete' => $album->created_by === $user->id,
                'canAddPhotos' => $album->canUserAccess($user),
                'canManagePhotos' => $album->created_by === $user->id,
            ];
            return $album;
        });
        
        return response()->json($albums);
    }

    public function store(Request $request, Family $family): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'privacy' => ['required', Rule::enum(AlbumPrivacyEnum::class)],
            'allowed_members' => 'nullable|array',
            'allowed_members.*' => 'integer|exists:users,id',
            'allow_download' => 'nullable|boolean',
            'allow_comments' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $album = new PhotoAlbum([
            'family_id' => $family->id,
            'created_by' => Auth::id(),
            'name' => $request->get('name'),
            'description' => $request->get('description'),
            'privacy' => AlbumPrivacyEnum::from($request->get('privacy')),
            'allowed_members' => $request->get('allowed_members', []),
            'allow_download' => $request->get('allow_download', true),
            'allow_comments' => $request->get('allow_comments', true),
        ]);

        $album->save();
        
        // Broadcast the creation
        broadcast(new AlbumCreatedEvent(
            $album->load('creator'),
            $album->creator,
            $family->slug
        ));

        return response()->json($album->load(['creator']), 201);
    }

    public function show(Request $request, Family $family, PhotoAlbum $album): JsonResponse
    {
        // Check if user can access this album
        if (!$album->canUserAccess(Auth::user())) {
            abort(403, 'You do not have permission to view this album');
        }
        
        $album->load(['creator', 'photos' => function ($q) {
            $q->latest()->limit(12);
        }]);
        
        // Add permissions
        $user = Auth::user();
        $album->permissions = [
            'canEdit' => $album->created_by === $user->id,
            'canDelete' => $album->created_by === $user->id,
            'canAddPhotos' => $album->canUserAccess($user),
            'canManagePhotos' => $album->created_by === $user->id,
        ];

        return response()->json($album);
    }

    public function update(Request $request, Family $family, PhotoAlbum $album): JsonResponse
    {
        // Check permissions
        if ($album->created_by !== Auth::id()) {
            abort(403, 'You can only edit your own albums');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:100',
            'description' => 'nullable|string|max:500',
            'privacy' => ['sometimes', Rule::enum(AlbumPrivacyEnum::class)],
            'allowed_members' => 'nullable|array',
            'allowed_members.*' => 'integer|exists:users,id',
            'allow_download' => 'nullable|boolean',
            'allow_comments' => 'nullable|boolean',
            'cover_photo' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $originalData = $album->toArray();
        
        $updateData = $request->only([
            'name', 'description', 'privacy', 'allowed_members',
            'allow_download', 'allow_comments', 'cover_photo'
        ]);
        
        if (isset($updateData['privacy'])) {
            $updateData['privacy'] = AlbumPrivacyEnum::from($updateData['privacy']);
        }

        $album->update($updateData);
        
        $changes = array_diff_assoc($album->toArray(), $originalData);
        
        // Broadcast the update
        if (!empty($changes)) {
            broadcast(new AlbumUpdatedEvent(
                $album,
                $changes,
                Auth::user(),
                $family->slug
            ));
        }

        return response()->json($album->load(['creator']));
    }

    public function destroy(Request $request, Family $family, PhotoAlbum $album): Response
    {
        // Check permissions
        if ($album->created_by !== Auth::id()) {
            abort(403, 'You can only delete your own albums');
        }

        $albumData = [
            'id' => $album->id,
            'name' => $album->name,
            'photoCount' => $album->photo_count,
        ];
        
        $albumId = $album->id;
        
        // Delete all photos in the album (this will cascade)
        $album->photos()->delete();
        
        $album->delete();
        
        // Broadcast the deletion
        broadcast(new AlbumDeletedEvent(
            $albumId,
            $albumData,
            Auth::user(),
            $family->slug
        ));

        return response()->noContent();
    }

    public function stats(Request $request, Family $family, PhotoAlbum $album = null): JsonResponse
    {
        if ($album) {
            // Single album stats
            $stats = [
                'totalPhotos' => $album->photo_count,
                'totalVideos' => $album->video_count,
                'totalSize' => $album->total_size,
                'totalViews' => $album->photos()->sum('views_count'),
                'totalLikes' => $album->photos()->sum('likes_count'),
                'totalComments' => $album->photos()->sum('comments_count'),
                'lastUpdated' => $album->last_updated_at,
            ];
        } else {
            // Family-wide album stats
            $familyAlbums = $family->photoAlbums();
            
            $stats = [
                'totalAlbums' => $familyAlbums->count(),
                'publicAlbums' => $familyAlbums->where('privacy', AlbumPrivacyEnum::PUBLIC)->count(),
                'privateAlbums' => $familyAlbums->where('privacy', AlbumPrivacyEnum::PRIVATE)->count(),
                'familyAlbums' => $familyAlbums->where('privacy', AlbumPrivacyEnum::FAMILY)->count(),
                'totalPhotos' => $familyAlbums->sum('photo_count'),
                'totalVideos' => $familyAlbums->sum('video_count'),
                'totalSize' => $familyAlbums->sum('total_size'),
            ];
        }

        return response()->json($stats);
    }

    public function setCoverPhoto(Request $request, Family $family, PhotoAlbum $album): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'photo_id' => 'required|integer|exists:photos,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $photoId = $request->get('photo_id');
        
        // Verify the photo belongs to this album
        $photo = $album->photos()->find($photoId);
        if (!$photo) {
            return response()->json(['error' => 'Photo not found in this album'], 404);
        }

        $album->update(['cover_photo' => $photo->path]);
        
        // Broadcast the update
        broadcast(new AlbumUpdatedEvent(
            $album,
            ['cover_photo' => $photo->path],
            Auth::user(),
            $family->slug
        ));

        return response()->json($album->load(['creator']));
    }
}