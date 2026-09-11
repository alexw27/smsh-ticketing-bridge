<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Controller;

use App\Core\Api\Http\ApiErrorCode;
use App\Core\Api\Http\ApiResponseFactory;
use App\Core\Auth\Entity\User;
use Smsh\TicketingBridge\Api\AddSignedInUserToOrganization;
use Smsh\TicketingBridge\Api\JoinOrganizationApiPayload;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * SmartShanghai-only: join the signed-in user to an organization.
 */
final class JoinOrganizationController extends AbstractController
{
    public function __construct(
        private readonly AddSignedInUserToOrganization $addSignedInUserToOrganization,
        private readonly ApiResponseFactory $apiResponseFactory,
    ) {
    }

    #[Route(path: '/api/v1/me/organizations', name: 'smsh_api_v1_me_organizations_join', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function __invoke(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->apiResponseFactory->error(
                ApiErrorCode::AUTHENTICATION_REQUIRED,
                'Authentication is required to access this resource.',
                JsonResponse::HTTP_UNAUTHORIZED,
            );
        }

        $body = json_decode($request->getContent(), true);
        if (!\is_array($body)) {
            return $this->apiResponseFactory->error(
                ApiErrorCode::INVALID_JSON,
                'Request body must be a JSON object.',
                JsonResponse::HTTP_BAD_REQUEST,
            );
        }

        try {
            $payload = JoinOrganizationApiPayload::fromRequestBody($body);
        } catch (\InvalidArgumentException $exception) {
            return $this->apiResponseFactory->error(
                ApiErrorCode::VALIDATION_FAILED,
                'Validation failed.',
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
                [$exception->getMessage() => 'Must be a valid organization identifier.'],
            );
        }

        try {
            $result = $this->addSignedInUserToOrganization->handle($user, $payload);
        } catch (\DomainException $exception) {
            return $this->mapDomainException($exception);
        }

        $organization = $result->organization;
        $status = $result->alreadyMember ? JsonResponse::HTTP_OK : JsonResponse::HTTP_CREATED;

        return $this->apiResponseFactory->success([
            'alreadyMember' => $result->alreadyMember,
            'organization' => [
                'id' => $organization->getId(),
                'name' => $organization->getName(),
                'slug' => $organization->getSlug(),
                'code' => $organization->getCode(),
            ],
        ], $status);
    }

    private function mapDomainException(\DomainException $exception): JsonResponse
    {
        return match ($exception->getMessage()) {
            AddSignedInUserToOrganization::ERROR_IDENTIFIER_REQUIRED => $this->apiResponseFactory->error(
                ApiErrorCode::VALIDATION_FAILED,
                'Provide organizationId, slug, or code.',
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
                ['organizationId' => 'Required unless slug or code is provided.'],
            ),
            AddSignedInUserToOrganization::ERROR_NOT_FOUND => $this->apiResponseFactory->error(
                'organization_not_found',
                'Organization not found.',
                JsonResponse::HTTP_NOT_FOUND,
            ),
            default => throw $exception,
        };
    }
}
