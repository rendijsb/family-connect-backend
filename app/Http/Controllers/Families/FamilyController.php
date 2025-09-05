<?php

declare(strict_types=1);

namespace App\Http\Controllers\Families;

use App\Enums\Families\FamilyRoleEnum;
use App\Http\Requests\Families\CreateFamilyRequest;
use App\Http\Requests\Families\DeleteFamilyRequest;
use App\Http\Requests\Families\GenerateJoinCodeRequest;
use App\Http\Requests\Families\GetAllFamiliesRequest;
use App\Http\Requests\Families\GetFamilyBySlugRequest;
use App\Http\Requests\Families\GetMyFamiliesRequest;
use App\Http\Requests\Families\InviteMemberRequest;
use App\Http\Requests\Families\JoinFamilyByCodeRequest;
use App\Http\Requests\Families\LeaveFamilyRequest;
use App\Http\Requests\Families\UpdateFamilyRequest;
use App\Http\Resources\Families\FamilyResource;
use App\Http\Resources\Families\FamilyResourceCollection;
use App\Models\Families\Family;
use App\Models\Families\FamilyMember;
use App\Services\Repositories\Families\FamilyRepository;
use Illuminate\Http\JsonResponse;

class FamilyController
{
    public function __construct(
        private readonly FamilyRepository $familyRepository,
    )
    {
    }

    public function getAllFamilies(GetAllFamiliesRequest $request): FamilyResourceCollection
    {
        return $request->responseResource(
            $this->familyRepository->getAllFamilies()
        );
    }

    public function createFamily(CreateFamilyRequest $request): FamilyResource
    {
        return $request->responseResource(
            $this->familyRepository->createFamily($request->dto())
        );
    }

    public function getMyFamilies(GetMyFamiliesRequest $request): FamilyResourceCollection
    {
        return $request->responseResource(
            $this->familyRepository->getMyFamilies()
        );
    }

    public function getFamilyBySlug(GetFamilyBySlugRequest $request): FamilyResource
    {
        return $request->responseResource(
            $this->familyRepository->getFamilyBySlug($request->getFamilySlug())
        );
    }

    public function updateFamily(UpdateFamilyRequest $request): FamilyResource
    {
        $this->ensureCanManageFamily($request);

        return $request->responseResource(
            $this->familyRepository->updateFamily($request->dto())
        );
    }

    public function deleteFamily(DeleteFamilyRequest $request): JsonResponse
    {
        $this->ensureIsOwner($request);
        $this->familyRepository->deleteFamily($request->getFamilySlug());

        return $this->successResponse('Family deleted successfully.');
    }

    public function leaveFamily(LeaveFamilyRequest $request): JsonResponse
    {
        $this->familyRepository->leaveFamily($request->getFamilySlug());
        return $this->successResponse('Family left.');
    }

    public function joinFamilyByCode(JoinFamilyByCodeRequest $request): FamilyResource
    {
        return $request->responseResource(
            $this->familyRepository->joinFamilyByCode($request->dto())
        );
    }

    public function generateJoinCode(GenerateJoinCodeRequest $request): JsonResponse
    {
        $this->ensureCanManageFamily($request);

        try {
            $family = $this->familyRepository->generateJoinCodeAndRefreshFamily($request->getFamilySlug());
            
            return response()->json([
                'success' => true,
                'data' => ['joinCode' => $family->getJoinCode()],
                'message' => 'Join code generated successfully.'
            ]);
        } catch (\Throwable) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate join code. Please try again.'
            ], 500);
        }
    }

    public function inviteMember(InviteMemberRequest $request): JsonResponse
    {
        $this->ensureCanManageFamily($request);

        try {
            $this->familyRepository->inviteMember($request->dto());
            return $this->successResponse('Invitation sent successfully.');
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    private function ensureCanManageFamily($request): void
    {
        $familyMember = $request->get('_family_member');
        if (!$familyMember || !$this->canManageFamily($familyMember)) {
            abort(403, 'You do not have permission to manage this family.');
        }
    }

    private function ensureIsOwner($request): void
    {
        $family = $request->get('_family');
        $familyMember = $request->get('_family_member');

        if (!$family || !$familyMember || !$this->isOwner($family, $familyMember)) {
            abort(403, 'Only the family owner can perform this action.');
        }
    }

    private function successResponse(string $message): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message]);
    }

    private function canManageFamily(FamilyMember $member): bool
    {
        return in_array($member->getRole(), [FamilyRoleEnum::OWNER, FamilyRoleEnum::MODERATOR]);
    }

    private function isOwner(Family $family, FamilyMember $member): bool
    {
        return $family->getOwnerId() === $member->getUserId() &&
            $member->getRole() === FamilyRoleEnum::OWNER;
    }
}
