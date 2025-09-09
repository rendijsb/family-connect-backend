<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Photos;

use App\Events\Photos\PhotoDeletedEvent;
use App\Events\Photos\PhotoLikedEvent;
use App\Events\Photos\PhotoUpdatedEvent;
use App\Events\Photos\PhotoUploadedEvent;
use App\Events\Photos\PhotoViewedEvent;
use App\Http\Controllers\Controller;
use App\Models\Families\Family;
use App\Models\Photos\Photo;
use App\Models\Photos\PhotoAlbum;
use App\Models\Photos\PhotoLike;
use App\Models\Users\User;
use App\Enums\Photos\AlbumPrivacyEnum;
use App\Services\PhotoStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\Facades\Image;

class PhotoController extends Controller
{
    protected PhotoStorageService $storageService;

    public function __construct(PhotoStorageService $storageService)
    {
        $this->storageService = $storageService;
    }
    public function index(Request $request, Family $family, PhotoAlbum $album): JsonResponse
    {
        $query = $album->photos()->with(['uploader', 'likes']);
        
        // Apply filters
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('original_name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orJsonContains('tags', $search);
            });
        }
        
        if ($request->filled('uploaded_by')) {
            $query->where('uploaded_by', $request->get('uploaded_by'));
        }
        
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->get('date_from'));
        }
        
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->get('date_to'));
        }
        
        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortBy, $sortDirection);
        
        $photos = $query->paginate(20);
        
        // Add user-specific data
        $photos->getCollection()->transform(function ($photo) {
            $photo->isLikedByCurrentUser = $photo->likes()
                ->where('user_id', Auth::id())->exists();
            return $photo;
        });
        
        return response()->json($photos);
    }

    public function store(Request $request, Family $family, PhotoAlbum $album): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|image|max:10240', // 10MB max
            'description' => 'nullable|string|max:1000',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'people_tagged' => 'nullable|array',
            'people_tagged.*' => 'integer|exists:users,id',
            'location' => 'nullable|string|max:200',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $file = $request->file('file');
        
        // Use storage service to handle file operations
        $fileData = $this->storageService->storePhoto($file);
        $exifData = $this->storageService->extractExifData($file);

        $photo = new Photo([
            'album_id' => $album->id,
            'uploaded_by' => Auth::id(),
            'filename' => $fileData['filename'],
            'original_name' => $fileData['original_name'],
            'mime_type' => $fileData['mime_type'],
            'path' => $fileData['path'],
            'thumbnail_path' => $fileData['thumbnail_path'],
            'size' => $fileData['size'],
            'description' => $request->get('description'),
            'tags' => $request->get('tags', []),
            'people_tagged' => $request->get('people_tagged', []),
            'location' => $request->get('location'),
            'metadata' => $exifData,
        ]);

        // Set taken_at from EXIF if available
        if ($exifData && isset($exifData['datetime_original'])) {
            $photo->taken_at = date('Y-m-d H:i:s', strtotime($exifData['datetime_original']));
        }

        $photo->save();
        
        // Update album stats
        $album->updateStats();
        
        // Broadcast the upload event
        broadcast(new PhotoUploadedEvent(
            $photo->load('uploader'),
            $photo->uploader,
            $family->slug
        ));

        return response()->json($photo->load(['uploader', 'album']), 201);
    }

    public function show(Request $request, Family $family, Photo $photo): JsonResponse
    {
        $photo->load(['uploader', 'album', 'comments.user', 'likes.user']);
        
        // Add user-specific data
        $photo->isLikedByCurrentUser = $photo->likes()
            ->where('user_id', Auth::id())->exists();
        
        return response()->json($photo);
    }

    public function update(Request $request, Family $family, Photo $photo): JsonResponse
    {
        // Check permissions - only uploader can edit
        if ($photo->uploaded_by !== Auth::id()) {
            abort(403, 'You can only edit your own photos');
        }

        $validator = Validator::make($request->all(), [
            'description' => 'nullable|string|max:1000',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'people_tagged' => 'nullable|array',
            'people_tagged.*' => 'integer|exists:users,id',
            'location' => 'nullable|string|max:200',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $originalData = $photo->toArray();

        $photo->update($request->only([
            'description', 'tags', 'people_tagged', 'location'
        ]));

        $changes = array_diff_assoc($photo->toArray(), $originalData);
        
        // Broadcast the update
        if (!empty($changes)) {
            broadcast(new PhotoUpdatedEvent(
                $photo,
                $changes,
                Auth::user(),
                $family->slug
            ));
        }

        return response()->json($photo->load(['uploader', 'album']));
    }

    public function destroy(Request $request, Family $family, Photo $photo): Response
    {
        // Check permissions - uploader or album owner can delete
        if ($photo->uploaded_by !== Auth::id() && $photo->album->created_by !== Auth::id()) {
            abort(403, 'You can only delete your own photos or photos in your albums');
        }

        $album = $photo->album;
        $photoData = [
            'id' => $photo->id,
            'filename' => $photo->filename,
            'album_id' => $photo->album_id,
        ];

        // Delete physical files using storage service
        $this->storageService->deletePhoto($photo->path, $photo->thumbnail_path);

        $photo->delete();
        
        // Update album stats
        $album->updateStats();
        $album->refresh();
        
        // Broadcast the deletion
        broadcast(new PhotoDeletedEvent(
            $photoData,
            Auth::user(),
            $family->slug
        ));

        return response()->noContent();
    }

    public function like(Request $request, Family $family, Photo $photo): JsonResponse
    {
        $user = Auth::user();
        
        $existingLike = $photo->likes()->where('user_id', $user->id)->first();
        
        if ($existingLike) {
            $existingLike->delete();
            $liked = false;
            $photo->decrement('likes_count');
        } else {
            $photo->likes()->create([
                'user_id' => $user->id,
            ]);
            $liked = true;
            $photo->increment('likes_count');
        }
        
        $photo->refresh();
        
        // Broadcast the like event
        broadcast(new PhotoLikedEvent(
            $photo,
            $user,
            $liked,
            $family->slug
        ));
        
        return response()->json([
            'liked' => $liked,
            'likesCount' => $photo->likes_count,
        ]);
    }

    public function incrementViews(Request $request, Family $family, Photo $photo): JsonResponse
    {
        $photo->increment('views_count');
        
        // Broadcast view event (throttled to avoid spam)
        if ($photo->views_count % 5 === 0) {
            broadcast(new PhotoViewedEvent(
                $photo,
                Auth::user(),
                $family->slug
            ));
        }
        
        return response()->json([
            'viewsCount' => $photo->views_count,
        ]);
    }

    public function download(Request $request, Family $family, Photo $photo): Response
    {
        $filePath = storage_path('app/public/' . $photo->path);
        
        if (!file_exists($filePath)) {
            abort(404, 'File not found');
        }

        return response()->download($filePath, $photo->original_name);
    }

    public function downloadBulk(Request $request, Family $family): Response
    {
        $validator = Validator::make($request->all(), [
            'photo_ids' => 'required|array',
            'photo_ids.*' => 'integer|exists:photos,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $photoIds = $request->get('photo_ids');
        $photos = Photo::whereIn('id', $photoIds)
            ->whereHas('album', function ($q) use ($family) {
                $q->where('family_id', $family->id);
            })
            ->get();

        if ($photos->isEmpty()) {
            return response()->json(['error' => 'No photos found'], 404);
        }

        // Create zip using storage service
        $zipFileName = 'family-photos-' . date('Y-m-d-H-i-s') . '.zip';
        $photoPaths = [];
        
        foreach ($photos as $photo) {
            $photoPaths[$photo->path] = $photo->original_name;
        }
        
        try {
            $zipPath = $this->storageService->createZipArchive($photoPaths, $zipFileName);
            return response()->download($zipPath, $zipFileName)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Could not create zip file: ' . $e->getMessage()], 500);
        }
    }

    public function recent(Request $request): JsonResponse
    {
        $user = Auth::user();
        $familyIds = $user->families()->pluck('families.id');

        $query = Photo::whereHas('album', function ($q) use ($familyIds) {
            $q->whereIn('family_id', $familyIds)
              ->where(function ($privacyQuery) use ($user) {
                  $privacyQuery->where('privacy', AlbumPrivacyEnum::FAMILY)
                      ->orWhere('privacy', AlbumPrivacyEnum::PUBLIC)
                      ->orWhere('created_by', $user->id)
                      ->orWhereJsonContains('allowed_members', $user->id);
              });
        })->with(['uploader', 'album']);

        $limit = min($request->get('limit', 20), 50); // Max 50 photos
        $photos = $query->latest('created_at')->limit($limit)->get();

        // Add user-specific data
        $photos->transform(function ($photo) use ($user) {
            $photo->isLikedByCurrentUser = $photo->likes()
                ->where('user_id', $user->id)->exists();
            return $photo;
        });

        return response()->json($photos);
    }

    public function favorites(Request $request): JsonResponse
    {
        $user = Auth::user();
        $familyIds = $user->families()->pluck('families.id');

        $query = Photo::whereHas('album', function ($q) use ($familyIds) {
            $q->whereIn('family_id', $familyIds);
        })->whereHas('likes', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->with(['uploader', 'album']);

        $photos = $query->latest('created_at')->paginate(20);

        // Add user-specific data
        $photos->getCollection()->transform(function ($photo) use ($user) {
            $photo->isLikedByCurrentUser = true; // Already filtered by user's likes
            return $photo;
        });

        return response()->json($photos);
    }

    public function taggedUser(Request $request, User $user): JsonResponse
    {
        $currentUser = Auth::user();
        $familyIds = $currentUser->families()->pluck('families.id');

        $query = Photo::whereHas('album', function ($q) use ($familyIds) {
            $q->whereIn('family_id', $familyIds)
              ->where(function ($privacyQuery) use ($currentUser) {
                  $privacyQuery->where('privacy', AlbumPrivacyEnum::FAMILY)
                      ->orWhere('privacy', AlbumPrivacyEnum::PUBLIC)
                      ->orWhere('created_by', $currentUser->id)
                      ->orWhereJsonContains('allowed_members', $currentUser->id);
              });
        })->whereJsonContains('people_tagged', $user->id)
          ->with(['uploader', 'album']);

        $photos = $query->latest('created_at')->paginate(20);

        // Add user-specific data
        $photos->getCollection()->transform(function ($photo) use ($currentUser) {
            $photo->isLikedByCurrentUser = $photo->likes()
                ->where('user_id', $currentUser->id)->exists();
            return $photo;
        });

        return response()->json($photos);
    }

    public function search(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'q' => 'required|string|min:2|max:100',
            'type' => 'nullable|in:photo,album,both',
            'family_id' => 'nullable|integer|exists:families,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $query = $request->get('q');
        $type = $request->get('type', 'both');
        $familyId = $request->get('family_id');
        $user = Auth::user();

        $results = [];

        // Get user's accessible family IDs
        $familyIds = $user->families()->pluck('families.id');
        if ($familyId) {
            $familyIds = $familyIds->filter(fn($id) => $id == $familyId);
        }

        // Search photos
        if (in_array($type, ['photo', 'both'])) {
            $photoQuery = Photo::whereHas('album', function ($q) use ($familyIds, $user) {
                $q->whereIn('family_id', $familyIds)
                  ->where(function ($privacyQuery) use ($user) {
                      $privacyQuery->where('privacy', AlbumPrivacyEnum::FAMILY)
                          ->orWhere('privacy', AlbumPrivacyEnum::PUBLIC)
                          ->orWhere('created_by', $user->id)
                          ->orWhereJsonContains('allowed_members', $user->id);
                  });
            })->where(function ($q) use ($query) {
                $q->where('original_name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%")
                  ->orJsonContains('tags', $query);
            })->with(['uploader', 'album']);

            $photos = $photoQuery->latest('created_at')->limit(20)->get();

            $photos->transform(function ($photo) use ($user) {
                $photo->isLikedByCurrentUser = $photo->likes()
                    ->where('user_id', $user->id)->exists();
                return $photo;
            });

            $results['photos'] = $photos;
        }

        // Search albums
        if (in_array($type, ['album', 'both'])) {
            $albumQuery = PhotoAlbum::whereIn('family_id', $familyIds)
                ->where(function ($privacyQuery) use ($user) {
                    $privacyQuery->where('privacy', AlbumPrivacyEnum::FAMILY)
                        ->orWhere('privacy', AlbumPrivacyEnum::PUBLIC)
                        ->orWhere('created_by', $user->id)
                        ->orWhereJsonContains('allowed_members', $user->id);
                })->where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                      ->orWhere('description', 'like', "%{$query}%");
                })->with(['creator']);

            $albums = $albumQuery->latest('updated_at')->limit(20)->get();

            $albums->transform(function ($album) use ($user) {
                $album->permissions = [
                    'canEdit' => $album->created_by === $user->id,
                    'canDelete' => $album->created_by === $user->id,
                    'canAddPhotos' => $album->canUserAccess($user),
                    'canManagePhotos' => $album->created_by === $user->id,
                ];
                return $album;
            });

            $results['albums'] = $albums;
        }

        return response()->json($results);
    }

}