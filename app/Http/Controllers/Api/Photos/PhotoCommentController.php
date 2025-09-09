<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Photos;

use App\Events\Photos\PhotoCommentedEvent;
use App\Events\Photos\PhotoCommentDeletedEvent;
use App\Http\Controllers\Controller;
use App\Models\Families\Family;
use App\Models\Photos\Photo;
use App\Models\Photos\PhotoComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PhotoCommentController extends Controller
{
    public function index(Request $request, Family $family, Photo $photo): JsonResponse
    {
        $comments = $photo->topLevelComments()
            ->with(['user', 'replies.user', 'replies.replies.user'])
            ->paginate(10);

        return response()->json($comments);
    }

    public function store(Request $request, Family $family, Photo $photo): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'comment' => 'required|string|max:1000',
            'parent_id' => 'nullable|integer|exists:photo_comments,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // If parent_id is provided, verify it belongs to this photo
        if ($request->filled('parent_id')) {
            $parentComment = PhotoComment::find($request->get('parent_id'));
            if (!$parentComment || $parentComment->photo_id !== $photo->id) {
                return response()->json(['error' => 'Invalid parent comment'], 400);
            }
        }

        $comment = new PhotoComment([
            'photo_id' => $photo->id,
            'user_id' => Auth::id(),
            'parent_id' => $request->get('parent_id'),
            'comment' => $request->get('comment'),
        ]);

        $comment->save();
        $comment->load('user');
        
        // Update photo comments count
        $photo->updateCommentsCount();
        $photo->refresh();
        
        // Broadcast the comment
        broadcast(new PhotoCommentedEvent(
            $photo,
            $comment,
            $comment->user,
            $family->slug
        ));

        return response()->json($comment, 201);
    }

    public function update(Request $request, Family $family, PhotoComment $comment): JsonResponse
    {
        // Check permissions
        if ($comment->user_id !== Auth::id()) {
            abort(403, 'You can only edit your own comments');
        }

        $validator = Validator::make($request->all(), [
            'comment' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $comment->update([
            'comment' => $request->get('comment'),
            'is_edited' => true,
            'edited_at' => now(),
        ]);

        return response()->json($comment->load('user'));
    }

    public function destroy(Request $request, Family $family, PhotoComment $comment): Response
    {
        // Check permissions
        if ($comment->user_id !== Auth::id()) {
            abort(403, 'You can only delete your own comments');
        }

        $photo = $comment->photo;
        $commentId = $comment->id;
        
        // Delete the comment (this will cascade delete replies)
        $comment->delete();
        
        // Update photo comments count
        $photo->updateCommentsCount();
        $photo->refresh();
        
        // Broadcast the deletion
        broadcast(new PhotoCommentDeletedEvent(
            $photo,
            $commentId,
            Auth::user(),
            $family->slug
        ));

        return response()->noContent();
    }
}